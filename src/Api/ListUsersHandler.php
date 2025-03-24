<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
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
            new InputParam(InputParam::TYPE_POST, 'user_ids', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'page', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'include_deactivated', InputParam::OPTIONAL),
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

        try {
            $userIds = Json::decode($params['user_ids'], Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'user_ids should be valid JSON array']);
            return $response;
        }

        $query = $this->usersRepository->all()
            ->select('id, email')
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
            $resultArr[$user->id] = [
                'id' => $user->id,
                'email' => $user->email,
            ];
        }

        $result = [
            'status' => 'ok',
            'page' => intval($params['page']),
            'totalPages' => $totalPages,
            'totalCount' => $count,
            'users' => $resultArr,
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
