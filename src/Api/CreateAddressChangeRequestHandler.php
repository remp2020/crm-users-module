<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateAddressChangeRequestHandler extends ApiHandler
{
    public function __construct(
        private AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private AddressTypesRepository $addressTypesRepository,
        private UserManager $userManager,
        private AddressesRepository $addressesRepository,
        private CountriesRepository $countriesRepository
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
            (new PostInputParam('type'))->setRequired(),

            (new PostInputParam('first_name')),
            (new PostInputParam('last_name')),
            (new PostInputParam('company_name')),
            (new PostInputParam('address')),
            (new PostInputParam('number')),
            (new PostInputParam('zip')),
            (new PostInputParam('city')),

            // **Deprecated** and will be removed. Replaced with `country_iso`.
            (new PostInputParam('country_id')),

            (new PostInputParam('country_iso')),
            (new PostInputParam('phone_number')),
            (new PostInputParam('company_id')),
            (new PostInputParam('company_tax_id')),
            (new PostInputParam('company_vat_id')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->userManager->loadUserByEmail($params['email']);
        if (!$user) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'User not found']);
            return $response;
        }

        $type = $this->addressTypesRepository->findByType($params['type']);
        if (!$type) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Address type not found']);
            return $response;
        }

        $country = $this->countriesRepository->findByIsoCode($params['country_iso']);
        if (!$country) {
            $country = null;
            if (isset($params['country_iso'])) {
                $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Country not found']);
                return $response;
            }
        }

        $parentAddress = $this->addressesRepository->address($user, $params['type']);
        if (!$parentAddress) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => 'Parent address not found',
            ]);
            return $response;
        }

        $change = $this->addressChangeRequestsRepository->add(
            $user,
            $parentAddress,
            $params['first_name'],
            $params['last_name'],
            $params['company_name'],
            $params['address'],
            $params['number'],
            $params['city'],
            $params['zip'],
            $params['country_id'] ?? $country->id ?? $this->countriesRepository->defaultCountry()->id,
            $params['company_id'],
            $params['company_tax_id'],
            $params['company_vat_id'],
            $params['phone_number'],
            $params['type']
        );

        $response = new JsonApiResponse(Response::S200_OK, [
            'status' => 'ok',
            'address' => [
                'id' => $change->id,
            ],
        ]);
        return $response;
    }
}
