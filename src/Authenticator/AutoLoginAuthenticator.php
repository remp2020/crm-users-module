<?php

namespace Crm\UsersModule\Authenticator;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\BaseAuthenticator;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;

/**
 * AutoLoginAuthenticator is used to login user after sign up.
 *
 * Required credentials (use `setCredentials()`):

 * - \Nette\Database\Table\ActiveRow 'user' - user created after sign up,
 * - bool 'autoLogin => false' - must be set to true to indicate we want to autologin user.
 */
class AutoLoginAuthenticator extends BaseAuthenticator
{
    /** @var UsersRepository */
    private $usersRepository;

    /** @var ActiveRow */
    private $user = null;

    /** @var bool */
    private $autoLogin = false;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        UsersRepository $usersRepository
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);

        $this->usersRepository = $usersRepository;
    }

    public function authenticate()
    {
        if ($this->user === null || $this->autoLogin !== true) {
            return false;
        }

        $this->addAttempt($this->user->email, $this->user, $this->source, LoginAttemptsRepository::STATUS_LOGIN_AFTER_SIGN_UP);
        $this->usersRepository->addSignIn($this->user);

        return $this->user;
    }

    public function setCredentials(array $credentials) : AuthenticatorInterface
    {
        parent::setCredentials($credentials);

        $this->autoLogin = $credentials['autoLogin'] ?? false;
        $this->user = $credentials['user'] ?? null;

        return $this;
    }
}
