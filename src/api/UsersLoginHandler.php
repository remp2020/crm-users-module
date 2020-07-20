<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Repositories\DeviceAccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\Response;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;

class UsersLoginHandler extends ApiHandler
{
    private $userAuthenticator;

    private $accessTokensRepository;

    private $deviceTokensRepository;

    private $deviceAccessTokensRepository;

    private $translator;

    public function __construct(
        UserAuthenticator $userAuthenticator,
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        DeviceAccessTokensRepository $deviceAccessTokensRepository,
        ITranslator $translator
    ) {
        $this->userAuthenticator = $userAuthenticator;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->deviceAccessTokensRepository = $deviceAccessTokensRepository;
        $this->translator = $translator;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'password', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'device_token', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $params = $paramsProcessor->getValues();

        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        if (!$params['email']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'no_email', 'message' => 'No valid email']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        if (!$params['password']) {
            $response = new JsonResponse(['status' => 'error', 'error' => 'no_password', 'message' => 'No valid password']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $deviceToken = false;
        if (!empty($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonResponse([
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist'
                ]);
                $response->setHttpCode(Response::S400_BAD_REQUEST);
                return $response;
            }
        }

        try {
            $source = 'api';
            if ($params['source'] && $params['source'] != 'api') {
                $source .= '+' . $params['source'];
            }
            $identity = $this->userAuthenticator->authenticate([
                'username' => $params['email'],
                'password' => $params['password'],
                'source' => $source,
            ]);
        } catch (AuthenticationException $authException) {
            $message = $authException->getMessage();
            if ($authException->getCode() == UserAuthenticator::IDENTITY_NOT_FOUND) {
                $message = $this->translator->translate('users.api.users_login_handler.identity_not_found');
            } elseif ($authException->getCode() == UserAuthenticator::INVALID_CREDENTIAL) {
                $message = $this->translator->translate('users.api.users_login_handler.invalid_credentials');
            }
            $response = new JsonResponse(['status' => 'error', 'error' => 'auth_failed', 'message' => $message]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $identity->id,
                'email' => $identity->data['email'],
                'first_name' => $identity->data['first_name'],
                'last_name' => $identity->data['last_name'],
            ],
        ];

        if ($identity->getRoles()) {
            $result['user']['roles'] = $identity->getRoles();
        }

        $lastToken = $this->accessTokensRepository->allUserTokens($identity->id)->limit(1)->fetch();

        if ($lastToken && $deviceToken) {
            $this->deviceAccessTokensRepository->pairAccessToken($lastToken, $deviceToken);
        }

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
