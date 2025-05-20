<?php

namespace Crm\UsersModule\Repositories;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Request;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\ApplicationModule\Repositories\CacheRepository;
use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Events\UserDisabledEvent;
use Crm\UsersModule\Events\UserEnabledEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Events\UserUpdatedEvent;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;
use Ramsey\Uuid\Uuid;

class UsersRepository extends Repository
{
    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    public const DEFAULT_REGISTRATION_CHANNEL = 'crm';

    protected $tableName = 'users';

    private $emitter;

    private $hermesEmitter;

    private $accessTokensRepository;

    private $cacheRepository;

    private Translator $translator;

    private $passwords;

    public function __construct(
        Explorer $database,
        Emitter $emitter,
        AuditLogRepository $auditLogRepository,
        CacheRepository $cacheRepository,
        \Tomaj\Hermes\Emitter $hermesEmmiter,
        AccessTokensRepository $accessTokensRepository,
        Translator $translator,
        Passwords $passwords,
    ) {
        parent::__construct($database);
        $this->emitter = $emitter;
        $this->auditLogRepository = $auditLogRepository;
        $this->hermesEmitter = $hermesEmmiter;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->cacheRepository = $cacheRepository;
        $this->translator = $translator;
        $this->passwords = $passwords;
    }

    /**
     * @inheritDoc
     */
    final public function getByEmail($email): ?ActiveRow
    {
        return $this->getTable()->select('*')->where(['email' => $email])->fetch();
    }

    final public function getByExternalId($extId)
    {
        return $this->getTable()->where(['ext_id' => $extId])->fetch();
    }

    final public function add(
        $email,
        $password,
        $role = self::ROLE_USER,
        $active = true,
        $extId = null,
        $preregistration = false,
        ?string $locale = null,
    ) {
        $user = $this->getByEmail($email);
        if ($user) {
            throw new UserAlreadyExistsException("Email '$email' je už registrovaný");
        }
        if (strlen($password) < 5) {
            throw new ShortPasswordException('Heslo je príliš krátke');
        }
        if ($locale === null) {
            $locale = $this->translator->getDefaultLocale();
        }

        $row = $this->insert([
            'email' => $email,
            'public_name' => $email,
            'password' => $this->passwords->hash($password),
            'role' => $role,
            'created_at' => new \DateTime(),
            'modified_at' => new \DateTime(),
            'active' => (int)$active,
            'ext_id' => $extId,
            'uuid' => Uuid::uuid4(),
            'registration_channel' => self::DEFAULT_REGISTRATION_CHANNEL,
            'locale' => $locale,
        ]);

        $this->emitter->emit(new NewUserEvent($row));
        if (!$preregistration) {
            $this->emitUserRegisteredEvents($row, $password);
        }

        return $row;
    }

