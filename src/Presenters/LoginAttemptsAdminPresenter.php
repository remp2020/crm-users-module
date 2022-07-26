<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class LoginAttemptsAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $created_at_from;

    /** @persistent */
    public $created_at_to;

    /** @persistent */
    public $email;

    /** @persistent */
    public $user_agent;

    private $loginAttemptsRepository;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository)
    {
        parent::__construct();
        $this->loginAttemptsRepository = $loginAttemptsRepository;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $filteredLoginAttempts = $this->getFilteredLoginAttempts();

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $filteredLoginAttempts = $filteredLoginAttempts->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($filteredLoginAttempts));

        $this->template->createdAtFrom = $this->created_at_from;
        $this->template->createdAtTo = $this->created_at_to;
        $this->template->loginAttempts = $filteredLoginAttempts;
    }

    private function getFilteredLoginAttempts()
    {
        $loginAttempts = $this->loginAttemptsRepository->getTable()->order('created_at DESC');

        if ($this->request->getParameter('status')) {
            $loginAttempts->where('status IN ?', $this->request->getParameter('status'));
        }
        if ($this->email) {
            $loginAttempts->where('email LIKE ?', "%{$this->email}%");
        }
        if ($this->user_agent) {
            $loginAttempts->where('user_agent LIKE ?', "%{$this->user_agent}%");
        }
        if ($this->created_at_from) {
            $loginAttempts->where('created_at >= ?', $this->created_at_from);
        }
        if ($this->created_at_to) {
            $loginAttempts->where('created_at <= ?', $this->created_at_to);
        }

        return $loginAttempts;
    }

    public function createComponentLoginAttemptsForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());

        $form->addText('email', $this->translator->translate('users.admin.login_attempts_form.email.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.login_attempts_form.email.placeholder'))
            ->setAttribute('autofocus');

        $statuses = $this->loginAttemptsRepository->getTable()->select("DISTINCT status")->fetchPairs("status", "status");
        $form->addMultiSelect('status', $this->translator->translate('users.admin.login_attempts_form.status.label'), $statuses)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $sources = $this->loginAttemptsRepository->getTable()->select("DISTINCT source")->fetchPairs("source", "source");
        $form->addMultiSelect('source', $this->translator->translate('users.admin.login_attempts_form.source.label'), $sources)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addText('created_at_from', $this->translator->translate('users.admin.login_attempts_form.created_at_from.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.login_attempts_form.created_at_from.placeholder'));

        $form->addText('created_at_to', $this->translator->translate('users.admin.login_attempts_form.created_at_to.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.login_attempts_form.created_at_to.placeholder'));

        $form->addText('user_agent', $this->translator->translate('users.admin.login_attempts_form.user_agent.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.admin.login_attempts_form.user_agent.placeholder'));

        $form->addSubmit('send', $this->translator->translate('users.admin.login_attempts_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('users.admin.login_attempts_form.submit'));

        $form->setDefaults([
            'created_at_from' => $this->created_at_from,
            'created_at_to' => $this->created_at_to,
            'email' => $this->email,
            'user_agent' => $this->user_agent,
            'status' => $this->request->getParameter('status'),
            'source' => $this->request->getParameter('source'),
        ]);

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        return $form;
    }
}
