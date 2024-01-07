<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Authorization\TokenParser;
use Crm\UsersModule\User\UserData;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserDataHandler extends ApiHandler
{
    private $userData;

    private $errorMessage;

    public function __construct(
        UserData $userData
    ) {
        $this->userData = $userData;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $tokenParser = new TokenParser();
        if (!$tokenParser->isOk()) {
            $this->errorMessage = $tokenParser->errorMessage();
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $tokenParser->errorMessage()]);
            return $response;
        }

        $result = $this->userData->getUserToken($tokenParser->getToken());

        if (!$result) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Token not found']);
            return $response;
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
