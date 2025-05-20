<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Events\UserSignOutEvent;
use Crm\UsersModule\Models\Auth\AccessTokensApiAuthorizationInterface;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersLogoutHandler extends ApiHandler
{
    private $accessTokensRepository;

    private $emitter;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        Emitter $emitter,
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!($authorization instanceof AccessTokensApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'AccessTokensApiAuthorizationInterface'");
        }

        $loggedOutUsers = [];
        foreach ($authorization->getAccessTokens() as $accessToken) {
            $loggedOutUsers[$accessToken->user_id] = $accessToken->user;
            $this->accessTokensRepository->remove($accessToken->token);
        }
        foreach ($loggedOutUsers as $user) {
            $this->emitter->emit(new UserSignOutEvent($user));
        }

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok']);
        return $response;
    }
}
