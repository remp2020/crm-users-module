<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Forms\BootstrapSmallInlineFormRenderer;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\Translator;

class UserGroupsFormFactory
{
    protected $userRepository;

    protected $groupsRepository;

    protected $userGroupsRepository;

    protected $translator;

    public $onAddedUserToGroup;

    public $onRemovedUserFromGroup;

    public function __construct(
        UsersRepository $userRepository,
        GroupsRepository $groupsRepository,
        UserGroupsRepository $userGroupsRepository,
        Translator $translator
    ) {
        $this->userRepository = $userRepository;
        $this->groupsRepository = $groupsRepository;
        $this->userGroupsRepository = $userGroupsRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     * @throws BadRequestException
     */
    public function create($userId)
    {
        $defaults = [];

        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $form = new Form;

        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapSmallInlineFormRenderer());
        $form->addProtection();

        $userGroups = $this->userGroupsRepository->userGroups($user);

        $userGroupsIds = [];
        if ($userGroups->count('*') > 0) {
            $factory = $this;
            foreach ($userGroups as $group) {
                $userGroupsIds[] = $group->id;
                $button = $form->addSubmit('group_' . $group->id);
                $button->setHtmlAttribute('class', 'btn btn-default btn-blxock btn-sm');
                $button->getControlPrototype()->setName('button')->setHtml('<i class="fa fa-times"></i> ' . $group->name);
                $button->onClick[] = function () use ($factory, $group, $user, $form) {
                    $factory->userGroupsRepository->removeFromGroup($group, $user);
                    $factory->onRemovedUserFromGroup->__invoke($form, $group, $user);
                    return false;
                };
            }
        }

        $groups = $this->groupsRepository->all()->fetchPairs('id', 'name');

        $groupsArray = [];
        foreach ($groups as $groupId => $groupName) {
            if (!in_array($groupId, $userGroupsIds, true)) {
                $groupsArray[$groupId] = $groupName;
            }
        }

        if (count($groupsArray) > 0) {
            $form->addSelect('group_id', '', $groupsArray)
                ->setPrompt('users.form.user_group.group_id.prompt');

            $form->addSubmit('send', 'users.form.user_group.send')
                ->setHtmlAttribute('class', 'btn btn-primary')
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.form.user_group.send'));
        }

        $form->addHidden('user_id', $userId);

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $group = $this->groupsRepository->find($values['group_id']);
        if (!$group) {
            $form['group_id']->addError('Neexistujuca skupina');
            return;
        }
        $user = $this->userRepository->find($values['user_id']);
        if (!$user) {
            $form['user_id']->addError('Neexistujuci pouzivatel');
            return;
        }

        $result = $this->userGroupsRepository->addToGroup($group, $user);
        if ($result) {
            $this->onAddedUserToGroup->__invoke($form, $group, $user);
        }
    }
}
