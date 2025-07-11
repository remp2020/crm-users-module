<?php

namespace Crm\UsersModule\Components\UserLoginAttempts;

use Crm\ApiModule\Repositories\UserSourceAccessesRepository;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\DetailWidgetInterface;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * This component fetches user login attempts
 * and render bootstrap styled table.
 *
 * @package Crm\UsersModule\Components
 */
class UserLoginAttempts extends BaseLazyWidget implements DetailWidgetInterface
{
    private $templateName = 'user_login_attempts.latte';

    private $loginAttemptsRepository;

    private $userSourceAccessesRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        LoginAttemptsRepository $loginAttemptsRepository,
        UserSourceAccessesRepository $userSourceAccessesRepository,
        Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->userSourceAccessesRepository = $userSourceAccessesRepository;
        $this->translator = $translator;
    }

    public function header($id = ''): string
    {
        $header = $this->translator->translate('users.component.user_login_attempts.title');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }

        $today = $this->loginAttemptsRepository->lastUserAttempt($id)->where([
            'created_at > ?' => DateTime::from(strtotime('today 00:00')),
            'status' => $this->loginAttemptsRepository->okStatuses(),
        ])->count('*');
        if ($today) {
            $header .= ' <span class="label label-warning">' . $this->translator->translate('users.component.user_login_attempts.today') . '</span>';
        }

        return $header;
    }

    public function identifier()
    {
        return 'userloginattempts';
    }

    public function render($id)
    {
        $this->template->lastSignInAttempts = $this->loginAttemptsRepository->lastUserAttempt($id);
        $this->template->isOkStatus = function ($status) {
            return $this->loginAttemptsRepository->okStatus($status);
        };
        $this->template->totalSignInAttempts = $this->totalCount($id);

        $this->template->totalUserIps = $this->loginAttemptsRepository->userIps($id)->count();
        $this->template->totalUserAgents = $this->loginAttemptsRepository->userAgents($id)->count();

        $this->template->mobileUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source != ?' => 'web'])->count();
        $this->template->mobileUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source != ?' => 'web'])->count();

        $this->template->webUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source' => 'web'])->count();
        $this->template->webUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source' => 'web'])->count();

        $this->template->userSourceAccesses = $this->userSourceAccessesRepository->getTable()->where(['user_id' => $id]);

        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->loginAttemptsRepository->totalUserAttempts($id);
        }
        return $this->totalCount;
    }
}
