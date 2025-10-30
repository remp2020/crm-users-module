<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListUsersHandler extends ApiHandler
{
    const PAGE_SIZE = 1000;

    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('user_ids'))->setRequired(),
            (new PostInputParam('page'))->setRequired(),
            new PostInputParam('include_deactivated'),
            new PostInputParam('with_uuid'),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        if (!$params['user_ids']) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'error' => 'missing_param', 'message' => 'missing required parameter: user_ids']);
            return $response;
        }

        if (!$params['page']) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'error' => 'missing_param', 'message' => 'missing required parameter: page']);
            return $response;
        }

        $includeDeactivated = filter_var($params['include_deactivated'], FILTER_VALIDATE_BOOLEAN);
        $withUuid = filter_var($params['with_uuid'] ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $userIds = Json::decode($params['user_ids'], forceArrays: true);
        } catch (JsonException $e) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => 'user_ids should be valid JSON array']);
        }

        $selectFields = 'id, email';
        if ($withUuid) {
            $selectFields .= ', uuid';
        }

        $query = $this->usersRepository->all()
            ->select($selectFields)
            ->where('deleted_at IS NULL') // never list anonymized users
            ->order('id ASC');

        if (!$includeDeactivated) {
            $query->where('active = ?', true);
        }

        if (!empty($userIds)) {
            $query->where(['id' => $userIds]);
        }

        $users = (clone($query))
            ->limit(self::PAGE_SIZE, ($params['page']-1) * self::PAGE_SIZE);
        $count = (clone($query))
            ->count('*');
        $totalPages = ceil((float)$count / (float)self::PAGE_SIZE);

        $resultArr = [];
        /** @var ActiveRow $user */
        foreach ($users as $user) {
            $userData = [
                'id' => $user->id,
                'email' => $user->email,
            ];
            if ($withUuid) {
                $userData['uuid'] = $user->uuid;
            }
            $resultArr[$user->id] = $userData;
        }

        $result = [
            'status' => 'ok',
            'page' => (int)$params['page'],
            'totalPages' => $totalPages,
            'totalCount' => $count,
            'users' => $resultArr,
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
