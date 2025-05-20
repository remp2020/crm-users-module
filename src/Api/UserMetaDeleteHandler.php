<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\ApiModule\Models\Response\EmptyResponse;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserMetaDeleteHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $userMetaRepository;

    private $usersRepository;

    public function __construct(
        UserMetaRepository $userMetaRepository,
        UsersRepository $usersRepository,
    ) {
        $this->userMetaRepository = $userMetaRepository;
        $this->usersRepository = $usersRepository;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-delete.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $userId = $json->user_id;
        $key = $json->key;
        $value = $json->value ?? null;

        $userRow = $this->usersRepository->find($userId);

        if (!$userRow) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => "User not found: {$userId}",
            ]);

            return $response;
        }

        $this->userMetaRepository->removeMeta($userId, $key, $value);

        $response = new EmptyResponse();
        return $response;
    }
}
