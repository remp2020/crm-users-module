<?php

namespace Crm\UsersModule\Builder;

use Crm\ApplicationModule\Builder\Builder;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Events\UserCreatedEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Security\Passwords;

class UserBuilder extends Builder
{
    private $emitter;

    private $hermesEmitter;

    private $originalPassword;

    private $accessToken;

    protected $tableName = 'users';

    private $sendEmail = false;

    public function __construct(
        Context $database,
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        AccessToken $accessToken
    ) {
        parent::__construct($database);
        $this->emitter = $emitter;
        $this->hermesEmitter = $hermesEmitter;
        $this->accessToken = $accessToken;
    }

    public function isValid()
    {
        if (strlen($this->get('email')) < 1) {
            $this->addError('Nebol zadaný email');
        }
        $user = $this->database->table($this->tableName)->where(['LOWER(email)' => strtolower($this->get('email'))])->fetch();
        if ($user) {
            $this->addError("Email '{$this->get('email')}' je už registrovaný");
        }
        if (strlen($this->get('password')) < 6) {
            $this->addError('Heslo musí byť dlhé aspon 6 znakov');
        }
        if (strlen($this->get('public_name')) < 1) {
            $this->addError('Missing public_name');
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
        $this->setOption('add_user_token', true);
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

    public function setRole($role)
    {
        return $this->set('role', $role);
    }

    public function setPassword($password, $generateHash = true)
    {
        if ($generateHash) {
            $this->originalPassword = $password;
            $password = Passwords::hash($password);
        }
        return $this->set('password', $password);
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

    protected function store($tableName)
    {
        $row = parent::store($tableName);
        $this->emitter->emit(new UserCreatedEvent($row, $this->originalPassword, $this->sendEmail));

        if ($this->getOption('add_user_token')) {
            $this->accessToken->addUserToken($row, null, null, $this->get('source'));
        }

        $this->hermesEmitter->emit(new HermesMessage('user-created', [
            'user_id' => $row->id,
            'password' => $this->originalPassword
        ]));
        return $row;
    }
}
