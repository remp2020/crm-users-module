<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Auth\UserAuthenticator;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Nette\Http\Response;
use Nette\Security\AuthenticationException;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class AutoLoginTokenLoginApiHandler extends ApiHandler
{
    public function __construct(
        private AccessTokensRepository $accessTokensRepository,
        private UserAuthenticator $userAuthenticator,
        private DeviceTokensRepository $deviceTokensRepository
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('autologin_token'))->setRequired(),
            (new PostInputParam('device_token')),
            (new PostInputParam('source')),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        $deviceToken = null;
        if (isset($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                    'status' => 'error',
                    'error' => 'invalid_device_token',
                    'message' => "device token doesn't exist: ". $params['device_token']
                ]);
                return $response;
            }
        }

        try {
            $source = 'api';
            if (isset($params['source']) && $params['source'] !== 'api') {
                $source .= '+' . $params['source'];
            }
            $identity = $this->userAuthenticator->authenticate([
                'autologin_token' => $params['autologin_token'],
                'source' => $source,
            ]);
        } catch (AuthenticationException $e) {
            $responseCode = Response::S401_UNAUTHORIZED;
            if ($e->getCode() === UserAuthenticator::NOT_APPROVED) {
                $responseCode = Response::S403_FORBIDDEN;
            }

            $response = new JsonApiResponse($responseCode, [
                'status' => 'error',
                'error' => 'auth_failed',
                'message' => $e->getMessage()
            ]);
            return $response;
        }

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $identity->id,
                'email' => $identity->data['email'],
                'confirmed_at' => $identity->data['confirmed_at'] ? $identity->data['confirmed_at']->format(DATE_RFC3339) : null,
                'public_name' => $identity->data['public_name'],
                'first_name' => $identity->data['first_name'],
                'last_name' => $identity->data['last_name'],
            ],
        ];

        if ($identity->getRoles()) {
            $result['user']['roles'] = $identity->getRoles();
        }

        $lastToken = $this->accessTokensRepository->allUserTokens($identity->id)->limit(1)->fetch();
        if (!$lastToken) {
            Debugger::log('Missing access token for user', ILogger::ERROR);
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, [
                'status' => 'error',
                'error' => 'missing_access_token',
                'message' => 'Missing access token for user'
            ]);
            return $response;
        }

        if ($deviceToken) {
            $this->accessTokensRepository->pairWithDeviceToken($lastToken, $deviceToken);
        }

        $result['access']['token'] = $lastToken->token;

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
