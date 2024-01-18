<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\User\UserData;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersTouchApiHandler extends ApiHandler
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserData $userData
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired()
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $userId = $params['id'];
        $user = $this->usersRepository->find($userId);

        if (!$user || ($user && $user->deleted_at)) {
            return new JsonApiResponse(IResponse::S404_NotFound, [
                'status' => 'error',
                'code' => 'user_not_found',
                'message' => 'User not found: ' . $params['id'],
            ]);
        }

        $this->userData->refreshUserTokens($userId);

        return new JsonApiResponse(IResponse::S200_OK, [
            'status' => 'ok',
            'message' => 'User touched',
        ]);
    }
}
