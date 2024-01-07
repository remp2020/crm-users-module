<?php

namespace Crm\UsersModule\Api;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Response\EmptyResponse;
use Crm\ApplicationModule\User\DeleteUserData;
use Crm\UsersModule\Auth\UsersApiAuthorizationInterface;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class DeleteUserApiHandler extends ApiHandler
{
    private $deleteUserData;

    private $translator;

    public function __construct(
        DeleteUserData $deleteUserData,
        Translator  $translator
    ) {
        $this->deleteUserData = $deleteUserData;
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!($authorization instanceof UsersApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'UsersApiAuthorizationInterface'");
        }

        $authorizedUsers = $authorization->getAuthorizedUsers();
        $authorizedUser = reset($authorizedUsers);
        [$canBeDeleted, $errors] = $this->deleteUserData->canBeDeleted($authorizedUser->id);
        if (!$canBeDeleted) {
            $response = new JsonApiResponse(IResponse::S403_FORBIDDEN, [
                'status' => "error",
                'code' => "user_delete_protected",
                'message' => 'Unable to delete user due to system protection configuration',
                'reason' => $this->translator->translate('users.frontend.settings.account_delete.cannot_delete'),
            ]);
            return $response;
        }

        $this->deleteUserData->deleteData($authorizedUser->id);
        $response = new EmptyResponse();
        $response->setCode(IResponse::S204_NO_CONTENT);
        return $response;
    }
}
