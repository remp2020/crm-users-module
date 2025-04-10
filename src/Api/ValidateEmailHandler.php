<?php
declare(strict_types=1);

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Email\EmailValidator;
use League\Fractal\ScopeFactoryInterface;
use Nette\Http\IResponse;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ValidateEmailHandler extends ApiHandler
{
    public function __construct(
        private readonly EmailValidator $emailValidator,
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
        $email = $params['email'];
        if (!Validators::isEmail($email) || !$this->emailValidator->isValid($email)) {
            return new JsonApiResponse(
                IResponse::S400_BadRequest,
                [
                    'status' => 'error',
                    'message' => "Email '{$email}' is not valid",
                    'code' => 'invalid_email'
                ]
            );
        }

        return new JsonApiResponse(IResponse::S200_OK, [
            'status' => 'success',
        ]);
    }
}
