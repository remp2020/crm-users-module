<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AdminGroupFormFactory
{
    protected $adminGroupsRepository;

    protected $translator;

    public $onSave;

    public $onCreate;

    public $onUpdate;

    public function __construct(AdminGroupsRepository $adminGroupsRepository, Translator $translator)
    {
        $this->adminGroupsRepository = $adminGroupsRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($groupId)
    {
        $defaults = [];
        if (isset($groupId)) {
            $group = $this->adminGroupsRepository->find($groupId);
            $defaults = $group->toArray();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addHidden('id');

        $form->addText('name', $this->translator->translate('users.admin.admin_group_form.name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.admin_group_form.name.placeholder'))
            ->setRequired();

        $form->addText('sorting', $this->translator->translate('users.admin.admin_group_form.sorting.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.admin_group_form.sorting.placeholder'))
            ->setDefaultValue(100)
            ->setRequired();

        $form->addSubmit('send', $this->translator->translate('users.admin.admin_group_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.admin.admin_group_form.submit'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($values->id) {
            $group = $this->adminGroupsRepository->find($values->id);
            unset($values->id);
            $this->adminGroupsRepository->update($group, $values);
            $this->onUpdate->__invoke($group);
        } else {
            $group = $this->adminGroupsRepository->add($values->name, $values->sorting);
            $this->onCreate->__invoke($group);
        }
    }
}
