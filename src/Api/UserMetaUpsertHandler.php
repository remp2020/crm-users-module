<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserMetaUpsertHandler extends ApiHandler
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
        $result = $this->validateInput(__DIR__ . '/user-meta-upsert.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $userId = $json->user_id;
        $key = $json->key;
        $value = $json->value;
        $isPublic = $json->is_public ?? false;

        $userRow = $this->usersRepository->find($userId);

        if (!$userRow) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => "User not found: {$userId}",
            ]);

            return $response;
        }

        $userMetaRow = $this->userMetaRepository->add($userRow, $key, $value, null, $isPublic);

        $response = new JsonApiResponse(Response::S200_OK, [
            'key' => $userMetaRow->key,
            'value' => $userMetaRow->value,
            'is_public' => (bool)$userMetaRow->is_public,
        ]);

        return $response;
    }
}
