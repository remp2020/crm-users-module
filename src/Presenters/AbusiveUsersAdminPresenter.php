<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\FilterAbusiveUserFormDataProviderInterface;
use Crm\UsersModule\Forms\AbusiveUsersFilterFormFactory;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Utils\DateTime;
use PDO;

class AbusiveUsersAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $email;

    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    /** @persistent */
    public $loginCount;

    /** @persistent */
    public $deviceCount;

    /** @persistent */
    public $sortBy;

    /** @var UsersRepository @inject */
    public $usersRepository;

    /** @var AccessTokensRepository @inject */
    public $accessTokensRepository;

    /** @var AbusiveUsersFilterFormFactory @inject */
    public $abusiveUsersFilterFormFactory;

    /** @var DataProviderManager @inject */
    public $dataProviderManager;

    protected $onPage = 100;

    /**
     * @admin-access-level read
     */
    public function renderDefault($dateFrom, $dateTo, $loginCount = 10, $deviceCount = 1, $sortBy = 'device_count', $email = null)
    {
        $this->dateFrom = $dateFrom ?? (new DateTime())->modify('- 2 months')->format('Y-m-d');
        $this->dateTo = $dateTo ?? (new DateTime())->format('Y-m-d');

        // Due to performance optimization (access_token table can grow very large), raw queries are used
        $sql = <<<SQL
SELECT user_id, MD5(user_agent) AS hashed_ua
FROM access_tokens
WHERE last_used_at >= :from AND last_used_at < :to
SQL;

        if ($email) {
            $emailIds = array_values($this->usersRepository->getTable()
                ->where('users.email LIKE ?', "%{$email}%")
                ->fetchAssoc('id=id'));
            $sql .= ' AND user_id IN (' . implode(',', array_map('intval', $emailIds)) . ' )';
        }
        $sql .= " ORDER BY user_id, MD5(user_agent)";

        $pdo = $this->accessTokensRepository->getDatabase()->getConnection()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':from', $this->dateFrom);
        $stmt->bindParam(':to', $this->dateTo);

        $filteredUserDevicesCount = [];
        $filteredUserTokensCount = [];

        if ($stmt->execute()) {
            $userId = 0;
            $userTokenCount = 0;
            $userDevices = [];

            while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                if ($row->user_id !== $userId) {
                    // we have all data of previous user, evaluate them before moving to next
                    if (count($userDevices) >= $deviceCount && $userTokenCount >= $loginCount) {
                        $filteredUserDevicesCount[$userId] = count($userDevices);
                        $filteredUserTokensCount[$userId] = $userTokenCount;
                    }

                    $userId = $row->user_id;
                    $userTokenCount = 0;
                    $userDevices = [];
                }

                $userTokenCount += 1;
                $userDevices[$row->hashed_ua] = true;
            }

            // don't forget to check the last processed user
            if (count($userDevices) >= $deviceCount && $userTokenCount >= $loginCount) {
                $filteredUserDevicesCount[$userId] = count($userDevices);
                $filteredUserTokensCount[$userId] = $userTokenCount;
            }
        }
        $pdo = null;

        // Data provider
        $thresholdsPassedUserIds = array_keys($filteredUserDevicesCount);
        $usersSelection = $this->usersRepository->getTable()
            ->select('users.id')
            ->where('users.id IN (?)', $thresholdsPassedUserIds);
        /** @var FilterAbusiveUserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_abusive_user_form', FilterAbusiveUserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $usersSelection = $provider->filter($usersSelection, $this->params);
        }
        $filteredUserIds = $usersSelection->fetchAssoc('id=id');

        // Order and paginate
        if ($sortBy === 'device_count') {
            $filteredUserDevicesCount = array_intersect_key($filteredUserDevicesCount, $filteredUserIds);
            arsort($filteredUserDevicesCount);
            $filteredUserIds = array_keys($filteredUserDevicesCount);
        } else {
            $filteredUserTokensCount = array_intersect_key($filteredUserTokensCount, $filteredUserIds);
            arsort($filteredUserTokensCount);
            $filteredUserIds = array_keys($filteredUserTokensCount);
        }
        $filteredCount = count($filteredUserIds);


        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $paginatedUserIds = array_slice($filteredUserIds, $paginator->offset, $paginator->getLength());
        $paginatedUsers = [];

        $pnp->setActualItemCount(count($paginatedUserIds));

        $q = $this->usersRepository->getTable()
            ->where('users.id IN (?)', $paginatedUserIds);

        if (count($paginatedUserIds)) {
            // has to be added conditionally (in case of empty array ORDER condition crashes) - Nette doesn't correctly process FIELD
            $q = $q->order('FIELD(id, ?)', $paginatedUserIds);
        }

        foreach ($q as $row) {
            $paginatedUsers[] = (object) array_merge($row->toArray(), [
                'user_row' => $row,
                'token_count' => $filteredUserTokensCount[$row->id],
                'device_count' => $filteredUserDevicesCount[$row->id],
            ]);
        }

        $this->template->filteredCount = $filteredCount;
        $this->template->abusers = $paginatedUsers;
        $this->template->sortByTokenCountLink = $this->link('AbusiveUsersAdmin:default', array_merge($this->getParameters(), ['sortBy' => 'token_count']));
        $this->template->sortByDeviceCountLink = $this->link('AbusiveUsersAdmin:default', array_merge($this->getParameters(), ['sortBy' => 'device_count']));
    }

    public function createComponentAbusiveUsersFilterForm()
    {
        $form = $this->abusiveUsersFilterFormFactory->create();
        $form->setDefaults([
            'email' => $this->email,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'loginCount' => $this->loginCount,
            'deviceCount' => $this->deviceCount,
        ]);

        /** @var FilterAbusiveUserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_abusive_user_form', FilterAbusiveUserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'params' => $this->params]);
        }

        $this->abusiveUsersFilterFormFactory->onCancel = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->redirect($this->action, $emptyDefaults);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];

        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleChangeActivation($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $this->usersRepository->toggleActivation($user);

        $this->flashMessage($this->translator->translate('users.admin.change_activation.activated'));
        $this->redirect('UsersAdmin:Show', $user->id);
    }
}
