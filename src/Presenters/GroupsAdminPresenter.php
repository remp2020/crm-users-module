<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Forms\GroupFormFactory;
use Crm\UsersModule\Repositories\GroupsRepository;
use Crm\UsersModule\Repositories\UserGroupsRepository;
use Nette\DI\Attributes\Inject;

class GroupsAdminPresenter extends AdminPresenter
{
    #[Inject]
    public GroupsRepository $groupsRepository;

    #[Inject]
    public GroupFormFactory $groupFormFactory;

    #[Inject]
    public UserGroupsRepository $userGroupsRepository;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $groups = [];
        foreach ($this->groupsRepository->all() as $group) {
            $groups[] = (object) [
                'row' => $group,
                'memberCount' => $this->userGroupsRepository->groupMembers($group)->count('*'),
            ];
        }

        $this->template->groups = $groups;
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
        $group = $this->groupsRepository->find($id);
        if (!$group) {
            $this->error();
        }
        $this->template->group = $group;
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $group = $this->groupsRepository->find($id);
        if (!$group) {
            $this->error();
        }
        $this->template->group = $group;
        $this->template->memberCount = $this->userGroupsRepository->groupMembers($group)->count('*');
    }

    /**
     * @admin-access-level write
     */
    public function handleDelete($id)
    {
        $row = $this->groupsRepository->find($id);
        if (is_null($row)) {
            return;
        }

        if ($this->userGroupsRepository->groupMembers($row)->count('*') > 0) {
            return;
        }

        $this->groupsRepository->softDelete($row);
    }

    public function createComponentGroupForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->groupFormFactory->create($id);
        $this->groupFormFactory->onCreate = function ($group) {
            $this->flashMessage('Skupina bola vytvorená.');
            $this->redirect('show', $group->id);
        };
        $this->groupFormFactory->onUpdate = function ($group) {
            $this->flashMessage('Skupina bola aktualizovná.');
            $this->redirect('show', $group->id);
        };
        return $form;
    }
}
