<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class AddressesHandler extends ApiHandler
{
    public function __construct(
        private UserManager $userManager,
        private AddressesRepository $addressesRepository,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('email'))->setRequired(),
            (new GetInputParam('type')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->userManager->loadUserByEmail($params['email']);
        if (!$user) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "user doesn't exist: {$params['email']}"]);
            return $response;
        }

        $type = null;
        if ($params['type']) {
            $type = $params['type'];
        }

        $addresses = $this->addressesRepository->addresses($user, $type);
        $addressesArray = [];
        foreach ($addresses as $address) {
            $addressesArray[] = [
                'user_id' => $user->id,
                'type' => $address->type,
                'created_at' => $address->created_at->format('c'),
                'email' => $user->email,
                'company_name' => $address->company_name,
                'phone_number' => $address->phone_number,
                'company_id' => $address->company_id,
                'tax_id' => $address->company_tax_id,
                'vat_id' => $address->company_vat_id,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'street' => $address->street,
                'number' => $address->number,
                'zip' => $address->zip,
                'city' => $address->city,
                'country' => $address->country ? $address->country->name : null,
            ];
        }

        $result = [
            'status' => 'ok',
            'addresses' => $addressesArray,
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
