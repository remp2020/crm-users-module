<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Symfony\Component\Console\Output\OutputInterface;

class AddressTypesTestSeeder implements ISeeder
{
    public const ADDRESS_TYPE = 'crm_test';

    public function __construct(private AddressTypesRepository $addressTypesRepository)
    {
    }

    public function seed(OutputInterface $output)
    {
        $types = [
            self::ADDRESS_TYPE => 'Dummy address type for tests',
        ];

        foreach ($types as $type => $title) {
            if ($this->addressTypesRepository->findBy('type', $type)) {
                $output->writeln("  * address type <info>{$type}</info> exists");
            } else {
                $this->addressTypesRepository->insert([
                    'type' => $type,
                    'title' => $title,
                ]);
                $output->writeln("  <comment>* address type <info>{$type}</info> created</comment>");
            }
        }
    }
}
