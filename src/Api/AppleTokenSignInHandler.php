<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class AppleTokenSignInHandler extends ApiHandler
{
    private AppleSignIn $appleSignIn;

    private AccessTokensRepository $accessTokensRepository;

    private DeviceTokensRepository $deviceTokensRepository;

    private UsersRepository $usersRepository;

    public function __construct(
        AppleSignIn $appleSignIn,
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        UsersRepository $usersRepository
    ) {
        parent::__construct();

        $this->appleSignIn = $appleSignIn;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->usersRepository = $usersRepository;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('id_token'))->setRequired(),
            new PostInputParam('create_access_token'),
            new PostInputParam('device_token'),
            new PostInputParam('locale'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $idToken = $params['id_token'];
        $createAccessToken = filter_var($params['create_access_token'], FILTER_VALIDATE_BOOLEAN) ?? false;

        $deviceToken = null;
        if (!empty($params['device_token'])) {
            if (!$createAccessToken) {
                $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'code' => 'no_access_token_to_pair_device_token',
                    'message' => 'There is no access token to pair with device token. Set parameter "create_access_token=true" in your request payload.'
                ]);
                return $response;
            }

            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, [
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist'
                ]);
                return $response;
            }
        }

        $user = $this->appleSignIn->signInUsingIdToken($idToken, $params['locale'] ?? null);

        if (!$user) {
            $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                'status' => 'error',
                'code' => 'error_verifying_id_token',
                'message' => 'Unable to verify ID token',
            ]);
            return $response;
        }

        $accessToken = null;
        if ($createAccessToken) {
            $accessToken = $this->accessTokensRepository->add($user, 3, AppleSignIn::ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO);
            if ($deviceToken) {
                $this->accessTokensRepository->pairWithDeviceToken($accessToken, $deviceToken);
            }
        }

        $result = $this->formatResponse($user, $accessToken);
        $response = new JsonApiResponse(IResponse::S200_OK, $result);
        return $response;
    }

    private function formatResponse(ActiveRow $user, ?ActiveRow $accessToken): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339),
                'confirmed_at' => $user->confirmed_at?->format(\DateTimeInterface::RFC3339),
            ],
            'user_meta' => new \stdClass(),
        ];

        if ($accessToken) {
            $result['access']['token'] = $accessToken->token;
        }

        $userMetaData = $user->related('user_meta')
            ->where('is_public', 1)
            ->fetchPairs('key', 'value');
        if (count($userMetaData)) {
            $result['user_meta'] = $userMetaData;
        }

        return $result;
    }
}
