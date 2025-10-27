<?php

namespace Crm\UsersModule\Components\UserAddressListWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;

class UserAddressListWidget extends BaseLazyWidget implements WidgetInterface
{
    private string $templateName = 'user_address_list_widget.latte';

    #[Persistent]
    public int $userId;

    private array $additionalAddresses = [];

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly AddressesRepository $addressesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly Translator $translator,
        private readonly UserAddressListConfig $config,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header(): string
    {
        return $this->translator->translate('users.admin.default.addresses');
    }

    public function identifier(): string
    {
        return 'addresslist';
    }

    public function render($userId = null): void
    {
        if ($userId !== null) {
            $this->userId = $userId;
        }

        $user = $this->usersRepository->find($this->userId);
        if (!$user) {
            throw new BadRequestException('User not found.');
        }

        $visibleAddresses = $this->getInitiallyShownAddresses($user);
        $allAddresses = $this->addressesRepository->addresses($user);
        $hasMoreAddresses = count($allAddresses) > count($visibleAddresses);

        $this->template->addresses = $visibleAddresses;
        $this->template->additionalAddresses = $this->additionalAddresses;
        $this->template->hasMoreAddresses = $hasMoreAddresses && $this->additionalAddresses === [];
        $this->template->userId = $this->userId;
        $this->template->iconMapping = $this->config->getIconMapping();

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleLoadMoreAddresses($userId = null): void
    {
        $userId = $userId ?? $this->userId;

        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException('User not found.');
        }

        $initiallyShownAddresses = $this->getInitiallyShownAddresses($user);
        $allAddresses = $this->addressesRepository->addresses($user);

        $shownIds = array_map(fn ($address) => $address->id, $initiallyShownAddresses);

        $additionalAddresses = [];
        foreach ($allAddresses as $address) {
            if (!in_array($address->id, $shownIds, true)) {
                $additionalAddresses[] = $address;
            }
        }

        $this->additionalAddresses = $this->sortAddresses($additionalAddresses);

        if ($this->presenter->isAjax()) {
            $this->redrawControl('additionalAddresses');
            $this->redrawControl('loadMoreButton');
        }
    }

    private function getInitiallyShownAddresses(ActiveRow $user): array
    {
        $alwaysVisibleAddresses = $this->addressesRepository
            ->userAddresses($user, $this->config->getAlwaysVisibleTypes())
            ->fetchAll();

        $visibleAddresses = $this->sortAddresses($alwaysVisibleAddresses);

        if ($visibleAddresses === []) {
            $allAddresses = $this->addressesRepository->addresses($user);
            if ($allAddresses !== []) {
                $sortedAll = $this->sortAddresses($allAddresses);
                $visibleAddresses = array_slice($sortedAll, 0, 3);
            }
        }

        return $visibleAddresses;
    }

    private function sortAddresses(array $addresses): array
    {
        usort($addresses, function ($a, $b) {
            // First, sort by type
            if ($a->type !== $b->type) {
                return $a->type <=> $b->type;
            }

            // Within same type, sort by is_default (default addresses first)
            if ($a->is_default !== $b->is_default) {
                return $b->is_default <=> $a->is_default;
            }

            // Within same type and default status, sort by created_at (newest first)
            return $b->created_at <=> $a->created_at;
        });
        return $addresses;
    }
}
