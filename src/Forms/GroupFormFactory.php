<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Repositories\GroupsRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GroupFormFactory
{
    protected $groupsRepository;

    protected $translator;

    public $onSave;

    public $onCreate;

    public $onUpdate;

    public function __construct(GroupsRepository $groupsRepository, Translator $translator)
    {
        $this->groupsRepository = $groupsRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($groupId)
    {
        $defaults = [];
        if (isset($groupId)) {
            $group = $this->groupsRepository->find($groupId);
            $defaults = $group->toArray();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addHidden('id');

        $form->addText('name', $this->translator->translate('users.admin.group_form.name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.group_form.name.placeholder'))
            ->setRequired();

        $form->addText('sorting', $this->translator->translate('users.admin.group_form.sorting.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.group_form.sorting.placeholder'))
            ->setDefaultValue(100)
            ->setRequired();

        $form->addSubmit('send', $this->translator->translate('users.admin.group_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.admin.group_form.submit'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($values->id) {
            $group = $this->groupsRepository->find($values->id);
            $this->groupsRepository->update($group, $values);
            $this->onUpdate->__invoke($group);
        } else {
            $group = $this->groupsRepository->add($values->name, $values->sorting);
            $this->onCreate->__invoke($group);
        }
    }
}
