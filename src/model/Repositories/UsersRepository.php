<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\UsersModule\Events\UserDisabledEvent;
use Crm\UsersModule\Events\UserUpdatedEvent;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;

class UsersRepository extends Repository
{
    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    protected $tableName = 'users';

    private $emitter;

    private $hermesEmitter;

    private $addressesRepository;

    private $accessTokensRepository;

    private $cacheRepository;

    public function __construct(
        Context $database,
        Emitter $emitter,
        AuditLogRepository $auditLogRepository,
        CacheRepository $cacheRepository,
        \Tomaj\Hermes\Emitter $hermesEmmiter,
        AddressesRepository $addressesRepository,
        AccessTokensRepository $accessTokensRepository
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->emitter = $emitter;
        $this->auditLogRepository = $auditLogRepository;
        $this->hermesEmitter = $hermesEmmiter;
        $this->addressesRepository = $addressesRepository;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->cacheRepository = $cacheRepository;
    }

    /**
     * @return bool|mixed|IRow
     */
    public function getByEmail($email)
    {
        return $this->getTable()->select('*')->where(['email' => $email])->fetch();
    }

    public function add(
        $email,
        $password,
        $firstName,
        $lastName,
        $role = self::ROLE_USER,
        $active = true,
        $address = '',
        $extId = null
    ) {
        $user = $this->getByEmail($email);
        if ($user) {
            throw new UserAlreadyExistsException("Email '$email' je už registrovaný");
        }
        if (strlen($password) < 5) {
            throw new ShortPasswordException('Heslo je príliš krátke');
        }
        return $this->insert([
            'email' => $email,
            'password' => Passwords::hash($password),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'created_at' => new \DateTime(),
            'modified_at' => new \DateTime(),
            'active' => intval($active),
            'address' => $address,
            'ext_id' => $extId,
        ]);
    }

    public function totalCount($allowCached = false, $forceCacheUpdate = false)
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return $this->cacheRepository->loadAndUpdate(
                'users_count',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }
        return $callable();
    }

    public function addSignIn($user)
    {
        return $this->getTable()->where(['id' => $user->id])->update([
            'current_sign_in_at' => new \DateTime(),
            'last_sign_in_at' => $user->current_sign_in_at,
            'current_sign_in_ip' => \Crm\ApplicationModule\Request::getIp(),
            'last_sign_in_ip' => $user->current_sign_in_ip,
        ]);
    }

    /**
     * @param string $text
     * @return Selection
     */
    public function all($text = '')
    {
        $table = $this->getTable()->where(['deleted_at' => null])->order('users.id DESC');

        if (!empty($text)) {
            $companyIdText = str_replace(' ', '', $text);
            $matchingUsersWithCompanyId = $this->addressesRepository->all()->select('DISTINCT(user_id)')
                ->where("REPLACE(company_id, ' ', '') = ? OR REPLACE(company_tax_id, ' ', '') = ? OR REPLACE(company_vat_id, ' ', '') = ?", [
                    "{$companyIdText}",
                    "{$companyIdText}",
                    "{$companyIdText}",
                ])->fetchPairs('user_id', 'user_id');

            foreach (explode(" ", $text) as $word) {
                $table
                    ->where(
                        'users.id = ? OR users.email LIKE ? OR users.first_name LIKE ? OR users.last_name LIKE ? OR users.id IN (?) OR users.id IN (?)',
                        [
                            $word,
                            "%{$word}%",
                            "%{$word}%",
                            "%{$word}%",
                            $this->addressesRepository->all()->select('DISTINCT(user_id)')
                                ->where(
                                    'address LIKE ? OR number LIKE ? OR city LIKE ? OR first_name LIKE ? OR last_name LIKE ?',
                                    [
                                    "%{$word}%",
                                    "%{$word}%",
                                    "%{$word}%",
                                    "%{$word}%",
                                    "%{$word}%",
                                    ]
                                ),
                            $matchingUsersWithCompanyId,
                        ]
                    )
                    ->group('users.id');
            }
        }

        return $table;
    }

    public function update(IRow &$row, $data)
    {
        if (isset($data['email'])) {
            $originalEmail = $row->email;
            $user = $this->getTable()->where(['email' => $data['email'], 'id != ?' => $row->id])->fetch();
            if ($user) {
                throw new UserAlreadyExistsException("Email '{$data['email']}' je už registrovaný");
            }
        }
        $data['modified_at'] = new \DateTime();
        parent::update($row, $data);

        if (isset($originalEmail) && $originalEmail !== $data['email']) {
            $this->hermesEmitter->emit(new HermesMessage(
                'email-changed',
                [
                    'user_id' => $row->id,
                    'original_email' => $originalEmail,
                    'new_email' => $row->email,
                ]
            ));
        }
        $this->emitter->emit(new UserUpdatedEvent($row));
    }

    public function toggleActivation($user)
    {
        $active = 1;
        if ($user->active) {
            $active = 0;
        }
        parent::update($user, [
            'active' => $active,
            'modified_at' => new \DateTime(),
        ]);
        if ($active == 0) {
            $this->accessTokensRepository->removeAllUserTokens($user->id);
            $this->emitter->emit(new UserDisabledEvent($user));
        }
        return $user;
    }

    public function getUsersRegisteredBetween(DateTime $startTime, DateTime $endTime = null)
    {
        if (!$endTime) {
            $endTime = new DateTime();
        }

        return $this->getTable()->where([
            'created_at > ?' => $startTime,
            'created_at < ?' => $endTime
        ]);
    }

    public function usersWithoutPassword()
    {
        return $this->getTable()->where(['password' => '']);
    }

    public function getAbusiveUsers(DateTime $start, DateTime $end, $tokenCount = 10, $deviceCount = 1, $sortBy = 'device_count')
    {
        if (!in_array($sortBy, ['device_count', 'token_count'], true)) {
            $sortBy = 'device_count';
        }

        return $this->getTable()->select('users.*, COUNT(:access_tokens.id) AS token_count, COUNT(DISTINCT :access_tokens.user_agent) AS device_count')
            ->where([':access_tokens.last_used_at >= ?' => $start, ':access_tokens.last_used_at < ?' => $end])
            ->group('users.id')
            ->having('token_count >= ? AND device_count >= ?', $tokenCount, $deviceCount)
            ->order("$sortBy DESC");
    }

    public function getNoConfirmed(DateTime $toTime)
    {
        return $this->getTable()->where(['created_at <= ?' => $toTime, 'confirmed_at' => null]);
    }

    public function getUserSources()
    {
        return $this->getTable()->select('distinct(source)')->fetchPairs('source', 'source');
    }

    /**
     * @param DateTime $from
     * @param DateTime $to
     * @return Selection
     */
    public function usersRegisteredBetween(DateTime $from, DateTime $to)
    {
        return $this->getTable()->where([
            'created_at > ?' => $from,
            'created_at < ?' => $to,
        ]);
    }

    public function isRole($userId, $role)
    {
        return $this->getTable()->where([
            'id' => $userId,
            'role' => $role,
        ])->count('*') > 0;
    }
}
