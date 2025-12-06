<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Models\User\UserDataStorageInterface;
use Crm\UsersModule\Models\Auth\AccessTokensApiAuthorizationInterface;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserInfoHandler extends ApiHandler
{
    public function __construct(
        private readonly UserDataStorageInterface $userDataStorage,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            return new JsonApiResponse(IResponse::S403_Forbidden, [
                'status' => 'error',
                'error' => 'no_authorization',
                'message' => 'Cannot authorize user',
            ]);
        }

        $userData = null;
        if ($authorization instanceof AccessTokensApiAuthorizationInterface) {
            // Cache-based approach
            foreach ($authorization->getAccessTokens() as $accessToken) {
                $rawCacheData = $this->userDataStorage->load($accessToken->token);
                if ($rawCacheData) {
                    $cacheData = Json::decode($rawCacheData, true);

                    $userData['user'] = [
                        'id' => $cacheData['basic']['id'],
                        'email' => $cacheData['basic']['email'],
                        'uuid' => $cacheData['basic']['uuid'],
                        'confirmed_at' => $cacheData['basic']['confirmed_at']
                            ? (\DateTime::createFromFormat('U', $cacheData['basic']['confirmed_at']))
                                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                                ->format(DATE_RFC3339)
                            : null,
                    ];

                    $userData['user_meta'] = new \stdClass();
                    foreach ($cacheData['user_meta'] ?? [] as $userMetaArr) {
                        foreach ($userMetaArr as $key => $value) {
                            $userData['user_meta']->{$key} = $value;
                        }
                    }

                    break;
                }
            }
        }

        if (!$userData) {
            // DB-based approach
            $userRow = $data['token']->user;

            $userData['user'] = [
                'id' => $userRow->id,
                'uuid' => $userRow->uuid,
                'email' => $userRow->email,
                'confirmed_at' => $userRow->confirmed_at ? $userRow->confirmed_at->format(DATE_RFC3339) : null,
            ];
            $userData['user_meta'] = new \stdClass();

            foreach ($userRow->related('user_meta')->where('is_public', 1) as $userMeta) {
                $userData['user_meta']->{$userMeta->key} = $userMeta->value;
            }
        }

        // required result
        $result = [
            'status' => 'ok',
            'user' => $userData['user'],
            'user_meta' => $userData['user_meta'],
        ];

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
