<?php

namespace Crm\UsersModule\Models\Auth;

use Crm\ApiModule\Models\Authorization\TokenParser;
use Crm\ApplicationModule\Models\Request;
use Crm\UsersModule\Events\UserLastAccessEvent;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Security\Authorizator;

class DeviceTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    protected $accessTokensRepository;

    protected $deviceTokensRepository;

    protected $emitter;

    protected $errorMessage = false;

    protected $authorizedData = [];

    protected $authorizedUsers = [];

    protected $accessTokens = [];

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        Emitter $emitter
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->emitter = $emitter;
    }

    public function authorized($resource = Authorizator::ALL): bool
    {
        $this->authorizedData = [];
        $this->authorizedUsers = [];
        $this->accessTokens = [];

        $tokenParser = new TokenParser();
        if (!$tokenParser->isOk()) {
            $this->errorMessage = $tokenParser->errorMessage();
            return false;
        }

        $deviceToken = $this->deviceTokensRepository->findByToken($tokenParser->getToken());

        if (!$deviceToken) {
            $this->errorMessage = "Device token doesn't exist";
            return false;
        }

        $source = isset($_GET['source']) ? 'api+' . $_GET['source'] : null;
        $accessDate = new DateTime();
        $this->deviceTokensRepository->update($deviceToken, ['last_used_at' => $accessDate]);

        $accessTokens = $this->accessTokensRepository->findAllByDeviceToken($deviceToken);
        foreach ($accessTokens as $accessToken) {
            $this->authorizedUsers[$accessToken->user_id] = $accessToken->user;
            $this->accessTokens[] = $accessToken;
            $this->emitter->emit(new UserLastAccessEvent(
                $accessToken->user,
                $accessDate,
                $source,
                Request::getUserAgent()
            ));
        }

        $this->authorizedData['token'] = $deviceToken;
        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getAuthorizedData()
    {
        return $this->authorizedData;
    }

    public function getAuthorizedUsers()
    {
        return $this->authorizedUsers;
    }

    public function getAccessTokens()
    {
        return $this->accessTokens;
    }
}
