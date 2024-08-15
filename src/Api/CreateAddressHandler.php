<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use League\Event\Emitter;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateAddressHandler extends ApiHandler
{
    public function __construct(
        private UserManager $userManager,
        private AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private AddressTypesRepository $addressTypesRepository,
        private CountriesRepository $countriesRepository,
        private Emitter $emitter,
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
            (new PostInputParam('address')),
            (new PostInputParam('number')),
            (new PostInputParam('zip')),
            (new PostInputParam('city')),
            (new PostInputParam('country_iso')),
            (new PostInputParam('company_name')),
            (new PostInputParam('company_id')),
            (new PostInputParam('tax_id')),
            (new PostInputParam('vat_id')),
            (new PostInputParam('phone_number')),
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
        if (isset($params['country_iso']) && !$country) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Country not found']);
            return $response;
        }

        $changeRequest = $this->addressChangeRequestsRepository->add(
            user: $user,
            parentAddress: null,
            firstName: $params['first_name'],
            lastName: $params['last_name'],
            companyName: $params['company_name'],
            address: $params['address'],
            number: $params['number'],
            city: $params['city'],
            zip: $params['zip'],
            countryId: $country->id ?? $this->countriesRepository->defaultCountry()->id,
            companyId: $params['company_id'],
            companyTaxId: $params['tax_id'],
            companyVatId: $params['vat_id'],
            phoneNumber: $params['phone_number'],
            type: $params['type']
        );
        $address = $this->addressChangeRequestsRepository->acceptRequest($changeRequest);

        if ($address) {
            $this->emitter->emit(new NewAddressEvent($address));
            $result = [
                'status' => 'ok',
                'address' => [
                    'id' => $address->id,
                ],
            ];
            $response = new JsonApiResponse(Response::S200_OK, $result);
        } else {
            $result = [
                'status' => 'error',
                'message' => 'Cannot create address',
            ];
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, $result);
        }

        return $response;
    }
}
