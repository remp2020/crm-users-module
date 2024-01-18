<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\UsersModule\Forms\AddressFormFactory;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;

class AddressAdminPresenter extends AdminPresenter
{
    private $addressesRepository;

    private $addressFormFactory;

    private $usersRepository;

    public function __construct(
        AddressesRepository $addressesRepository,
        AddressFormFactory $addressFormFactory,
        UsersRepository $usersRepository
    ) {
        parent::__construct();
        $this->addressesRepository = $addressesRepository;
        $this->addressFormFactory = $addressFormFactory;
        $this->usersRepository = $usersRepository;
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $address = $this->addressesRepository->find($id);
        if (!$address) {
            throw new BadRequestException();
        }
        $this->template->address = $address;
        $this->template->user = $address->user;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew($userId)
    {
        $this->template->user = $this->usersRepository->find($userId);
    }

    public function createComponentAddressForm()
    {
        $addressId = null;
        if (isset($this->params['id'])) {
            $addressId = $this->params['id'];
        }

        $userId = null;
        if (isset($this->params['userId'])) {
            $userId = $this->params['userId'];
        }

        $form = $this->addressFormFactory->create($addressId, $userId);
        $this->addressFormFactory->onSave = function ($form, $address) {
            $this->flashMessage('Adresa bola vytvorená.');
            $this->redirect('UsersAdmin:Show', $address->user->id);
        };
        $this->addressFormFactory->onUpdate = function ($form, $address) {
            $this->flashMessage('Adresa bola aktualizovaná.');
            $this->redirect('UsersAdmin:Show', $address->user->id);
        };
        return $form;
    }
}
