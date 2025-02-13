<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Forms\BootstrapSmallInlineFormRenderer;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Repositories\AdminGroupsRepository;
use Crm\UsersModule\Repositories\AdminUserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\Translator;

class AdminUserGroupFormFactory
{
    public $onAddedUserToGroup;

    public $onRemovedUserFromGroup;

    public $authorize;

    public function __construct(
        private AdminGroupsRepository $adminGroupsRepository,
        private AdminUserGroupsRepository $adminUserGroupsRepository,
        private UsersRepository $usersRepository,
        private Translator $translator
    ) {
    }

    /**
     * @return Form
     * @throws BadRequestException
     */
    public function create($userId)
    {
        $defaults = [];

        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapSmallInlineFormRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $userGroups = $user->related('admin_user_groups');

        $userGroupsIds = [];
        if ($userGroups->count('*') > 0) {
            $factory = $this;
            foreach ($userGroups as $userGroup) {
                $group = $userGroup->group;
                $userGroupsIds[] = $group->id;
                $accesses = $group->related('admin_groups_access')->count('*');
                $button = $form->addSubmit('group_' . $group->id);
                $button->setHtmlAttribute('class', 'btn btn-default btn-blxock btn-sm');
                $button->getControlPrototype()
                    ->setName('button')
                    ->setHtml('<i class="fa fa-times"></i> ' . $group->name . ' (' . $accesses . ')');
                $button->onClick[] = function () use ($factory, $group, $user, $form) {
                    $this->adminUserGroupsRepository->remove($group, $user);
                    $factory->onRemovedUserFromGroup->__invoke($form, $group, $user);
                    return false;
                };
            }
        }

        $groups = $this->adminGroupsRepository->all()->fetchPairs('id', 'name');

        $groupsArray = [];
        foreach ($groups as $groupId => $groupName) {
            if (!in_array($groupId, $userGroupsIds, true)) {
                $groupsArray[$groupId] = $groupName;
            }
        }

        if (count($groupsArray) > 0) {
            $form->addSelect('group_id', '', $groupsArray)
                ->setPrompt('users.form.admin_user_group.group_id.prompt');

            $form->addSubmit('send', 'users.form.admin_user_group.send')
                ->setHtmlAttribute('class', 'btn btn-primary')
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.form.admin_user_group.send'));
        }

        $form->addHidden('user_id', $userId);

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if (!$this->authorize->__invoke()) {
            $form->addError('users.form.admin_user_group.error.insufficient_rights');
            return;
        }

        $adminGroup = $this->adminGroupsRepository->find($values['group_id']);
        if (!$adminGroup) {
            $form['group_id']->addError('users.form.admin_user_group.error.no_group');
            return;
        }
        $user = $this->usersRepository->find($values['user_id']);
        if (!$user) {
            $form['user_id']->addError('users.form.admin_user_group.error.no_user');
            return;
        }

        $result = $this->adminUserGroupsRepository->add($adminGroup, $user);
        if ($result) {
            $this->onAddedUserToGroup->__invoke($form, $adminGroup, $user);
        }
    }
}
