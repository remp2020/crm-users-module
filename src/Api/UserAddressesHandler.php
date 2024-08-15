<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Auth\UsersApiAuthorizationInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\ViewObjects\Address;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserAddressesHandler extends ApiHandler
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            new GetInputParam('type'),
        ];
    }

    public function getOutputAddresses(ActiveRow $user, ?string $type = null): array
    {
        $addressesArray = [];
        foreach ($this->addressesRepository->userAddresses($user, $type) as $row) {
            $address = Address::fromActiveRow($row);
            $addressesArray[$row->id] = [
                ...$address->toArray(),
                'address_string' => $address->formatSimple(),
            ];
        }
        return $addressesArray;
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!$authorization instanceof UsersApiAuthorizationInterface) {
            throw new \UnexpectedValueException("Invalid authorization used for the API, it needs to implement 'UsersApiAuthorizationInterface': " . get_class($authorization));
        }

        $users = $authorization->getAuthorizedUsers();
        if (count($users) !== 1) {
            throw new \UnexpectedValueException('Incorrect number of authorized users, expected 1 but got ' . count($users));
        }
        /** @var ActiveRow $user */
        $user = reset($users);

        return new JsonApiResponse(IResponse::S200_OK, [
            'status' => 'ok',
            'addresses' => $this->getOutputAddresses($user, $params['type'] ?? null),
        ]);
    }
}
