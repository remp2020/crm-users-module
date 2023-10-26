<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\User\UserDataStorageInterface;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\IRequest;
use Nette\Utils\Json;

class UserData
{
    private array $tokenDataCache = [];

    public function __construct(
        private UserDataRegistrator $userDataRegistrator,
        private UserDataStorageInterface $userDataStorage,
        private AccessTokensRepository $accessTokensRepository,
        private AccessToken $accessToken
    ) {
    }

    public function refreshUserTokens($userId): void
    {
        $tokens = $this->accessTokensRepository->allUserTokens($userId)->fetchAll();
        if (!count($tokens)) {
            // no need to generate user data if there are no access tokens to store.
            return;
        }

        $userDataContent = $this->userDataRegistrator->generate($userId);

        $tokensString = [];
        foreach ($tokens as $token) {
            $tokensString[] = $token->token;
        }
        $this->userDataStorage->multiStore($tokensString, Json::encode($userDataContent));
    }

    public function getCurrentUserData(IRequest $request, bool $ignoreCache = false): ?object
    {
        $token = $this->accessToken->getToken($request);
        if (!$token) {
            return null;
        }

        if (!$ignoreCache && array_key_exists($token, $this->tokenDataCache)) {
            return $this->tokenDataCache[$token];
        }

        $userDataJson = $this->userDataStorage->load($token);
        if ($userDataJson) {
            $userData = Json::decode($userDataJson);
            $this->tokenDataCache[$token] = $userData;
            return $userData;
        }
        return null;
    }

    public function getUserToken($token): ?object
    {
        $data = $this->userDataStorage->load($token);
        if ($data) {
            return Json::decode($data);
        }
        return null;
    }

    public function getUserTokens(array $tokens): array
    {
        $data = $this->userDataStorage->multiLoad($tokens);
        $result = [];
        foreach ($data as $row) {
            if ($row !== null) {
                $result[] = Json::decode($row);
            }
        }
        return $result;
    }

    public function removeUserToken($token)
    {
        return $this->userDataStorage->remove($token);
    }
}
