<?php

namespace Crm\UsersModule\Components\AddressWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;

/**
 * This widget renders simple address detail from given address.
 *
 * @package Crm\UsersModule\Components
 */
class AddressWidget extends BaseLazyWidget
{
    private $templateName = 'address_widget.latte';

    public function header($id = '')
    {
        return 'Address';
    }

    public function identifier()
    {
        return 'address';
    }

    public function render($address)
    {
        $this->template->address = $address;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
