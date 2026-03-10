<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApplicationModule\Models\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Models\Authenticator\AuthenticatorManager;
use Crm\UsersModule\Events\UserSignInEvent;
use Crm\UsersModule\Models\Auth\Rate\RateLimitException;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\SimpleIdentity;
use Nette\SmartObject;

class UserAuthenticator
{
    use SmartObject;

    const COLUMN_PASSWORD_HASH = 'password';

    public function __construct(
        private readonly Emitter $emitter,
        private readonly AuthenticatorManager $authenticatorManager,
        private readonly UnclaimedUser $unclaimedUser,
    ) {
    }

    /**
     * Runs the authenticator chain with the given credential array.
     *
     * @throws AuthenticationException
     */
    public function authenticateCredentials(array $credentials): SimpleIdentity
    {
        $user = false;
        $source = null;
        $regenerateToken = null;
        $exception = null;
        $authenticators = $this->authenticatorManager->getAuthenticators();
        /** @var AuthenticatorInterface $authenticator */
        foreach ($authenticators as $authenticator) {
            try {
                $u = $authenticator->setCredentials($credentials)->authenticate();
                if ($u && $this->unclaimedUser->isUnclaimedUser($u)) {
                    // No exception thrown here, this shouldn't propagate outside. Only exceptions thrown
                    // by authenticators themselves will be propagated.

                     $u = null;
                }

                $regenerateToken = $authenticator->shouldRegenerateToken();

                if ($u !== null && $u !== false) {
                    $user = $u;
                    $source = $authenticator->getSource();
                    break;
                }
            } catch (RateLimitException $e) {
                $exception = $e;
                break;
            } catch (AuthenticationException $e) {
                if ($exception === null) {
                    $exception = $e;
                }
                continue;
            }
        }

        if ($user === false && $exception !== null) {
            throw $exception;
        }
        if ($user === false) {
            throw new AuthenticationException('', Authenticator::IdentityNotFound);
        }
        $this->emitter->emit(new UserSignInEvent($user, $source, $regenerateToken ?? true));

        return $this->getIdentity($user);
    }

    public function getIdentity(ActiveRow $user): SimpleIdentity
    {
        $arr = $user->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);

        $groups = [];
        if ($user['role'] === UsersRepository::ROLE_ADMIN) {
            $userGroups = $user->related('admin_user_groups');
            foreach ($userGroups as $userGroup) {
                $groups[] = $userGroup->admin_group->name;
            }
        }

        return new SimpleIdentity($user['id'], $groups, $arr);
    }
}
