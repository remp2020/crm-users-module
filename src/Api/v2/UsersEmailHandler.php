<?php

namespace Crm\UsersModule\Api\v2;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Auth\Rate\RateLimitException;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Email\EmailValidator;
use Nette\Http\IResponse;
use Nette\Security\AuthenticationException;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersEmailHandler extends ApiHandler
{
    private UserManager $userManager;

    private EmailValidator $emailValidator;

    private UsersAuthenticator $usersAuthenticator;

    public function __construct(
        UserManager $userManager,
        EmailValidator $emailValidator,
        UsersAuthenticator $usersAuthenticator
    ) {
        $this->userManager = $userManager;
        $this->emailValidator = $emailValidator;
        $this->usersAuthenticator = $usersAuthenticator;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
            new PostInputParam('password'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        if (strlen($params['email']) > 255) {
            return new JsonApiResponse(
                IResponse::S422_UNPROCESSABLE_ENTITY,
                [
                    'status' => 'error',
                    'message' => 'Invalid email format',
                    'code' => 'invalid_email'
                ]
            );
        }

        $passwordStatus = null;
        $responseCode = IResponse::S200_OK;
        $user = $this->userManager->loadUserByEmail($params['email']);
        try {
            $this->usersAuthenticator->setCredentials([
                'username' => $params['email'],
                'password' => $params['password'] ?? ''
            ]);
            $this->usersAuthenticator->authenticate();
            $status = 'taken';
            $passwordStatus = true;
        } catch (RateLimitException $e) {
            $response = new JsonApiResponse(
                IResponse::S429_TOO_MANY_REQUESTS,
                [
                    'status' => 'error',
                    'message' => 'Rate limit exceeded',
                    'code' => 'rate_limit_exceeded'
                ]
            );
            return $response;
        } catch (AuthenticationException $authException) {
            if ($authException->getCode() === UserAuthenticator::IDENTITY_NOT_FOUND) {
                $status = 'available';

                // Validate email format only if user email does not exist in our DB, since external services may be slow
                if (!Validators::isEmail($params['email']) || !$this->emailValidator->isValid($params['email'])) {
                    $response = new JsonApiResponse(
                        IResponse::S422_UNPROCESSABLE_ENTITY,
                        [
                            'status' => 'error',
                            'message' => 'Invalid email format',
                            'code' => 'invalid_email'
                        ]
                    );
                    return $response;
                }
            } elseif ($authException->getCode() === UserAuthenticator::INVALID_CREDENTIAL) {
                $status = 'taken';
                $passwordStatus = isset($params['password']) ? false : null;
                if ($passwordStatus === false) {
                    $responseCode = IResponse::S401_UNAUTHORIZED;
                }
            } elseif ($authException->getCode() ===  UserAuthenticator::NOT_APPROVED) {
                $user = null;
                $status = 'available';
                $responseCode = IResponse::S200_OK;
            } else {
                $status = 'taken';
            }
        }

        $result = [
            'email' => $params['email'],
            'id' => $user->id ?? null,
            'status' => $status,
            'password' => $passwordStatus,
        ];

        $response = new JsonApiResponse($responseCode, $result);
        return $response;
    }
}
