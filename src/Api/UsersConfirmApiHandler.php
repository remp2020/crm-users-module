<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\IdempotentHandlerInterface;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\ApiModule\Models\Params\ParamsProcessor;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersConfirmApiHandler extends ApiHandler implements IdempotentHandlerInterface
{
    private $userManager;

    public function __construct(
        UserManager $userManager
    ) {
        $this->userManager = $userManager;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $user = $this->userManager->loadUserByEmail($params['email']);

        if (!$user) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'code' => 'user_not_found']);
            return $response;
        }

        $this->userManager->confirmUser($user);

        return $this->createResponse();
    }

    public function idempotentHandle(array $params): ResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $user = $this->userManager->loadUserByEmail($params['email']);

        if (!$user) {
            $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok']);
            return $response;
        }

        return $this->createResponse();
    }

    private function createResponse()
    {
        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok']);
        return $response;
    }
}
