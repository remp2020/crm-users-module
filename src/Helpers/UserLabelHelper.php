<?php

namespace Crm\UsersModule\Helpers;

use Latte\ContentType;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Latte\Loaders\StringLoader;
use Latte\Runtime\FilterInfo;
use Nette\Localization\Translator;

class UserLabelHelper
{
    public function __construct(private Translator $translator)
    {
    }

    public function process(FilterInfo $filterInfo, $user)
    {
        $filterInfo->contentType = ContentType::Html;

        $template = '{$email}';
        if ($user->is_institution) {
            $template .= ' <small>{_users.admin.default.institution}: {$institutionName}</small>';
        }

        $latte = new Engine();
        $latte->addExtension(new TranslatorExtension($this->translator));
        $latte->setLoader(new StringLoader());
        return $latte->renderToString($template, [
            'email' => $user->email,
            'institutionName' => $user->institution_name,
        ]);
    }
}
