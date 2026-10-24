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

    private ?int $statsDays = null;

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

    /**
     * Sets how many days back IP and user agent counts are calculated.
     * If not set, counts are calculated from all login attempts.
     */
    public function setStatsDays(int $days): void
    {
        if ($days < 1) {
            throw new \InvalidArgumentException("Number of days must be a positive integer, {$days} given.");
        }
        $this->statsDays = $days;
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

        $statsConditions = [];
        if ($this->statsDays !== null) {
            $statsConditions['created_at >= ?'] = DateTime::from("-{$this->statsDays} days");
        }
        $this->template->statsDays = $this->statsDays;

        $this->template->totalUserIps = $this->loginAttemptsRepository->userIps($id)->where($statsConditions)->count();
        $this->template->totalUserAgents = $this->loginAttemptsRepository->userAgents($id)->where($statsConditions)->count();

        $this->template->mobileUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source != ?' => 'web'] + $statsConditions)->count();
        $this->template->mobileUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source != ?' => 'web'] + $statsConditions)->count();

        $this->template->webUserIps = $this->loginAttemptsRepository->userIps($id)->where(['source' => 'web'] + $statsConditions)->count();
        $this->template->webUserAgents = $this->loginAttemptsRepository->userAgents($id)->where(['source' => 'web'] + $statsConditions)->count();

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
