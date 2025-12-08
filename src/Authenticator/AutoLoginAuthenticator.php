<?php

namespace Crm\UsersModule\Authenticator;

use Crm\ApplicationModule\Models\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Models\Authenticator\BaseAuthenticator;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
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
    /** @var ActiveRow */
    private $user = null;

    /** @var bool */
    private $autoLogin = false;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);
    }

    public function authenticate()
    {
        if ($this->user === null || $this->autoLogin !== true) {
            return false;
        }

        $this->addAttempt($this->user->email, $this->user, $this->source, LoginAttemptsRepository::STATUS_LOGIN_AFTER_SIGN_UP);

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
