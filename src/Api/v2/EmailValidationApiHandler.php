<?php

namespace Crm\UsersModule\Api\v2;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use DateTime;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EmailValidationApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    // It would be preferable to replace these with an enum.
    // But that would take a whole new file since the sniffer
    // complains (we only allow one class per file).
    public const VALIDATE = 1;
    public const INVALIDATE = 2;
    private int $action;

    public function __construct(
        private Request $request,
        private UsersRepository $usersRepository,
        private UnclaimedUser $unclaimedUser,
        private UserMetaRepository $userMetaRepository
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        // JsonInputParam input can't be mocked, and therefore any API that uses it can't be tested.
        //return [new JsonInputParam('emails', __DIR__ . '/email-validation-api-handler.schema.json')]
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $result = $this->validateInput(
            __DIR__ . '/email-validation-api-handler.schema.json',
            $this->rawPayload()
        );
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $action = $this->getAction();

        $validEmails = array_filter($json->emails, function ($email) {
            return Validators::isEmail($email);
        });

        match ($action) {
            EmailValidationApiHandler::VALIDATE => $this->validateEmails($validEmails),
            EmailValidationApiHandler::INVALIDATE => $this->invalidateEmails($validEmails),
        };

        //TODO:
        // Improve error handling. Nothing currently consumes api responses so it's been purposefully left open to
        // extension for future implementations. As a note for anybody trying to figure this out in the future,
        // you should investigate the implications of using the 207 status code.
        return new JsonApiResponse(IResponse::S200_OK, ["status" => "ok"]);
    }

    private function validateEmails(array $emails): void
    {
        // Preferably, all this code would be just a chain of repository method calls
        // that build a single query we can use without wrapping everything in a transaction.
        // This isn't possible as most repository methods evaluate their queries eagerly.
        // Which can result in cases where a table can be accessed redundantly.
        // For example, the naive implementation of this function would:
        // * Search the userRepository for every user_id belonging to addresses in the $emails array.
        // * Search the userMetaRepository for unclaimed users to filter out.
        // * Update the userRepository with all the validated users.
        //
        // Each step executing a new query just so the php script can send the data back to the database
        // for more work.

        $unclaimed_users = $this->userMetaRepository->getTable()
            ->where('key', $this->unclaimedUser::META_KEY)
            ->select('user_id');

        $this->usersRepository->getTable()
            ->where('email', $emails)
            ->where('id NOT', $unclaimed_users)
            ->where(['email_validated_at' => null])
            ->update(['email_validated_at' => new DateTime() /* Now */]);
    }

    private function invalidateEmails(array $emails): void
    {
        // Same reasoning as was used in `validateEmails`

        $unclaimed_users = $this->userMetaRepository->getTable()
            ->where(['key' => $this->unclaimedUser::META_KEY])
            ->select('user_id');

        $this->usersRepository->getTable()
            ->where('email', $emails)
            ->where('id NOT', $unclaimed_users)
            ->update(['email_validated_at' => null]);
    }

    public function setAction(int $action)
    {
        assert($action === $this::VALIDATE || $action === $this::INVALIDATE);

        $this->action = $action;
    }

    private function getAction(): int
    {
        if (isset($this->action)) {
            return $this->action;
        }
        if (str_contains($this->request->getUrl()->getPath(), "invalidate")) {
            return EmailValidationApiHandler::INVALIDATE;
        }

        return EmailValidationApiHandler::VALIDATE;
    }
}
