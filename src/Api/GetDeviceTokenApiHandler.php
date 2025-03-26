<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class GetDeviceTokenApiHandler extends ApiHandler
{
    private $accessTokensRepository;

    private $deviceTokensRepository;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('device_id'))->setRequired(),

            new PostInputParam('access_token'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $accessToken = null;
        if (isset($params['access_token'])) {
            $accessToken = $this->accessTokensRepository->loadToken($params['access_token']);
            if (!$accessToken) {
                $response = new JsonApiResponse(Response::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Access token not valid'
                ]);
                return $response;
            }
        }

        $deviceToken = $this->deviceTokensRepository->generate($params['device_id']);
        if ($accessToken) {
            $this->accessTokensRepository->pairWithDeviceToken($accessToken, $deviceToken);
        }

        $response = new JsonApiResponse(Response::S200_OK, [
            'device_token' => $deviceToken->token
        ]);
        return $response;
    }
}
