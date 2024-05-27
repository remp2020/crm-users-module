<?php

namespace Crm\UsersModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Crm\UsersModule\Models\Config;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class UsersModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'show_full_address' => Expect::bool(false)->dynamic(),
            'countries' => Expect::structure([
                'default' => Expect::string('SK')->dynamic(),
            ]),
            'sso' => Expect::structure([
                'google' => Expect::structure([
                    'client_id' => Expect::string()->nullable()->dynamic(),
                    'client_secret' => Expect::string()->nullable()->dynamic(),
                ]),
                'apple' => Expect::structure([
                    'client_id' => Expect::string()->dynamic(),
                    'trusted_client_ids' => Expect::listOf('string')->dynamic(),
                ]),
            ]),
        ]);
    }

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        // set extension parameters
        if (!isset($builder->parameters['countries']['default'])) {
            $builder->parameters['countries']['default'] = $this->config->countries->default;
        }
        if (!isset($builder->parameters['sso']['google'])) {
            $builder->parameters['sso']['google']['client_id'] = $this->config->sso->google->client_id;
            $builder->parameters['sso']['google']['client_secret'] = $this->config->sso->google->client_secret;
        }
        if (!isset($builder->parameters['sso']['apple'])) {
            $builder->parameters['sso']['apple']['client_id'] = $this->config->sso->apple->client_id;
            $builder->parameters['sso']['apple']['trusted_client_ids'] = $this->config->sso->apple->trusted_client_ids;
        }

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        /** @var ServiceDefinition $definition */
        foreach ($builder->findByType(Config::class) as $definition) {
            $definition->setFactory($definition->getType(), [
                $this->config->show_full_address,
            ]);
        }
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['Users' => 'Crm\UsersModule\Presenters\*Presenter']]);
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
