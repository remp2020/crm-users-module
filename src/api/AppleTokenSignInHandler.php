<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\IRow;
use Nette\Http\Response;

class AppleTokenSignInHandler extends ApiHandler
{
    private $appleSignIn;

    private $accessTokensRepository;

    private $usersRepository;

    public function __construct(
        AppleSignIn $appleSignIn,
        AccessTokensRepository $accessTokensRepository,
        UsersRepository $usersRepository
    ) {
        $this->appleSignIn = $appleSignIn;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->usersRepository = $usersRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'id_token', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'create_access_token', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization): ?JsonResponse
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'invalid_id_token',
                'message' => 'Wrong input - ' . $error
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();
        $idToken = $params['id_token'];
        $createAccessToken = filter_var($params['create_access_token'], FILTER_VALIDATE_BOOLEAN) ?? false;

        $user = $this->appleSignIn->signInUsingIdToken($idToken);

        if (!$user) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'error_verifying_id_token',
                'message' => 'Unable to verify ID token',
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $accessToken = null;
        if ($createAccessToken) {
            $accessToken = $this->accessTokensRepository->add($user, 3, AppleSignIn::ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO);
        }

        $result = $this->formatResponse($user, $accessToken);
        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function formatResponse(IRow $user, ?IRow $accessToken): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339),
                'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->format(\DateTimeInterface::RFC3339) : null,
            ],
        ];

        if ($accessToken) {
            $result['access']['token'] = $accessToken->token;
        }
        return $result;
    }
}
