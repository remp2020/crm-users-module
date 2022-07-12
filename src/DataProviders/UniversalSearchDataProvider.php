<?php

namespace Crm\UsersModule\DataProvider;

use Crm\AdminModule\Model\UniversalSearchDataProviderInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Application\LinkGenerator;

class UniversalSearchDataProvider implements UniversalSearchDataProviderInterface
{
    private UsersRepository $usersRepository;
    private LinkGenerator $linkGenerator;
    private Translator $translator;

    public function __construct(
        UsersRepository $usersRepository,
        LinkGenerator $linkGenerator,
        Translator $translator
    ) {
        $this->usersRepository = $usersRepository;
        $this->linkGenerator = $linkGenerator;
        $this->translator = $translator;
    }

    public function provide(array $params): array
    {
        $result = [];
        $term = $params['term'];

        if (is_numeric($term)) {
            $user = $this->usersRepository->find($term);
            if ($user) {
                $result = $this->addUserToGroup($user, $result);
            }
        }
        if (strlen($term) >= 3) {
            $users = $this->usersRepository->getTable()->where('email LIKE ?', "{$term}%")
                ->limit(15)->fetchAll();
            foreach ($users as $user) {
                $result = $this->addUserToGroup($user, $result);
            }
        }

        return $result;
    }

    private function addUserToGroup($user, $result)
    {
        $result[$this->translator->translate('users.data_provider.universal_search.user_group')][] = [
            'id' => 'user_' . $user->id,
            'text' => $user->email,
            'url' => $this->linkGenerator->link('Users:UsersAdmin:show', ['id' => $user->id])
        ];

        return $result;
    }
}
