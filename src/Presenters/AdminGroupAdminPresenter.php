<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Forms\AdminGroupFormFactory;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AdminGroupAdminPresenter extends AdminPresenter
{
    /** @inject */
    public AdminGroupsRepository $adminGroupsRepository;

    /** @inject */
    public AdminGroupFormFactory $adminGroupFormFactory;

    /** @inject */
    public AdminAccessRepository $adminAccessRepository;

    /** @inject */
    public AdminGroupsAccessRepository $adminGroupsAccessRepository;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $this->template->groups = $this->adminGroupsRepository->all();
    }

    /**
     * @admin-access-level write
     */
    public function renderNew()
    {
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $this->template->group = $this->adminGroupsRepository->find($id);
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $this->template->group = $this->adminGroupsRepository->find($id);
        $this->template->accesses = $this->adminAccessRepository->all();
    }

    public function createComponentGroupForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->adminGroupFormFactory->create($id);
        $this->adminGroupFormFactory->onCreate = function ($group) {
            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.group_created'));
            $this->redirect('show', $group->id);
        };
        $this->adminGroupFormFactory->onUpdate = function ($group) {
            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.group_updated'));
            $this->redirect('show', $group->id);
        };
        return $form;
    }

    public function createComponentAccessForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $accesses = $this->adminAccessRepository->all();

        $group = $this->adminGroupsRepository->find($this->params['id']);

        $form->addGroup()
            ->setOption('description', $this->translator->translate('users.admin.admin_group_admin.privileges.global_settings'))
            ->setOption('container', Html::el('fieldset class="global-settings"'));
        $form
            ->addRadioList(
                'privileges',
                null,
                [
                    'all' => $this->translator->translate('users.admin.admin_group_admin.privileges.all'),
                    'read' => $this->translator->translate('users.admin.admin_group_admin.privileges.read_only'),
                    'none' => $this->translator->translate('users.admin.admin_group_admin.privileges.none'),
                    'custom' => $this->translator->translate('users.admin.admin_group_admin.privileges.custom'),
                ]
            )
            ->setDisabled(['custom']);

        $defaults = [];
        foreach ($group->related('admin_groups_access') as $adminGroupAccess) {
            $defaults['access_' . $adminGroupAccess->admin_access_id] = true;
        }

        $formGroup = false;
        $accessesPerModule = [];
        foreach ($accesses as $access) {
            $parts = explode(':', $access->resource);
            if (!isset($parts[0])) {
                continue;
            }
            $module = $parts[0];

            if ($formGroup !== $module) {
                $form->addGroup($module)
                    ->setOption('container', Html::el('fieldset class="resources-group"'))
                    ->setOption('embedNext', true);

                $allContainer = $form->addContainer(str_replace(':', '_', $access->resource));
                $radioList = $allContainer
                    ->addRadioList(
                        'privileges',
                        null,
                        [
                            'all' => $this->translator->translate('users.admin.admin_group_admin.privileges.all'),
                            'read' => $this->translator->translate('users.admin.admin_group_admin.privileges.read_only'),
                            'none' => $this->translator->translate('users.admin.admin_group_admin.privileges.none'),
                            'custom' => $this->translator->translate('users.admin.admin_group_admin.privileges.custom'),
                        ]
                    )
                    ->setDisabled(['custom'])
                    ->setHtmlAttribute('data-access-module', $module);

                $form->addGroup(null)
                    ->setOption('container', Html::el('fieldset class="resources"'));

                $accessesPerModule[$module] = ['radio' => [
                    'container' => $allContainer->getName(),
                    'radio' => $radioList->getName()]
                ];
            }

            $permission = $access->resource . ':' . $access->action;
            // missing access level won't be displayed as `read` to indicate that it needs to be checked by developer
            // this will change in future; missing access level will be treated as 'read' level
            $permissionLevel =  $access->level ? '<em class="access-metadata">(' . $access->type . ', ' . $access->level . ')</em> ' : '';

            $checkbox = $form
                ->addCheckbox(
                    'access_' . $access->id,
                    Html::el('span', ['class' => 'access-resource'])->setHtml($permissionLevel . $permission)
                )
                ->setHtmlAttribute('data-access-module', $module)
                ->setHtmlAttribute('data-access-resource', $access->resource)
                ->setHtmlAttribute('data-access-level', $access->level);

            $accessesPerModule[$module]['checkboxes'][] = [
                'name' => $checkbox->getName(),
                'level' => $access->level
            ];

            $formGroup = $module;
        }

        // set default values for radio lists
        foreach ($accessesPerModule as $module) {
            $moduleCheckboxes = array_column($module['checkboxes'], 'name');
            $moduleCount = count($moduleCheckboxes);
            $enabledCount = array_intersect($moduleCheckboxes, array_keys($defaults));

            // set radio button to enabled if 'all' of all module checkboxes are set
            if ($moduleCount === count($enabledCount)) {
                $defaults[$module['radio']['container']] = [$module['radio']['radio'] => 'all'];
            } else {
                // we need to check read levels
                $allReadOnly = true;
                $noPrivileges = true;
                foreach ($module['checkboxes'] as $checkbox) {
                    if (!isset($defaults[$checkbox['name']])) {
                        $allReadOnly = false;
                        continue;
                    }
                    // found some set privilege
                    $noPrivileges = false;

                    if ($checkbox['level'] === 'write') {
                        $allReadOnly = false;
                    }
                }

                if ($noPrivileges) {
                    $defaultValue = 'none';
                } elseif ($allReadOnly) {
                    $defaultValue = 'read';
                } else {
                    $defaultValue = 'custom';
                }
                $defaults[$module['radio']['container']] = [$module['radio']['radio'] => $defaultValue];
            }
        }

        // set default values for global radio list
        $globalPrivileges = 'all';
        $radioButtonLevels = array_column($defaults, 'privileges');
        $read = array_search('read', $radioButtonLevels, true);
        $custom = array_search('custom', $radioButtonLevels, true);

        if (count($radioButtonLevels) === 0) {
            $globalPrivileges = 'none';
        } elseif ($read === count($radioButtonLevels)) {
            $globalPrivileges = 'read';
        } elseif ($read !== 0 || $custom !== 0) {
            $globalPrivileges = 'custom';
        }
        $defaults['privileges'] = $globalPrivileges;

        $form->setDefaults($defaults);

        $form->addGroup();

        $form->addSubmit('submit', $this->translator->translate('users.admin.admin_group_admin.submit'));
        $form->onSuccess[] = function ($form, $values) use ($group) {
            $accesses = [];
            foreach ($values as $key => $value) {
                if (!$value) {
                    continue;
                }
                $accessId = str_replace('access_', '', $key);
                if ($accessId === $key) {
                    continue; // skip radio buttons (used only for frontend changes)
                }
                $accesses[] = $accessId;
            }

            // if admin group's access is not part of form, it should not exist in database
            // (this is cleaning process to ensure database holds only accesses administrator saw in form)
            $this->adminGroupsAccessRepository->deleteByGroup(
                adminGroup: $group,
                toDelete: [],
                toExclude: $accesses,
            );
            foreach ($accesses as $accessId) {
                $adminAccess = $this->adminAccessRepository->find($accessId);
                $adminGroupAccessExists = $this->adminGroupsAccessRepository->exists($group, $adminAccess);
                if (!$adminGroupAccessExists) {
                    $this->adminGroupsAccessRepository->add($group, $adminAccess);
                }
            }

            $this->flashMessage($this->translator->translate('users.admin.admin_group_admin.rights_updated'));
            $this->redirect('show', $group->id);
        };
        return $form;
    }
}
