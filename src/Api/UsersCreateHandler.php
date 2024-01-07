<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\ApiParamsValidatorInterface;
use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\Rate\RegistrationIpRateLimit;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\RegistrationAttemptsRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersCreateHandler extends ApiHandler implements ApiParamsValidatorInterface
{
    public function __construct(
        private UserManager $userManager,
        private AccessTokensRepository $accessTokensRepository,
        private DeviceTokensRepository $deviceTokensRepository,
        private UsersRepository $usersRepository,
        private UnclaimedUser $unclaimedUser,
        private RegistrationIpRateLimit $registrationIpRateLimit,
        private RegistrationAttemptsRepository $registrationAttemptsRepository
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
            new PostInputParam('password'),
            new PostInputParam('first_name'),
            new PostInputParam('last_name'),
            new PostInputParam('ext_id'),
            new PostInputParam('source'),
            new PostInputParam('referer'),
            new PostInputParam('note'),
            new PostInputParam('send_email'),
            new PostInputParam('disable_email_validation'),
            new PostInputParam('device_token'),
            new PostInputParam('unclaimed'),
            new PostInputParam('newsletters_subscribe'),
            new PostInputParam('locale'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        $authorization = $this->getAuthorization();
        if ($authorization instanceof NoAuthorization) {
            if ($this->registrationIpRateLimit->reachLimit(Request::getIp())) {
                $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_RATE_LIMIT_EXCEEDED);

                $response = new JsonApiResponse(IResponse::S429_TOO_MANY_REQUESTS, ['status' => 'error', 'message' => 'Limit reached', 'code' => 'limit_reached']);
                return $response;
            }
        }

        $unclaimed = filter_var($params['unclaimed'] ?? null, FILTER_VALIDATE_BOOLEAN);

        $email = $params['email'];
        $user = $this->userManager->loadUserByEmail($email) ?: null;

        // if user found allow only unclaimed user to get registered
        if ($user && ($unclaimed || !$this->unclaimedUser->isUnclaimedUser($user))) {
            $this->addAttempt($params['email'], null, $params['source'] ?? null, RegistrationAttemptsRepository::STATUS_TAKEN_EMAIL);
            $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            return $response;
        }

        $source = 'api';
        if (isset($params['source']) && strlen($params['source']) > 0) {
            $source = $params['source'];
        }

        $referer = null;
        if (isset($params['referer']) && $params['referer']) {
            $referer = $params['referer'];
        }

        $sendEmail = true;
        if (isset($params['send_email'])) {
            $sendEmail = filter_var($params['send_email'], FILTER_VALIDATE_BOOLEAN);
        }

        $checkEmail = true;
        if (isset($params['disable_email_validation']) && ($params['disable_email_validation'] == '1' || $params['disable_email_validation'] == 'true')) {
            $checkEmail = false;
        }

        $locale = $params['locale'] ?? null;

        $deviceToken = null;
        if (!empty($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $this->addAttempt($params['email'], $user, $source, RegistrationAttemptsRepository::STATUS_DEVICE_TOKEN_NOT_FOUND);
                $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist'
                ]);
                return $response;
            }
        }

        $password = $params['password'] ?? null;

        $meta = $this->processMeta($params);

        try {
            if ($user) {
                $user = $this->unclaimedUser->makeUnclaimedUserRegistered($user, $sendEmail, $source, $referer, $password);
            } elseif ($unclaimed) {
                $user = $this->unclaimedUser->createUnclaimedUser($email, $source, $locale);
            } else {
                $user = $this->userManager->addNewUser($email, $sendEmail, $source, $referer, $checkEmail, $password, true, $meta, true, $locale);
            }
        } catch (InvalidEmailException $e) {
            $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_INVALID_EMAIL);
            $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            return $response;
        } catch (UserAlreadyExistsException $e) {
            $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_TAKEN_EMAIL);
            $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            return $response;
        }

        $userData = [];
        if (!empty($params['first_name'])) {
            $userData['first_name'] = $params['first_name'];
        }

        if (!empty($params['last_name'])) {
            $userData['last_name'] = $params['last_name'];
        }

        if (!empty($params['ext_id'])) {
            $userData['ext_id'] = (int)$params['ext_id'];
        }

        if (!empty($params['note'])) {
            $userData['note'] = $params['note'];
        }

        $this->usersRepository->update($user, $userData);

        $lastToken = $this->accessTokensRepository->allUserTokens($user->id)->limit(1)->fetch() ?: null;
        if ($lastToken && $deviceToken) {
            $this->accessTokensRepository->pairWithDeviceToken($lastToken, $deviceToken);
        }

        $this->addAttempt($params['email'], $user, $params['source'] ?? null, RegistrationAttemptsRepository::STATUS_OK);
        $result = $this->formatResponse($user, $lastToken);

        $response = new JsonApiResponse(IResponse::S200_OK, $result);
        return $response;
    }

    private function formatResponse(ActiveRow $user, ?ActiveRow $lastToken): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->format(DATE_RFC3339) : null,
            ],
        ];

        if ($user->ext_id) {
            $result['user']['ext_id'] = $user->ext_id;
        }

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }
        return $result;
    }

    private function addAttempt($email, $user, $source, $status): void
    {
        $this->registrationAttemptsRepository->insertAttempt(
            $email,
            $user,
            $source,
            $status,
            Request::getIp(),
            Request::getUserAgent(),
            new \DateTime()
        );
    }

    private function processMeta($params): array
    {
        $newslettersSubscribe = filter_var($params['newsletters_subscribe'] ?? null, FILTER_VALIDATE_BOOLEAN);

        return array_filter([
            'newsletters_subscribe' => $newslettersSubscribe
        ]);
    }

    public function validateParams(array $params): ?ResponseInterface
    {
        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        $email = $params['email'];
        if (!$email) {
            return new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
        }
        if (!Validators::isEmail($email)) {
            $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_INVALID_EMAIL);
            return new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
        }

        return null;
    }
}
