<?php

namespace Crm\UsersModule\Helpers;

use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Nette\Localization\Translator;

class UserLabelHelper
{
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function process(FilterInfo $filterInfo, $user)
    {
        $filterInfo->contentType = ContentType::Html;

        $append = '';
        if ($user->is_institution) {
            $append .= " <small>{$this->translator->translate('users.admin.default.institution')}: {$user->institution_name}</small>";
        }

        return $user->email . $append;
    }
}
