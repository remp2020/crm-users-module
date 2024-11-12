<?php

namespace Crm\UsersModule\Helpers;

use Latte\ContentType;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Latte\Runtime\FilterInfo;

class GravatarHelper
{
    public function process(FilterInfo $info, $email, $size = 40)
    {
        $info->contentType = ContentType::Html;

        $hash = md5($email); // @phpstan-ignore-line
        $gravatarPath = 'avatar/' . $hash . '?s=' . $size . '&d=identicon';
        $data = [
            'class' => 'avatar',
            'src' => "https://www.gravatar.com/$gravatarPath",
            'alt' => $email,
        ];

        $template = '<img class="{$class}" alt="{$alt}" src="{$src}"></img>';

        $latte = new Engine();
        $latte->setLoader(new StringLoader());
        return $latte->renderToString($template, $data);
    }
}
