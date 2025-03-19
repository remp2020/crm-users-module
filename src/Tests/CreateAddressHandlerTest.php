<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\UsersModule\Api\CreateAddressHandler;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

///**
// * @runTestsInSeparateProcesses
// */
class CreateAddressHandlerTest extends BaseTestCase
{
    use ApiTestTrait;

    private CreateAddressHandler $handler;
    private AddressTypesRepository $addressTypesRepository;

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
            UsersSeeder::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(CreateAddressHandler::class);
        $this->addressTypesRepository = $this->getRepository(AddressTypesRepository::class);

        $this->addressTypesRepository->add('test', 'Test');

        unset($_POST);
    }

    public function testRequiredMissing()
    {
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testUserNotFound()
    {
        $_POST['email'] = '0test@user.site';
        $_POST['type'] = 'test';

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('User not found', $payload['message']);
    }

    public function testTypeNotFound()
    {
        $_POST['email'] = UsersSeeder::USER_ADMIN;
        $_POST['type'] = '@test';

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Address type not found', $payload['message']);
    }

    public function testCountryNotFound()
    {
        $_POST['email'] = UsersSeeder::USER_ADMIN;
        $_POST['type'] = 'test';

        $_POST['country_iso'] = 'QQQ';

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Country not found', $payload['message']);
    }

    public function testValid()
    {
        $_POST['email'] = UsersSeeder::USER_ADMIN;
        $_POST['type'] = 'test';

        $_POST['street'] = 'Vysoka';
        $_POST['city'] = 'Poprad';
        $_POST['zip'] = '98745';
        $_POST['country_iso'] = 'AU';

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
    }
}
