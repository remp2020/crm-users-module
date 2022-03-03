<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\IRow;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UserNoteFormFactory
{
    protected $usersRepository;

    private $translator;

    /* callback function */
    public $onUpdate;

    /** @var IRow */
    private $user;

    public function __construct(
        ITranslator $translator,
        UsersRepository $usersRepository
    ) {
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
    }

    /**
     * @params $user
     * @return Form
     */
    public function create(IRow $user)
    {
        $form = new Form;
        $this->user = $user;

        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $note = $form->addTextArea('note', '')
            ->setAttribute('placeholder', 'users.admin.user_note_form.note.placeholder')
            ->setNullable()
            ->setRequired(false);
        $note->addCondition(Form::FILLED)
                ->addRule(
                    Form::MAX_LENGTH,
                    'users.admin.user_note_form.note.validation.maximum',
                    \Phinx\Db\Adapter\MysqlAdapter::TEXT_REGULAR
                );
        $note->getControlPrototype()->addAttributes([
                'class' => 'autosize',
                'style' => 'max-height: 400px;'
            ]);

        $form->addSubmit('send', 'system.save')
            ->setAttribute('class', 'btn btn-primary')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->setDefaults([
            'note' => $this->user->note,
        ]);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $this->usersRepository->update($this->user, ['note' => $values['note']]);
        $this->onUpdate->__invoke($form, $this->user);
    }
}