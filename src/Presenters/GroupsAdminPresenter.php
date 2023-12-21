<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Forms\GroupFormFactory;
use Crm\UsersModule\Repository\GroupsRepository;
use Nette\DI\Attributes\Inject;

class GroupsAdminPresenter extends AdminPresenter
{
    #[Inject]
    public GroupsRepository $groupsRepository;

    #[Inject]
    public GroupFormFactory $groupFormFactory;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $this->template->groups = $this->groupsRepository->all();
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
        $this->template->group = $this->groupsRepository->find($id);
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $this->template->group = $this->groupsRepository->find($id);
    }

    /**
     * @admin-access-level write
     */
    public function handleDelete($id)
    {
        $row = $this->groupsRepository->find($id);

        if (!is_null($row)) {
            $this->groupsRepository->delete($row);
        }
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