    final public function totalCount($allowCached = false, $forceCacheUpdate = false): int
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return (int) $this->cacheRepository->loadAndUpdate(
                'users_count',
                $callable,
                DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate,
            );
        }
        return $callable();
    }

    final public function addSignIn($user)
    {
        return $this->getTable()->where(['id' => $user->id])->update([
            'current_sign_in_at' => new \DateTime(),
            'current_sign_in_ip' => Request::getIp(),
        ]);
    }

    /**
     * @param string $text
     * @return Selection
     */
    final public function all($text = '')
    {
        $table = $this->getTable()->where(['deleted_at' => null])->order('users.id DESC');

        if (!empty($text)) {
            foreach (explode(" ", $text) as $word) {
                $table
                    ->where(
                        'users.id = ? OR users.email LIKE ? OR users.public_name LIKE ? OR users.first_name LIKE ? OR users.last_name LIKE ?',
                        [
                            $word,
                            "%{$word}%",
                            "%{$word}%",
                            "%{$word}%",
                            "%{$word}%",
                        ],
                    );
            }
        }

        return $table;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $email = $data['email'] ?? null;
        if ($email !== null) {
            $user = $this->getTable()->where(['email' => $data['email'], 'id != ?' => $row->id])->fetch();
            if ($user) {
                throw new UserAlreadyExistsException("Email '{$data['email']}' je už registrovaný");
            }
        }

        $emailChanged = false;
        $originalEmail = $row->email;
        if ($email && $originalEmail !== $email) {
            $emailChanged = true;
            $data['email_validated_at'] = null;
        }

        $data['modified_at'] = new \DateTime();
        $result = parent::update($row, $data);

        if ($emailChanged) {
            $this->hermesEmitter->emit(new HermesMessage(
                'email-changed',
                [
                    'user_id' => $row->id,
                    'original_email' => $originalEmail,
                    'new_email' => $row->email,
                ],
            ), HermesMessage::PRIORITY_DEFAULT);
        }
        $this->emitter->emit(new UserUpdatedEvent($row));

        return $result;
    }

    final public function toggleActivation($user)
    {
        $active = 1;
        if ($user->active) {
            $active = 0;
        }
        parent::update($user, [
            'active' => $active,
            'modified_at' => new \DateTime(),
        ]);
        if ($active) {
            $this->emitter->emit(new UserEnabledEvent($user));
        } else {
            $this->accessTokensRepository->removeAllUserTokens($user->id);
            $this->emitter->emit(new UserDisabledEvent($user));
        }
        return $user;
    }

    final public function getUsersRegisteredBetween(DateTime $startTime, DateTime $endTime = null)
    {
        if (!$endTime) {
            $endTime = new DateTime();
        }

        return $this->getTable()->where([
            'created_at > ?' => $startTime,
            'created_at < ?' => $endTime,
        ]);
    }

    final public function usersWithoutPassword()
    {
        return $this->getTable()->where(['password' => '']);
    }

    final public function getAbusiveUsers(DateTime $start, DateTime $end, $tokenCount = 10, $deviceCount = 1, $sortBy = 'device_count', $email = null)
    {
        if (!in_array($sortBy, ['device_count', 'token_count'], true)) {
            $sortBy = 'device_count';
        }

        $selection = $this->getTable()->select('users.*, COUNT(:access_tokens.id) AS token_count, COUNT(DISTINCT :access_tokens.user_agent) AS device_count')
            ->where([':access_tokens.last_used_at >= ?' => $start, ':access_tokens.last_used_at < ?' => $end])
            ->group('users.id')
            ->having('token_count >= ? AND device_count >= ?', $tokenCount, $deviceCount)
            ->order("$sortBy DESC");

        if ($email) {
            $selection->where('users.email LIKE ?', "%{$email}%");
        }

        return $selection;
    }

    final public function getNoConfirmed(DateTime $toTime)
    {
        return $this->getTable()->where(['created_at <= ?' => $toTime, 'confirmed_at' => null]);
    }

    final public function getUserSources()
    {
        return $this->getTable()->select('distinct(source)')->fetchPairs('source', 'source');
    }

    /**
     * @param DateTime $from
     * @param DateTime $to
     * @return Selection
     */
    final public function usersRegisteredBetween(DateTime $from, DateTime $to)
    {
        return $this->getTable()->where([
            'created_at > ?' => $from,
            'created_at < ?' => $to,
        ]);
    }

    final public function isRole($userId, $role)
    {
        return $this->getTable()->where([
            'id' => $userId,
            'role' => $role,
        ])->count('*') > 0;
    }

    final public function setEmailValidated(ActiveRow $user, \DateTime $validatedAt): void
    {
        if ($user->email_validated_at) {
            return;
        }
        $this->update($user, [
            'email_validated_at' => $validatedAt,
        ]);
    }

    final public function setEmailInvalidated(ActiveRow $user): void
    {
        $this->update($user, [
            'email_validated_at' => null,
        ]);
    }

    final public function emitUserRegisteredEvents($user, $password, $sendEmail = false): void
    {
        $this->emitter->emit(new UserRegisteredEvent($user, $password, $sendEmail));
        $this->hermesEmitter->emit(new HermesMessage('user-registered', [
            'user_id' => $user->id,
            'password' => $password,
        ]), HermesMessage::PRIORITY_HIGH);
    }
}
