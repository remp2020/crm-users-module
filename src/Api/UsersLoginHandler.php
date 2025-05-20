<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\ApiParamsValidatorInterface;
use Crm\UsersModule\Models\Auth\UserAuthenticator;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersLoginHandler extends ApiHandler implements ApiParamsValidatorInterface
{
    private UsersRepository $usersRepository;

    private UserAuthenticator $userAuthenticator;

    private AccessTokensRepository $accessTokensRepository;

    private DeviceTokensRepository $deviceTokensRepository;

    private Translator $translator;

    public function __construct(
        UsersRepository $usersRepository,
        UserAuthenticator $userAuthenticator,
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        Translator $translator,
    ) {
        parent::__construct();

        $this->usersRepository = $usersRepository;
        $this->userAuthenticator = $userAuthenticator;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
            (new PostInputParam('password'))->setRequired(),
            (new PostInputParam('source')),
            (new PostInputParam('device_token')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        // TODO: This is handled in ApiPresenter. Remove this once tests validate the params before calling the handler.
        $response = $this->validateParams($params);
        if ($response) {
            return $response;
        }

        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        $deviceToken = false;
        if (!empty($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist',
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
                'username' => $params['email'],
                'password' => $params['password'],
                'source' => $source,
            ]);
        } catch (AuthenticationException $authException) {
            $message = $authException->getMessage();
            $code = 'auth_failed';
            if (in_array($authException->getCode(), [UserAuthenticator::IDENTITY_NOT_FOUND, UserAuthenticator::NOT_APPROVED], true)) {
                $message = $this->translator->translate('users.api.users_login_handler.identity_not_found');
                $code = 'identity_not_found';
            } elseif ($authException->getCode() === UserAuthenticator::INVALID_CREDENTIAL) {
                $message = $this->translator->translate('users.api.users_login_handler.invalid_credentials');
                $code = 'invalid_credential';
            }
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'error' => 'auth_failed', 'message' => $message, 'code' => $code]);
            return $response;
        }

        $user = $this->usersRepository->find($identity->getId());

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'confirmed_at' => $user->confirmed_at?->format(\DateTimeInterface::RFC3339),
            ],
            'user_meta' => new \stdClass(),
        ];

        $userMetaData = $user->related('user_meta')
            ->where('is_public', 1)
            ->fetchPairs('key', 'value');
        if (count($userMetaData)) {
            $result['user_meta'] = $userMetaData;
        }

        if ($identity->getRoles()) {
            $result['user']['roles'] = $identity->getRoles();
        }

        $lastToken = $this->accessTokensRepository->allUserTokens($identity->id)->limit(1)->fetch();

        if ($lastToken && $deviceToken) {
            $this->accessTokensRepository->pairWithDeviceToken($lastToken, $deviceToken);
        }

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }

    public function validateParams(array $params): ?ResponseInterface
    {
        if (!isset($params['email'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'error' => 'no_email', 'message' => 'No valid email', 'code' => 'invalid_email']);
            return $response;
        }

        if (!isset($params['password'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'error' => 'no_password', 'message' => 'No valid password', 'code' => 'invalid_password']);
            return $response;
        }

        return null;
    }
}
