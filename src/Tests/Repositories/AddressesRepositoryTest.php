<?php

declare(strict_types=1);

namespace Crm\UsersModule\Tests\Repositories;

use Crm\UsersModule\Events\AddressRemovedEvent;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\AddressTypesTestSeeder;
use Crm\UsersModule\Tests\BaseTestCase;
use League\Event\AbstractListener;
use League\Event\Emitter;
use Mockery;

class AddressesRepositoryTest extends BaseTestCase
{
    private AddressesRepository $addressesRepository;
    private Emitter $emitter;

    protected function requiredSeeders(): array
    {
        return [
            ...parent::requiredSeeders(),
            AddressTypesTestSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->emitter = $this->inject(Emitter::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->emitter->removeAllListeners(AddressRemovedEvent::class);
    }

    public function testSoftDeleteEmitsAddressRemovedEvent(): void
    {
        $user = $this->getRepository(UsersRepository::class)->add('user@example.com', 'secret123');
        $address = $this->addressesRepository->add(
            user: $user,
            type: AddressTypesTestSeeder::ADDRESS_TYPE,
            firstName: null,
            lastName: null,
            street: null,
            number: null,
            city: null,
            zip: null,
            countryId: null,
            phoneNumber: null,
        );

        $removedEvent = null;
        $listener = Mockery::mock(AbstractListener::class)
            ->shouldReceive('handle')
            ->withArgs(function (AddressRemovedEvent $event) use (&$removedEvent) {
                $removedEvent = $event;
                return true;
            })
            ->getMock();
        $this->emitter->addListener(AddressRemovedEvent::class, $listener);

        $this->addressesRepository->softDelete($address, true);

        $this->assertInstanceOf(AddressRemovedEvent::class, $removedEvent);
        $this->assertSame($address->id, $removedEvent->getAddress()->id);
    }
}
