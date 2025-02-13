<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AbusiveUsersFilterFormFactory
{
    private $translator;

    public $onSave;

    public $onCancel;

    public function __construct(
        Translator $translator
    ) {
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create()
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $defaults = [];

        $form->addText('email', 'users.admin.abusive.email');

        $form->addText('dateFrom', 'users.admin.abusive.date_from')
            ->setHtmlAttribute('data-input');

        $form->addText('dateTo', 'users.admin.abusive.date_to')
            ->setHtmlAttribute('data-input');

        $form->addSelect(
            'loginCount',
            'users.admin.abusive.number_of_logins',
            [10 => '10+', 25 => '25+', 50 => '50+', 100 => '100+']
        );

        $form->addSelect(
            'deviceCount',
            'users.admin.abusive.number_of_devices',
            [1 => '1+', 5 => '5+', 10 => '10+', 25 => '25+', 50 => '50+']
        );

        $form->addSubmit('send', 'users.admin.abusive.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('users.admin.abusive.submit'));

        $form->addSubmit('cancel', 'users.admin.abusive.cancel_filter')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-times"></i> ' . $this->translator->translate('users.admin.abusive.cancel_filter'));

        $form->getComponent('cancel')->onClick[] = function () {
            $this->onCancel->__invoke();
        };

        $form->setDefaults($defaults);

        return $form;
    }
}
