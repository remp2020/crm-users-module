<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    use ConfigsTrait;

    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder,
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->configCategoriesRepository->loadByName('application.config.category');
        $this->addConfig(
            $output,
            $category,
            'not_logged_in_route',
            ApplicationConfig::TYPE_STRING,
            'application.config.not_logged_in_route.name',
            'application.config.not_logged_in_route.description',
            ':Users:Sign:in',
            280,
        );

        $categoryName = 'users.config.category_authentication';
        $category = $this->configCategoriesRepository->loadByName($categoryName);
        if (!$category) {
            $category = $this->configCategoriesRepository->add($categoryName, 'fa fa-key', 300);
            $output->writeln('  <comment>* config category <info>Authentication</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Authentication</info> exists');
        }

        $this->addConfig(
            $output,
            $category,
            'google_sign_in_enabled',
            ApplicationConfig::TYPE_BOOLEAN,
            'users.config.google_sign_in_enabled.name',
            '',
            false,
            10,
        );

        $this->addConfig(
            $output,
            $category,
            'apple_sign_in_enabled',
            ApplicationConfig::TYPE_BOOLEAN,
            'users.config.apple_sign_in_enabled.name',
            '',
            false,
            10,
        );
    }
}
