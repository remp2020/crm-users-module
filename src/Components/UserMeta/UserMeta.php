<?php

namespace Crm\UsersModule\Components\UserMeta;

use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

/**
 * This widget fetches user meta data and renders
 * bootstrap styles table.
 *
 * @package Crm\UsersModule\Components
 */
class UserMeta extends Control implements WidgetInterface
{
    private $templateName = 'user_meta.latte';

    private $userMetaRepository;

    private $usersRepository;

    private $translator;

    private $user;

    private $userId;

    public function __construct(
        UserMetaRepository $userMetaRepository,
        UsersRepository $usersRepository,
        Translator $translator,
    ) {
        $this->userMetaRepository = $userMetaRepository;
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('users.component.user_meta.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'usermeta';
    }

    private function getUser($id)
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->find($id);
        }
        return $this->user;
    }

    public function render($id)
    {
        $this->userId = $id;
        $meta = $this->userMetaRepository->userMetaRows($this->getUser($id));
        $this->template->meta = $meta;
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    public function handleDelete($id, $key)
    {
        $this->userMetaRepository->removeMeta($id, $key);
        $this->presenter->flashMessage($this->translator->translate('users.component.user_meta.value_removed'));
        $this->presenter->redirect('UsersAdmin:Show', $id);
    }

    protected function createComponentMetaForm()
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapInlineRenderer());

        $form->addText('key', 'users.component.user_meta.form.key.label')
            ->setRequired('users.component.user_meta.form.key.required');

        $form->addText('value', 'users.component.user_meta.form.value.label')
            ->setRequired('users.component.user_meta.form.value.required');

        $form->addCheckbox('is_public', 'users.component.user_meta.form.is_public.label');
        $form->addHidden('user_id', $this->userId);
        $form->addSubmit('submit', 'users.component.user_meta.form.submit');

        $form->onSuccess[] = function ($form, $values) {
            $this->userMetaRepository->setMeta(
                $this->getUser($values['user_id']),
                [$values['key'] => $values['value']],
                $values->is_public,
            );
            $this->presenter->flashMessage($this->translator->translate('users.component.user_meta.value_added'));
            $this->presenter->redirect('UsersAdmin:Show', $values['user_id']);
        };

        return $form;
    }

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = count($this->userMetaRepository->userMeta($id));
        }
        return $this->totalCount;
    }
}
