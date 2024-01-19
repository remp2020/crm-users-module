<?php

namespace Crm\UsersModule\Components\SsoWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;

/**
 * This widget renders SSO login form
 *
 * @package Crm\UsersModule\Components
 */
class SsoWidget extends BaseLazyWidget
{
    private $templateName = 'sso_widget.latte';

    public function identifier()
    {
        return 'ssowidget';
    }

    public function render($url = null)
    {
        return;

        // Currently, Google Sign In button in template is not allowed
        // TODO: enable once SSO button is ready in other templates (and Sign In is enabled in configuration)
//        $this->template->redirectUrl = $url;
        //$this->template->setFile(__DIR__ . '/' . $this->templateName);
        //$this->template->render();
    }
}
