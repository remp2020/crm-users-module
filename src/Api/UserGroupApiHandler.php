<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Nette\Http\Request;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserGroupApiHandler extends ApiHandler
{
    /** @var UserManager  */
    private $userManager;

    /** @var GroupsRepository  */
    private $groupsRepository;

    /** @var UserGroupsRepository  */
    private $userGroupsRepository;

    /** @var Request  */
    private $request;

    public function __construct(Request $request, UserManager $userManager, GroupsRepository $groupsRepository, UserGroupsRepository $userGroupsRepository)
    {
        $this->request = $request;
        $this->userManager = $userManager;
        $this->groupsRepository = $groupsRepository;
        $this->userGroupsRepository = $userGroupsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'group_id', InputParam::REQUIRED),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        $user = $this->userManager->loadUserByEmail($params['email']);
        if (!$user) {
            $result = [
                'status' => 'error',
                'message' => 'User doesn\'t exists',
            ];
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, $result);
            return $response;
        }

        $group = $this->groupsRepository->find($params['group_id']);
        if (!$group) {
            $result = [
                'status' => 'error',
                'message' => 'Group doesn\'t exists',
            ];
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, $result);
            return $response;
        }

        if ($this->getAction() == 'add') {
            $this->userGroupsRepository->addToGroup($group, $user);
        } else {
            $this->userGroupsRepository->removeFromGroup($group, $user);
        }

        $result = [
            'status' => 'ok',
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);

        return $response;
    }

    private function getAction()
    {
        $parts = explode('/', $this->request->getUrl()->getPath());
        if ($parts[count($parts) - 1] == 'add-to-group') {
            return 'add';
        } else {
            return 'remove';
        }
    }
}
