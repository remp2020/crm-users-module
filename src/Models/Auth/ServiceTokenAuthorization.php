<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApiModule\Models\Authorization\BearerTokenAuthorization;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\IRequest;
use Nette\Security\Authorizator;

class ServiceTokenAuthorization implements UsersApiAuthorizationInterface
{
    private $authorizedUsers = [];

    /** @var UsersApiAuthorizationInterface */
    private $authorizator;

    private $bearerTokenAuthorization;

    private $userTokenAuthorization;

    private $usersRepository;

    private $request;

    public function __construct(
        BearerTokenAuthorization $bearerTokenAuthorization,
        UserTokenAuthorization $userTokenAuthorization,
        UsersRepository $usersRepository,
        IRequest $request
    ) {
        $this->bearerTokenAuthorization = $bearerTokenAuthorization;
        $this->userTokenAuthorization = $userTokenAuthorization;
        $this->usersRepository = $usersRepository;
        $this->request = $request;
    }

    public function authorized($resource = Authorizator::ALL): bool
    {
        $userId = $this->request->getQuery('user_id');
        if ($userId) {
            $this->authorizator = $this->bearerTokenAuthorization;
            $isAuthorized = $this->authorizator->authorized($resource);
            if ($isAuthorized) {
                $user = $this->usersRepository->find($userId);
                if ($user) {
                    $this->authorizedUsers[] = $this->usersRepository->find($userId);
                    return true;
                }
            }
        }

        $this->authorizator = $this->userTokenAuthorization;
        $isAuthorized = $this->authorizator->authorized($resource);
        if ($isAuthorized) {
            $this->authorizedUsers = $this->authorizator->getAuthorizedUsers();
            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function getErrorMessage(): ?string
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getErrorMessage();
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getAuthorizedData()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAuthorizedData();
    }

    public function getAuthorizedUsers()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizedUsers;
    }
}
