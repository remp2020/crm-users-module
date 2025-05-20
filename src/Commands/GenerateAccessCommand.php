<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repositories\AdminAccessRepository;
use Crm\UsersModule\Repositories\AdminGroupsAccessRepository;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateAccessCommand extends Command
{
    private $adminAccessRepository;

    private $adminGroupsAccessRepository;

    private $container;

    public function __construct(
        AdminAccessRepository $adminAccessRepository,
        AdminGroupsAccessRepository $adminGroupsAccessRepository,
        Container $container,
    ) {
        parent::__construct();
        $this->adminAccessRepository = $adminAccessRepository;
        $this->adminGroupsAccessRepository = $adminGroupsAccessRepository;
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('user:generate_access')
            ->setDescription('Generate user access control resources')
            ->addOption(
                'cleanup',
                null,
                InputOption::VALUE_NONE,
                'Use this option to remove orphaned ACL resources (eg. (re)moved presenter / action; uninstalled module).',
            )
            ->addUsage('--cleanup');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generatedACL = [];
        foreach ($this->container->findByType('Crm\AdminModule\Presenters\AdminPresenter') as $adminServiceName) {
            try {
                $adminPresenterName = get_class($this->container->getService($adminServiceName));
                $output->writeln("Processing class <info>{$adminPresenterName}</info>");
                [$presenterResource, $presenterActions] = $this->processPresenterClass($adminPresenterName, $output);
                $generatedACL[$presenterResource] = $presenterActions;
            } catch (MissingServiceException $e) {
                $output->writeln("<error>Service {$adminServiceName} missing</error>");
            } catch (\Exception $e) {
                if (isset($adminPresenterName)) {
                    $output->writeln("<error>Unable to process presenter {$adminPresenterName}</error>");
                } else {
                    $output->writeln("<error>Unable to process service {$adminServiceName}</error>");
                }
                $output->writeln("<comment> - {$e->getMessage()}</comment>");
            }
        }

        if ($input->getOption('cleanup')) {
            $output->writeln("\nRemoving orphaned resources without presenter or action:");
            $storedResources = $this->adminAccessRepository->all()->fetchAll();
            $generatedResources = array_keys($generatedACL);
            foreach ($storedResources as $storedResource) {
                // is resource *Module*:*Presenter* still present?
                if (in_array($storedResource['resource'], $generatedResources, true)) {
                    // is *Module*:*Presenter*:*Action* still present?
                    $generatedActions = array_values($generatedACL[$storedResource['resource']]);
                    if (in_array($storedResource['action'], $generatedActions, true)) {
                        continue;
                    }
                }

                // not present; remove
                $output->writeln(" <comment>* ACL resource <info>{$storedResource->resource}:{$storedResource->action}</info> removed.");
                $this->adminGroupsAccessRepository->deleteByAdminAccess($storedResource);
                $this->adminAccessRepository->delete($storedResource);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array [string $resource, array $resourceActions]
     */
    private function processPresenterClass($presenterClass, OutputInterface $output): array
    {
        $parts = explode('\\', $presenterClass);
        $presenter = str_replace('Presenter', '', array_pop($parts));
        $next = array_pop($parts);
        if ($next === 'Presenters') {
            $next = array_pop($parts);
        }
        $module = str_replace('Module', '', $next);
        $resource = "{$module}:{$presenter}";

        $resourceActions = [];

        // select which prefixes
        $methodPrefixes = ['render', 'action', 'handle'];
        foreach (get_class_methods($presenterClass) as $methodName) {
            foreach ($methodPrefixes as $methodPrefix) {
                if (substr($methodName, 0, strlen($methodPrefix)) !== $methodPrefix) {
                    continue;
                }

                // parse resource & load access level from annotation
                $action = substr($methodName, strlen($methodPrefix));
                $action = lcfirst($action);

                $reflectionClass = new ReflectionClass($presenterClass);
                $method = $reflectionClass->getMethod($methodName);
                $phpDoc = $method->getDocComment();

                preg_match('/@admin-access-level\s+(\w+)/', $phpDoc, $match);
                $accessLevel = count($match) == 2 ? $match[1] : null;

                if (!in_array($accessLevel, [null, 'write', 'read'], true)) {
                    $output->writeln(
                        " * <error>ACL resource </error><fg=red;bg=white;options=bold> {$resource}:{$action} </><error>" .
                        " has incorrect access level <fg=red;bg=white;options=bold>[{$accessLevel}]</>.</error>\n" .
                        "   <error>Only read, write and null are allowed. Null will be used instead.</error>",
                    );
                    $accessLevel = null;
                }

                // add / update access resource
                $adminAccess = $this->adminAccessRepository->findByResourceAndAction($resource, $action);
                if (!$adminAccess) {
                    $this->adminAccessRepository->add($resource, $action, $methodPrefix, $accessLevel);
                    $output->writeln(" <comment>* ACL resource <info>{$resource}:{$action}</info> was created</comment>");
                } else {
                    $updateData = [];
                    if ($adminAccess['type'] !== $methodPrefix) {
                        $updateData['type'] = $methodPrefix;
                    }
                    if ($adminAccess['level'] !== $accessLevel) {
                        $updateData['level'] = $accessLevel;
                    }

                    if (!empty($updateData)) {
                        $this->adminAccessRepository->update($adminAccess, $updateData);
                        $output->writeln(" <comment>* ACL resource <info>{$resource}:{$action}</info> updated. Changes:</comment>");
                        foreach ($updateData as $key => $value) {
                            $output->writeln("\t- <comment>[{$key}]</comment> => <info>{$value}</info>");
                        }
                    }
                }

                $resourceActions[] = $action;
            }
        }

        return [$resource, $resourceActions];
    }
}
