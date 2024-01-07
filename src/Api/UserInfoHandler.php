<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserInfoHandler extends ApiHandler
{
    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, ['status' => 'error', 'message' => 'Cannot authorize user']);
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
                'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->format(DATE_RFC3339) : null,
            ],
            'user_meta' => new \stdClass(),
        ];

        $userMetaData = $user->related('user_meta')->where('is_public', 1);
        foreach ($userMetaData as $userMeta) {
            $result['user_meta']->{$userMeta->key} = $userMeta->value;
        }

        // additional custom data added by authorizators for other sources
        if (isset($data['token']->authSource) && !empty($data['token']->authSource) && is_string($data['token']->authSource)) {
            $authSource = $data['token']->authSource;
            $result['source'] = $authSource;
            $result[$authSource] = $data['token']->$authSource;
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
