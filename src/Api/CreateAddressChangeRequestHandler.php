<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateAddressChangeRequestHandler extends ApiHandler
{
    public function __construct(
        private AddressTypesRepository $addressTypesRepository,
        private AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private UserManager $userManager,
        private AddressesRepository $addressesRepository,
        private CountriesRepository $countriesRepository,
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
            (new PostInputParam('street')),
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
            user: $user,
            parentAddress: $parentAddress,
            firstName: $params['first_name'],
            lastName: $params['last_name'],
            companyName: $params['company_name'],
            street: $params['street'],
            number: $params['number'],
            city: $params['city'],
            zip: $params['zip'],
            countryId: $params['country_id'] ?? $country->id ?? $this->countriesRepository->defaultCountry()->id,
            companyId: $params['company_id'],
            companyTaxId: $params['company_tax_id'],
            companyVatId: $params['company_vat_id'],
            phoneNumber: $params['phone_number'],
            type: $params['type'],
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
