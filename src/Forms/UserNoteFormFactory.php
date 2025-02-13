<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Phinx\Db\Adapter\MysqlAdapter;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UserNoteFormFactory
{
    protected $usersRepository;

    private $translator;

    /* callback function */
    public $onUpdate;

    /** @var ActiveRow */
    private $user;

    public function __construct(
        Translator $translator,
        UsersRepository $usersRepository
    ) {
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
    }

    /**
     * @params $user
     * @return Form
     */
    public function create(ActiveRow $user)
    {
        $form = new Form;
        $this->user = $user;

        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $note = $form->addTextArea('note', '')
            ->setHtmlAttribute('placeholder', 'users.admin.user_note_form.note.placeholder')
            ->setNullable()
            ->setRequired(false);
        $note->addCondition(Form::FILLED)
                ->addRule(
                    Form::MAX_LENGTH,
                    'users.admin.user_note_form.note.validation.maximum',
                    MysqlAdapter::TEXT_REGULAR
                );
        $note->getControlPrototype()->addAttributes([
                'class' => 'autosize',
                'style' => 'max-height: 400px;'
            ]);

        $form->addSubmit('send', 'system.save')
            ->setHtmlAttribute('class', 'btn btn-primary')
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
