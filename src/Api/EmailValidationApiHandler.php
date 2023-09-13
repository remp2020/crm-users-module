<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EmailValidationApiHandler extends ApiHandler
{
    private $action = 'validate';

    public function __construct(
        private Request $request,
        private UsersRepository $usersRepository,
        private UnclaimedUser $unclaimedUser
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
        ];
    }

    public function setAction(string $action)
    {
        $this->action = $action;
    }


    public function handle(array $params): ResponseInterface
    {
        if (!Validators::isEmail($params['email'])) {
            $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                'status' => 'error',
                'message' => 'Email is not valid',
                'code' => 'invalid_param',
            ]);
            return $response;
        }

        $user = $this->usersRepository->getByEmail($params['email']);
        if (!$user || $this->unclaimedUser->isUnclaimedUser($user)) {
            $result = [
                'status'  => 'error',
                'message' => 'Email isn\'t assigned to any user',
                'code'    => 'email_not_found',
            ];
            $response = new JsonApiResponse(IResponse::S404_NotFound, $result);
            return $response;
        }

        $action = $this->getAction();
        if ($action === 'validate') {
            $this->usersRepository->setEmailValidated($user, new \DateTime());
            $message = 'Email has been validated';
        } elseif ($action === 'invalidate') {
            $this->usersRepository->setEmailInvalidated($user);
            $message = 'Email has been invalidated';
        } else {
            throw new \Exception('invalid action resolved: ' . $action);
        }

        $result = [
            'status'  => 'ok',
            'message' => $message,
            'code'    => 'success',
        ];

        $response = new JsonApiResponse(IResponse::S200_OK, $result);

        return $response;
    }

    private function getAction(): string
    {
        if (isset($this->action)) {
            return $this->action;
        }
        if (str_contains($this->request->getUrl()->getPath(), "invalidate")) {
            return 'invalidate';
        }
        return 'validate';
    }
}
