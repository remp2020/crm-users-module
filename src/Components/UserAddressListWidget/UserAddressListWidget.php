<?php

namespace Crm\UsersModule\Components\UserAddressListWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Models\Widget\WidgetInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
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

        $defaultAddresses = $this->addressesRepository->userAddresses($user, $this->config->getAlwaysVisibleTypes())
            ->fetchAll();

        $allAddresses = $this->addressesRepository->addresses($user);
        $hasMoreAddresses = count($allAddresses) > count($defaultAddresses);

        $this->template->addresses = $defaultAddresses;
        $this->template->additionalAddresses = $this->additionalAddresses;
        $this->template->hasMoreAddresses = $hasMoreAddresses && empty($this->additionalAddresses);
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

        $allAddresses = $this->addressesRepository->addresses($user);

        $additionalAddresses = [];
        foreach ($allAddresses as $address) {
            if (!in_array($address->type, $this->config->getAlwaysVisibleTypes(), true)) {
                $additionalAddresses[] = $address;
            }
        }

        $this->additionalAddresses = $additionalAddresses;

        if ($this->presenter->isAjax()) {
            $this->redrawControl('additionalAddresses');
            $this->redrawControl('loadMoreButton');
        }
    }
}
