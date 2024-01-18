<?php

namespace Crm\UsersModule\Components\Widgets\DetailWidget;

interface DetailWidgetFactoryInterface
{
    public function create(): DetailWidget;
}
