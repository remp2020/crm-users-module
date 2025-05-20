<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Models\Email\EmailValidator;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use League\Fractal\ScopeFactoryInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Security\Passwords;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersUpdateHandler extends ApiHandler
{
    private $usersRepository;

    private $emailValidator;

    private $changePasswordsLogsRepository;

    private $emitter;

    private $passwords;

    public function __construct(
        UsersRepository $usersRepository,
        EmailValidator $emailValidator,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        Emitter $emitter,
        Passwords $passwords,
        ScopeFactoryInterface $scopeFactory = null,
    ) {
        parent::__construct($scopeFactory);
        $this->usersRepository = $usersRepository;
        $this->emailValidator = $emailValidator;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->emitter = $emitter;
        $this->passwords = $passwords;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('user_id'))->setRequired(),
            new PostInputParam('email'),
            new PostInputParam('password'),
            new PostInputParam('ext_id'),
            new PostInputParam('disable_email_validation'),
            new PostInputParam('locale'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $userId = $params['user_id'];
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            return new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'User not found', 'code' => 'user_not_found']);
        }

        $userData = [];
        if (!empty($params['email'])) {
            $checkEmail = true;
            if (filter_var($params['disable_email_validation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $checkEmail = false;
            }

            if ($checkEmail && !$this->emailValidator->isValid($params['email'])) {
                return new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            }

            $userData['email'] = $params['email'];
            if ($user->email === $user->public_name) {
                $userData['public_name'] = $params['email'];
            }
        }

        if (!empty($params['ext_id'])) {
            $userData['ext_id'] = (int)$params['ext_id'];
        }

        if (!empty($params['locale'])) {
            $userData['locale'] = $params['locale'];
        }

        $passwordChanged = false;
        $hashedPassword = null;
        $password = null;
        if (!empty($params['password'])) {
            $password = $params['password'];
            $passwordChanged = !$this->passwords->verify($password, $user->password);
            if ($passwordChanged) {
                $hashedPassword = $this->passwords->hash($password);
                $userData['password'] = $hashedPassword;
            }
        }

        $oldPassword = $user->password;
        $this->usersRepository->update($user, $userData);

        if ($passwordChanged) {
            $this->changePasswordsLogsRepository->add(
                $user,
                ChangePasswordsLogsRepository::TYPE_CHANGE,
                $oldPassword,
                $hashedPassword,
            );

            $this->emitter->emit(new UserChangePasswordEvent($user, $password));
        }

        $result = $this->formatResponse($user);

        return new JsonApiResponse(Response::S200_OK, $result);
    }

    private function formatResponse(ActiveRow $user): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->format(DATE_RFC3339) : null,
            ],
        ];

        if ($user->ext_id) {
            $result['user']['ext_id'] = $user->ext_id;
        }

        return $result;
    }
}
