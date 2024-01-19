<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Repositories\MeasurementsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\ApplicationModule\Seeders\MeasurementsTrait;
use Crm\UsersModule\Measurements\NewUsersMeasurement;
use Crm\UsersModule\Measurements\SignInMeasurement;
use Symfony\Component\Console\Output\OutputInterface;

class MeasurementsSeeder implements ISeeder
{
    use MeasurementsTrait;

    private MeasurementsRepository $measurementsRepository;

    public function __construct(MeasurementsRepository $measurementsRepository)
    {
        $this->measurementsRepository = $measurementsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->addMeasurement(
            $output,
            SignInMeasurement::CODE,
            'users.measurements.sign_in.title',
            'users.measurements.sign_in.description',
        );
        $this->addMeasurement(
            $output,
            NewUsersMeasurement::CODE,
            'users.measurements.new_users.title',
            'users.measurements.new_users.description',
        );
    }
}
