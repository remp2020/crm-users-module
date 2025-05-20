<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Models\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class AppleTokenSignInHandler extends ApiHandler
{
    public function __construct(
        private AppleSignIn $appleSignIn,
        private AccessTokensRepository $accessTokensRepository,
        private DeviceTokensRepository $deviceTokensRepository,
        private UsersRepository $usersRepository,
        private Emitter $emitter,
        private \Tomaj\Hermes\Emitter $hermesEmitter,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('id_token'))->setRequired(),
            new PostInputParam('create_access_token'),
            new PostInputParam('device_token'),
            new PostInputParam('locale'),
            new PostInputParam('source'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $idToken = $params['id_token'];
        $createAccessToken = filter_var($params['create_access_token'], FILTER_VALIDATE_BOOLEAN);

        $deviceToken = null;
        if (!empty($params['device_token'])) {
            if (!$createAccessToken) {
                $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'code' => 'no_access_token_to_pair_device_token',
                    'message' => 'There is no access token to pair with device token. Set parameter "create_access_token=true" in your request payload.',
                ]);
                return $response;
            }

            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, [
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist',
                ]);
                return $response;
            }
        }

        $user = $this->appleSignIn->getUserUsingIdToken($idToken, $params['locale'] ?? null);

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
            $this->addLoginAttempt(
                user: $user,
                source: $params['source'] ?? AppleSignIn::ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO,
                browserId: $deviceToken->device_id ?? null,
            );
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
                'uuid' => $user->uuid,
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

    private function addLoginAttempt(ActiveRow $user, string $source, ?string $browserId)
    {
        $date = new \DateTime();
        $this->emitter->emit(new LoginAttemptEvent(
            $user->email,
            $user,
            $source,
            LoginAttemptsRepository::STATUS_API_OK,
            $date,
        ));
        $this->hermesEmitter->emit(new HermesMessage(
            'login-attempt',
            [
                'status' => LoginAttemptsRepository::STATUS_API_OK,
                'source' => $source,
                'date' => $date->getTimestamp(),
                'browser_id' => $browserId,
                'user_id' => $user->id,
            ],
        ), HermesMessage::PRIORITY_DEFAULT);
    }
}
