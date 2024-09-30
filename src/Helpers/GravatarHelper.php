<?php

namespace Crm\UsersModule\Helpers;

use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Nette\Utils\Html;

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

        return Html::el('img', $data)->render();
    }
}
