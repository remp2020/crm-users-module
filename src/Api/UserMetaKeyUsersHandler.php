<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\UsersModule\Repository\UserMetaRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserMetaKeyUsersHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $userMetaRepository;

    public function __construct(UserMetaRepository $userMetaRepository)
    {
        $this->userMetaRepository = $userMetaRepository;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-key-users.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $key = $json->key;
        $value = $json->value ?? null;

        $userMetaSelection = $this->userMetaRepository->usersWithKey($key, $value);
        $users = array_map(function ($data) {
                return [
                    'user_id' => $data->user_id,
                    'value' => $data->value,
                ];
        }, array_values($userMetaSelection->fetchAll()));

        $response = new JsonApiResponse(Response::S200_OK, $users);

        return $response;
    }
}
