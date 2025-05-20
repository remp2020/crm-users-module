<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\DataProviders\UserFormDataProviderInterface;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\AdminUserGroupsRepository;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\UserAlreadyExistsException;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Locale;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Tomaj\Form\Renderer\BootstrapRenderer;

class UserFormFactory
{
    private $userRepository;

    private $userBuilder;

    private $translator;

    private $dataProviderManager;

    private $adminUserGroupsRepository;

    private $changePasswordsLogsRepository;

    private $emitter;

    public $onSave;

    public $onUpdate;

    private $onCallback;

    /** @var Passwords */
    private $passwords;

    public function __construct(
        UsersRepository $userRepository,
        UserBuilder $userBuilder,
        Translator $translator,
        DataProviderManager $dataProviderManager,
        AdminUserGroupsRepository $adminUserGroupsRepository,
        Passwords $passwords,
        ChangePasswordsLogsRepository $changePasswordsLogsRepository,
        Emitter $emitter,
    ) {
        $this->userRepository = $userRepository;
        $this->userBuilder = $userBuilder;
        $this->translator = $translator;
        $this->dataProviderManager = $dataProviderManager;
        $this->adminUserGroupsRepository = $adminUserGroupsRepository;
        $this->passwords = $passwords;
        $this->changePasswordsLogsRepository = $changePasswordsLogsRepository;
        $this->emitter = $emitter;
    }

    public function create($userId): Form
    {
        $defaults = [];
        $user = null;

        if (isset($userId)) {
            $user = $this->userRepository->find($userId);
            $defaults = $user->toArray();
            unset($defaults['password']);
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->onSuccess[] = [$this, 'formSucceeded'];

        $form->addGroup($this->translator->translate('users.admin.user_form.credentials'));
        $form->addText('email', $this->translator->translate('users.admin.user_form.email.label'))
            ->setRequired($this->translator->translate('users.admin.user_form.email.required'))
            ->setHtmlType('email')
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.email.placeholder'));
        $password = $form->addPassword('password', $this->translator->translate('users.admin.user_form.password.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.password.placeholder'))
            ->setHtmlAttribute('autocomplete', 'new-password');
        if (!$userId) {
            $password->setRequired($this->translator->translate('users.admin.user_form.password.required'));
        } else {
            $password->setOption('description', $this->translator->translate('users.admin.user_form.password.description'));
        }

        $form->addText('source', $this->translator->translate('users.admin.user_form.source.label'))
            ->setRequired($this->translator->translate('users.admin.user_form.source.required'))
            ->setDefaultValue('backend');

        $form->addGroup($this->translator->translate('users.admin.user_form.personal_information'));

        $localeOptions = [];
        foreach ($this->translator->getAvailableLocales() as $locale) {
            $localeOptions[$locale] = Locale::getDisplayLanguage($locale, $this->translator->getLocale());
        }
        $form->addSelect(
            'locale',
            $this->translator->translate('users.admin.user_form.locale.label'),
            $localeOptions,
        )->setDefaultValue($this->translator->getLocale());
        $form->addText('first_name', $this->translator->translate('users.admin.user_form.first_name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.first_name.placeholder'));
        $form->addText('last_name', $this->translator->translate('users.admin.user_form.last_name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.last_name.placeholder'));
        $form->addText('public_name', $this->translator->translate('users.admin.user_form.public_name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.public_name.placeholder'))
            ->setOption('description', $this->translator->translate('users.admin.user_form.public_name.description'));

        $form->addGroup($this->translator->translate('users.admin.user_form.institution'));

        $form->addCheckbox('is_institution', $this->translator->translate('users.admin.user_form.is_institution'));
        $form->addText('institution_name', $this->translator->translate('users.admin.user_form.institution_name.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.institution_name.placeholder'))
            ->addConditionOn($form['is_institution'], Form::EQUAL, true)
                ->setRequired($this->translator->translate('users.admin.user_form.institution_name.required'));

        /** @var UserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.user_form', UserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'user' => $user]);
        }

        $form->addGroup($this->translator->translate('users.admin.user_form.other'));

        $form->addText('ext_id', $this->translator->translate('users.admin.user_form.external_id.label'))
            ->setHtmlAttribute('placeholder', $this->translator->translate('users.admin.user_form.external_id.placeholder'))
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, $this->translator->translate('users.admin.user_form.external_id.integer'));
        $form->addSelect('role', $this->translator->translate('users.admin.user_form.role.label'), [
            UsersRepository::ROLE_USER => $this->translator->translate('users.admin.user_form.role.user'),
            UsersRepository::ROLE_ADMIN => $this->translator->translate('users.admin.user_form.role.admin'),
        ]);
        $form->addCheckbox('active', $this->translator->translate('users.admin.user_form.active'));

        $form->addSubmit('send', $this->translator->translate('users.admin.user_form.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.admin.user_form.submit'));

        if ($userId) {
            $form->addHidden('user_id', $userId);
        }

        $form->setDefaults($defaults);
        $form->onSuccess[] = [$this, 'callback'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $values = clone($values);
        foreach ($values as $i => $item) {
            if ($item instanceof ArrayHash) {
                unset($values[$i]);
            }
        }

        // if public name is missing, set email as public_name
        if (strlen(trim($values['public_name'])) === 0) {
            $values['public_name'] = $values['email'];
        }
        $values['ext_id'] = trim($values['ext_id']) ? (int) $values['ext_id'] : null;

        if (isset($values['user_id'])) {
            $userId = $values['user_id'];
            unset($values['user_id']);

            try {
                $newPassword = null;
                if (isset($values['password'])) {
                    if (strlen($values['password']) > 0) {
                        $newPassword = $values['password'];
                        $values['password'] = $this->passwords->hash($values['password']);
                    } else {
                        unset($values['password']);
                    }
                }

                $user = $this->userRepository->find($userId);
                $oldPasswordHash = $user->password;
                $this->userRepository->update($user, $values);
                if ($values['role'] === UsersRepository::ROLE_USER) {
                    $this->adminUserGroupsRepository->removeGroupsForUser($user);
                }
                if (isset($values['password'])) {
                    $this->changePasswordsLogsRepository->add(
                        $user,
                        ChangePasswordsLogsRepository::TYPE_RESET,
                        $oldPasswordHash,
                        $values['password'],
                    );

                    $this->emitter->emit(new UserChangePasswordEvent($user, $newPassword));
                }
                $this->onCallback = function () use ($form, $user) {
                    $this->onUpdate->__invoke($form, $user);
                };
            } catch (UserAlreadyExistsException $e) {
                $form['email']->addError($e->getMessage());
            }
        } else {
            $user = $this->userBuilder->createNew()
                ->setEmail($values['email'])
                ->setPassword($values['password'])
                ->setFirstName($values['first_name'])
                ->setLastName($values['last_name'])
                ->setPublicName($values['public_name'])
                ->setRole($values['role'])
                ->setActive($values['active'])
                ->setExtId($values['ext_id'])
                ->setInstitutionName($values['institution_name'])
                ->setIsInstitution($values['is_institution'])
                ->setSource($values['source'])
                ->save();

            if (!$user) {
                $form['email']->addError(implode("\n", $this->userBuilder->getErrors()));
            } else {
                $this->onCallback = function () use ($form, $user) {
                    $this->onSave->__invoke($form, $user);
                };
            }
        }
    }

    public function callback()
    {
        if ($this->onCallback) {
            $this->onCallback->__invoke();
        }
    }
}
