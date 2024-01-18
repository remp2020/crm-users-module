<?php

namespace Crm\UsersModule\Components\AutologinTokens;

use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\UsersModule\Repositories\AutoLoginTokensRepository;
use Nette\Application\UI\Control;

/**
 * This widget fetches autologin tokens for specific user
 * and renders bootstrap table listing.
 *
 * @package Crm\UsersModule\Components
 */
class AutologinTokens extends Control implements WidgetInterface
{
    private $templateName = 'autologin_tokens.latte';

    /**
     * @var AutoLoginTokensRepository
     */
    public $autoLoginTokensRepository;

    public function __construct(AutoLoginTokensRepository $autoLoginTokensRepository)
    {
        $this->autoLoginTokensRepository = $autoLoginTokensRepository;
    }

    public function header($id = '')
    {
        $header = 'Autologin tokens';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'userautologintokens';
    }

    public function render($id)
    {
        $this->template->autologinTokens = $this->autoLoginTokensRepository->userTokens($id);
        $this->template->totalAutologinTokens = $this->totalCount($id);
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->autoLoginTokensRepository->userTokens($id)->count('*');
        }
        return $this->totalCount;
    }
}
