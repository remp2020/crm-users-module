<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class UsersClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserMetaRepository $userMetaRepository,
        private AddressesRepository $addressesRepository,
        private AddressChangeRequestsRepository $addressChangeRequestsRepository,
    ) {
    }

    public function provide(array $params): void
    {
        if (!isset($params['unclaimedUser'])) {
            throw new DataProviderException('unclaimedUser param missing');
        }
        if (!isset($params['loggedUser'])) {
            throw new DataProviderException('loggedUser param missing');
        }

        $unclaimedUserMetas = $this->userMetaRepository->userMetaRows($params['unclaimedUser'])->fetchAll();
        foreach ($unclaimedUserMetas as $unclaimedUserMeta) {
            if ($unclaimedUserMeta->key === UnclaimedUser::META_KEY) {
                continue;
            }
            if ($this->userMetaRepository->exists($params['loggedUser'], $unclaimedUserMeta->key)) {
                // don't overwrite logged user settings, if there are some
                $this->userMetaRepository->delete($unclaimedUserMeta);
                continue;
            }
            $this->userMetaRepository->update($unclaimedUserMeta, ['user_id' => $params['loggedUser']->id]);
        }

        $addresses = $this->addressesRepository->getTable()
            ->where('user_id = ?', $params['unclaimedUser']->id);

        foreach ($addresses as $address) {
            $this->addressesRepository->update($address, [
                'user_id' => $params['loggedUser']->id,
            ]);
        }

        $addressChangeRequests = $this->addressChangeRequestsRepository->getTable()
            ->where('user_id = ?', $params['unclaimedUser']->id);

        foreach ($addressChangeRequests as $addressChangeRequest) {
            $this->addressChangeRequestsRepository->update($addressChangeRequest, [
                'user_id' => $params['loggedUser']->id,
            ]);
        }

        // trim - if any of the notes is null or empty
        $mergedNote = trim($params['loggedUser']->note . "\n" . $params['unclaimedUser']->note);
        $mergedNote = empty($mergedNote) ? null : $mergedNote;

        $this->usersRepository->update($params['loggedUser'], ['note' => $mergedNote]);
    }
}
