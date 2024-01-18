<?php

namespace Crm\UsersModule\Models\Builder;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Builder\Builder;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Security\Passwords;

class UserBuilder extends Builder
{
    private $originalPassword;

    protected $tableName = 'users';

    private bool $sendEmail;

    private array $metaItems;

    private bool $emitUserRegisteredEvent;

    private array $passwordLazyParams;

    public function __construct(
        Explorer $database,
        private Emitter $emitter,
        private AccessToken $accessToken,
        private UsersRepository $usersRepository,
        private UserMetaRepository $userMetaRepository,
        private Translator $translator,
        private Passwords $passwords
    ) {
        parent::__construct($database);
    }

    public function isValid()
    {
        if (strlen($this->get('email')) < 1) {
            $this->addError($this->translator->translate('users.model.user_builder.error.email_missing'));
        }
        $user = $this->database->table($this->tableName)->where(['email' => $this->get('email')])->fetch();
        if ($user) {
            $this->addError($this->translator->translate('users.model.user_builder.error.email_taken', ['email' => $this->get('email')]));
        }
        $password = $this->get('password');
        if (strlen($password) < 6) {
            $this->addError($this->translator->translate('users.model.user_builder.error.password_short', ['length' => 6]));
        }
        if (strlen($this->get('public_name')) < 1) {
            $this->addError($this->translator->translate('users.model.user_builder.error.public_name_missing'));
        }

        if (count($this->getErrors()) > 0) {
            return false;
        }
        return true;
    }

    protected function setDefaults()
    {
        $this->set('created_at', new \DateTime());
        $this->set('modified_at', new \DateTime());
        $this->set('active', true);
        $this->set('first_name', null);
        $this->set('last_name', null);
        $this->set('public_name', null);
        $this->set('ext_id', null);
        $this->set('role', UsersRepository::ROLE_USER);
        $this->set('invoice', false);
        $this->set('locale', $this->translator->getDefaultLocale());
        $this->set('registration_channel', UsersRepository::DEFAULT_REGISTRATION_CHANNEL);
        $this->setOption('add_user_token', true);

        $this->sendEmail = false;
        $this->metaItems = [];
        $this->emitUserRegisteredEvent = true;
        $this->passwordLazyParams = [];
    }

    public function setEmail($email)
    {
        return $this->set('email', $email);
    }

    public function setFirstName($firstName)
    {
        return $this->set('first_name', $firstName);
    }

    public function setLastName($lastName)
    {
        return $this->set('last_name', $lastName);
    }

    public function setPublicName($publicName)
    {
        return $this->set('public_name', $publicName);
    }

    public function setLocale($locale)
    {
        return $this->set('locale', $locale);
    }

    public function setRole($role)
    {
        return $this->set('role', $role);
    }

    public function setPassword($password, $generateHash = true)
    {
        if ($generateHash) {
            $this->originalPassword = $password;
            $password = $this->passwords->hash($password);
        }
        return $this->set('password', $password);
    }

    /**
     * This does not immediately generate a password, only when save() is called.
     *
     * @param callable $getPasswordFunc
     * @param bool     $generateHash
     *
     * @return $this
     */
    public function setPasswordLazy(callable $getPasswordFunc, bool $generateHash = true): self
    {
        $this->passwordLazyParams = [$getPasswordFunc, $generateHash];
        return $this;
    }

    public function setActive($active)
    {
        return $this->set('active', $active);
    }

    public function setExtId($extId)
    {
        return $this->set('ext_id', $extId);
    }

    public function setInvoice($invoice)
    {
        return $this->set('invoice', $invoice);
    }

    public function setNote($note)
    {
        return $this->set('note', $note);
    }

    public function setCompanyName($companyName)
    {
        return $this->set('company_name', $companyName);
    }

    public function setIsInstitution($isInstitution = true)
    {
        return $this->set('is_institution', $isInstitution);
    }

    public function setInstitutionName($institutionName)
    {
        return $this->set('institution_name', $institutionName);
    }

    public function setSource($source)
    {
        return $this->set('source', $source);
    }

    public function setRegistrationChannel($registrationChannel)
    {
        return $this->set('registration_channel', $registrationChannel);
    }

    public function sendEmail($sendEmail)
    {
        $this->sendEmail = $sendEmail;
        return $this;
    }

    public function setReferer($referer)
    {
        return $this->set('referer', $referer);
    }

    public function setDisableAutoInvoice($disableAutoInvoice)
    {
        return $this->set('disable_auto_invoice', $disableAutoInvoice);
    }

    public function setAddTokenOption(bool $addToken)
    {
        return $this->setOption('add_user_token', $addToken);
    }

    public function setUserMeta(array $userMeta)
    {
        $this->metaItems = $userMeta;
        return $this;
    }

    public function setEmitUserRegisteredEvent(bool $emitUserRegisteredEvent)
    {
        $this->emitUserRegisteredEvent = $emitUserRegisteredEvent;
        return $this;
    }

    public function save()
    {
        if ($this->passwordLazyParams) {
            [$getPasswordFunc, $generateHash]= $this->passwordLazyParams;
            $this->setPassword($getPasswordFunc(), $generateHash);
        }

        return parent::save();
    }

    protected function store($tableName)
    {
        $user = parent::store($tableName);

        $this->emitter->emit(new NewUserEvent($user));

        foreach ($this->metaItems as $key => $value) {
            $this->userMetaRepository->add($user, $key, $value);
        }

        if ($this->emitUserRegisteredEvent) {
            $this->usersRepository->emitUserRegisteredEvents($user, $this->originalPassword, $this->sendEmail);
        }

        if ($this->getOption('add_user_token')) {
            $this->accessToken->addUserToken($user, null, null, $this->get('source'));
        }

        return $user;
    }
}
