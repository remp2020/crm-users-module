<?php

namespace Crm\UsersModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;

final class UsersModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    private $defaults = [
        'countries' => [
            'default' => 'SK',
        ],
        'sso' => [
            'google' => [
                'client_id' => null,
                'client_secret' => null,
            ],
            'apple' => [
                'client_id' => null,
                'trusted_client_ids' => [],
            ]
        ]
    ];

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        // set default values if user didn't define them
        $this->config = $this->validateConfig($this->defaults);
        // set extension parameters
        if (!isset($builder->parameters['countries']['default'])) {
            $builder->parameters['countries']['default'] = $this->config['countries']['default'];
        }
        if (!isset($builder->parameters['sso']['google'])) {
            $builder->parameters['sso']['google'] = $this->config['sso']['google'];
        }
        if (!isset($builder->parameters['sso']['apple'])) {
            $builder->parameters['sso']['apple'] = $this->config['sso']['apple'];
        }

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );
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
