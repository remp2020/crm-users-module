<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApiModule\Models\Authorization\ApiAuthorizationInterface;
use League\Event\Emitter;
use Nette\Security\Authorizator;

class UserTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    protected UsersApiAuthorizationInterface $authorizator;

    /** @var UsersApiAuthorizationInterface[] */
    protected $authorizators = [];

    public function __construct(
        protected DefaultUserTokenAuthorization $defaultUserTokenAuthorization,
        protected DeviceTokenAuthorization $deviceTokenAuthorization,
        protected Emitter $emitter
    ) {
    }

    public function registerAuthorizator(string $source, ApiAuthorizationInterface $authorizator, bool $useAlways = false)
    {
        $this->authorizators[$source] = [
            'source' => $source,
            'authorizator' => $authorizator,
            'useAlways' => $useAlways,
        ];
    }

    public function authorized($resource = Authorizator::ALL): bool
    {
        foreach ($this->authorizators as $authDef) {
            if ($authDef['useAlways'] || (isset($_GET['source']) && $authDef['source'] === $_GET['source'])) {
                $this->authorizator = $authDef['authorizator'];
                if ($this->authorizator->authorized($resource)) {
                    return true;
                }
            }
        }

        $this->authorizator = clone $this->defaultUserTokenAuthorization;
        if ($this->authorizator->authorized($resource)) {
            return true;
        }

        $this->authorizator = clone $this->deviceTokenAuthorization;
        return $this->authorizator->authorized($resource);
    }

    public function getErrorMessage(): ?string
    {
        if (!isset($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getErrorMessage();
    }

    public function getAuthorizedData()
    {
        if (!isset($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAuthorizedData();
    }

    public function getAuthorizedUsers(): array
    {
        if (!isset($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAuthorizedUsers();
    }

    public function getAccessTokens(): array
    {
        if (!isset($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        if (!$this->authorizator instanceof AccessTokensApiAuthorizationInterface) {
            return [];
        }
        return $this->authorizator->getAccessTokens();
    }
}
