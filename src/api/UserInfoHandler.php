<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Nette\Http\Response;

class UserInfoHandler extends ApiHandler
{
    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $user = $data['token']->user;

        // required result
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
        ];

        // additional custom data added by authorizators for other sources
        if (isset($data['token']->authSource) && !empty($data['token']->authSource) && is_string($data['token']->authSource)) {
            $authSource = $data['token']->authSource;
            $result['source'] = $authSource;
            $result[$authSource] = $data['token']->$authSource;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
