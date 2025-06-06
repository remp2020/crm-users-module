<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\DataProviders\AddressFormDataProviderInterface;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AddressFormFactory
{
    public $onSave;

    public $onUpdate;

    private $address;

    public function __construct(
        private readonly UsersRepository $userRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly AddressTypesRepository $addressTypesRepository,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly Translator $translator,
        private readonly DataProviderManager $dataProviderManager,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
    ) {
    }

    public function create($addressId, $userId): Form
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $defaults = [];
        $address = $this->addressesRepository->find($addressId);

        if ($addressId) {
            $defaults = $address->toArray();
            if ($defaults['country_id']) {
                $defaults['country'] = $address->country->iso_code;
            } else {
                $defaults['country'] = $this->countriesRepository->defaultCountry()->iso_code;
            }
            $userId = $address->user_id;
        } else {
            $defaults['country'] = $this->countriesRepository->defaultCountry()->iso_code;
            $userRow = $this->userRepository->find($userId);
            $defaults['first_name'] = $userRow->first_name;
            $defaults['last_name'] = $userRow->last_name;
        }

        $type = $form->addSelect('type', 'users.frontend.address.type.label', $this->addressTypesRepository->getPairs());
        if ($addressId) {
            $type->setDisabled(true);
        }

        $form->addText('first_name', 'users.frontend.address.first_name.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.first_name.placeholder')
            ->setOption('id', 'first_name');
        $form->addText('last_name', 'users.frontend.address.last_name.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.first_name.placeholder')
            ->setOption('id', 'last_name');

        $form->addTextArea('company_name', 'users.frontend.address.company_name.label', null, 1)
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.company_name.placeholder')
            ->setMaxLength(150)
            ->setOption('id', 'company_name');

        $form->addText('phone_number', 'users.frontend.address.phone_number.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.phone_number.placeholder');
        $form->addText('street', 'users.frontend.address.street.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.street.placeholder');
        $form->addText('number', 'users.frontend.address.number.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.number.placeholder');
        $form->addText('zip', 'users.frontend.address.zip.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.zip.placeholder');
        $form->addText('city', 'users.frontend.address.city.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.city.placeholder');
        $form->addSelect('country', 'users.frontend.address.country.label', $this->countriesSelectItemsBuilder->getAllIsoPairs());

        $form->addText('company_id', 'users.frontend.address.company_id.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.company_id.placeholder');
        $form->addText('company_tax_id', 'users.frontend.address.company_tax_id.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.company_tax_id.placeholder');
        $form->addText('company_vat_id', 'users.frontend.address.company_vat_id.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'users.frontend.address.company_vat_id.placeholder');

        if ($userId) {
            $form->addHidden('user_id', $userId);
        }
        if ($addressId) {
            $form->addHidden('id', $addressId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        /** @var AddressFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.address_form', AddressFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'address' => $address]);
        }

        $form->addSubmit('send', 'users.frontend.address.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.frontend.address.submit'));

        $form->onSuccess[] = [$this, 'formSucceededAfterProviders'];

        return $form;
    }

    public function formSucceeded(Form $form, $values): void
    {
        $user = $this->userRepository->find($values->user_id);
        $address = null;

        if (isset($values->id)) {
            $address = $this->addressesRepository->find($values->id);
        };

        $country = $this->countriesRepository->findByIsoCode($values->country);

        $changeRequest = $this->addressChangeRequestsRepository->add(
            user: $user,
            parentAddress: $address,
            firstName: $values->first_name,
            lastName: $values->last_name,
            companyName: $values->company_name,
            street: $values->street,
            number: $values->number,
            city: $values->city,
            zip: $values->zip,
            countryId: $country->id,
            companyId: $values->company_id,
            companyTaxId: $values->company_tax_id,
            companyVatId: $values->company_vat_id,
            phoneNumber: $values->phone_number,
            type: $values->type ?? null,
        );

        if ($changeRequest) {
            $address = $this->addressChangeRequestsRepository->acceptRequest($changeRequest, true);
        }

        // pass newly created address ID to next formSucceeded handlers via hidden input
        if (!isset($values->id)) {
            $form->addHidden('id', $address->id);
            $form->setValues(['id' => $address->id]);
        }

        $this->address = $address;
    }

    public function formSucceededAfterProviders(Form $form, $values): void
    {
        if (isset($values->id)) {
            $this->onUpdate->__invoke($form, $this->address);
        } else {
            $this->onSave->__invoke($form, $this->address);
        }
    }
}
