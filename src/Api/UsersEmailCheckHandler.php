<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Fractal\ScopeFactoryInterface;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersEmailCheckHandler extends ApiHandler
{
    public const STATUS_TAKEN = 'taken';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ERROR = 'error';

    public function __construct(
        private UsersRepository $usersRepository,
        private UnclaimedUser $unclaimedUser,
        ScopeFactoryInterface $scopeFactory = null
    ) {
        parent::__construct($scopeFactory);
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired()
        ];
    }

    public function handle(array $params): JsonApiResponse
    {
        if (strlen($params['email']) > 255) {
            return new JsonApiResponse(
                IResponse::S422_UnprocessableEntity,
                [
                    'status' => self::STATUS_ERROR,
                    'message' => 'Invalid email format',
                    'code' => 'invalid_email'
                ]
            );
        }

        $user = $this->usersRepository->getByEmail($params['email']);
        $unclaimed = $user && $this->unclaimedUser->isUnclaimedUser($user);
        $taken = $user && !$unclaimed;

        $result = [
            'email' => $params['email'],
            'status' => $taken ? self::STATUS_TAKEN : self::STATUS_AVAILABLE
        ];
        if ($taken) {
            $result['id'] = $user->id;
        }

        return new JsonApiResponse(IResponse::S200_OK, $result);
    }
}
