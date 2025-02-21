<?php

namespace Crm\UsersModule;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\CallbackManagerInterface;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Events\AuthenticationEvent;
use Crm\ApplicationModule\Events\FrontendRequestEvent;
use Crm\ApplicationModule\Models\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\ApplicationModule\Models\User\UserDataRegistrator;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManagerInterface;
use Crm\UsersModule\Api\AddressesHandler;
use Crm\UsersModule\Api\AppleTokenSignInHandler;
use Crm\UsersModule\Api\AutoLoginTokenLoginApiHandler;
use Crm\UsersModule\Api\CreateAddressChangeRequestHandler;
use Crm\UsersModule\Api\CreateAddressHandler;
use Crm\UsersModule\Api\DeleteUserApiHandler;
use Crm\UsersModule\Api\EmailValidationApiHandler;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Api\GoogleTokenSignInHandler;
use Crm\UsersModule\Api\ListUsersHandler;
use Crm\UsersModule\Api\UserAddressesHandler;
use Crm\UsersModule\Api\UserDataHandler;
use Crm\UsersModule\Api\UserGroupApiHandler;
use Crm\UsersModule\Api\UserInfoHandler;
use Crm\UsersModule\Api\UserMetaDeleteHandler;
use Crm\UsersModule\Api\UserMetaKeyUsersHandler;
use Crm\UsersModule\Api\UserMetaListHandler;
use Crm\UsersModule\Api\UserMetaUpsertHandler;
use Crm\UsersModule\Api\UsersConfirmApiHandler;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Api\UsersEmailCheckHandler;
use Crm\UsersModule\Api\UsersEmailHandler;
use Crm\UsersModule\Api\UsersLoginHandler;
use Crm\UsersModule\Api\UsersLogoutHandler;
use Crm\UsersModule\Api\UsersTouchApiHandler;
use Crm\UsersModule\Api\UsersUpdateHandler;
use Crm\UsersModule\Api\v2\EmailValidationApiHandler as EmailValidationApiHandlerV2;
use Crm\UsersModule\Authenticator\AccessTokenAuthenticator;
use Crm\UsersModule\Authenticator\AutoLoginAuthenticator;
use Crm\UsersModule\Authenticator\AutoLoginTokenAuthenticator;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Commands\AddUserCommand;
use Crm\UsersModule\Commands\CheckEmailsCommand;
use Crm\UsersModule\Commands\DisableUserCommand;
use Crm\UsersModule\Commands\GenerateAccessCommand;
use Crm\UsersModule\Commands\GenerateUuidForUsersCommand;
use Crm\UsersModule\Commands\ReconstructUserDataCommand;
use Crm\UsersModule\Commands\UpdateLoginAttemptsCommand;
use Crm\UsersModule\Components\ActiveRegisteredUsersStatWidget\ActiveRegisteredUsersStatWidget;
use Crm\UsersModule\Components\AddressWidget\AddressWidget;
use Crm\UsersModule\Components\AutologinTokens\AutologinTokens;
use Crm\UsersModule\Components\MonthToDateUsersStatWidget\MonthToDateUsersStatWidget;
use Crm\UsersModule\Components\MonthUsersSmallBarGraphWidget\MonthUsersSmallBarGraphWidget;
use Crm\UsersModule\Components\MonthUsersStatWidget\MonthUsersStatWidget;
use Crm\UsersModule\Components\TodayUsersStatWidget\TodayUsersStatWidget;
use Crm\UsersModule\Components\UserConnectedAccountsListWidget\UserConnectedAccountsListWidget;
use Crm\UsersModule\Components\UserLoginAttempts\UserLoginAttempts;
use Crm\UsersModule\Components\UserMeta\UserMeta;
use Crm\UsersModule\Components\UserPasswordChanges\UserPasswordChanges;
use Crm\UsersModule\Components\UserSourceAccesses\UserSourceAccesses;
use Crm\UsersModule\Components\UserTokens\UserTokens;
use Crm\UsersModule\DataProviders\AddressesUserDataProvider;
use Crm\UsersModule\DataProviders\AdminUserGroupsUserDataProvider;
use Crm\UsersModule\DataProviders\AutoLoginTokensUserDataProvider;
use Crm\UsersModule\DataProviders\BasicUserDataProvider;
use Crm\UsersModule\DataProviders\LoginAttemptsUserDataProvider;
use Crm\UsersModule\DataProviders\UniversalSearchDataProvider;
use Crm\UsersModule\DataProviders\UserConnectedAccountsDataProvider;
use Crm\UsersModule\DataProviders\UserMetaUserDataProvider;
use Crm\UsersModule\DataProviders\UserStatsUserDataProvider;
use Crm\UsersModule\DataProviders\UsersClaimUserDataProvider;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\AuthenticationHandler;
use Crm\UsersModule\Events\FrontendRequestAccessTokenAutologinHandler;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Events\LoginAttemptHandler;
use Crm\UsersModule\Events\NewAccessTokenEvent;
use Crm\UsersModule\Events\NewAccessTokenHandler;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\RefreshUserDataTokenHandler;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use Crm\UsersModule\Events\RemovedAccessTokenHandler;
use Crm\UsersModule\Events\SignEventHandler;
use Crm\UsersModule\Events\UserChangePasswordEvent;
use Crm\UsersModule\Events\UserChangePasswordRequestEvent;
use Crm\UsersModule\Events\UserConfirmedEvent;
use Crm\UsersModule\Events\UserDisabledEvent;
use Crm\UsersModule\Events\UserEnabledEvent;
use Crm\UsersModule\Events\UserLastAccessEvent;
use Crm\UsersModule\Events\UserLastAccessHandler;
use Crm\UsersModule\Events\UserMetaEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Events\UserResetPasswordEvent;
use Crm\UsersModule\Events\UserSignInEvent;
use Crm\UsersModule\Events\UserSignOutEvent;
use Crm\UsersModule\Events\UserSuspiciousEvent;
use Crm\UsersModule\Events\UserUpdatedEvent;
use Crm\UsersModule\Events\UserUpdatedHandler;
use Crm\UsersModule\Hermes\UserTokenUsageHandler;
use Crm\UsersModule\Models\Auth\Permissions;
use Crm\UsersModule\Models\Auth\ServiceTokenAuthorization;
use Crm\UsersModule\Models\Auth\UserTokenAuthorization;
use Crm\UsersModule\Repositories\AutoLoginTokensRepository;
use Crm\UsersModule\Repositories\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repositories\UserActionsLogRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Scenarios\AddressScenarioConditionModel;
use Crm\UsersModule\Scenarios\AddressTypeCriteria;
use Crm\UsersModule\Scenarios\IsConfirmedCriteria;
use Crm\UsersModule\Scenarios\LocaleCriteria;
use Crm\UsersModule\Scenarios\UserCreatedAtCriteria;
use Crm\UsersModule\Scenarios\UserGroupsCriteria;
use Crm\UsersModule\Scenarios\UserHasAddressCriteria;
use Crm\UsersModule\Scenarios\UserScenarioConditionModel;
use Crm\UsersModule\Scenarios\UserSourceCriteria;
use Crm\UsersModule\Seeders\ConfigsSeeder;
use Crm\UsersModule\Seeders\MeasurementsSeeder;
use Crm\UsersModule\Seeders\SegmentsSeeder;
use Crm\UsersModule\Seeders\SnippetsSeeder;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\Segment\ActiveCriteria;
use Crm\UsersModule\Segment\CreatedCriteria;
use Crm\UsersModule\Segment\DeletedCriteria;
use Crm\UsersModule\Segment\EmailCriteria;
use Crm\UsersModule\Segment\GroupCriteria;
use Crm\UsersModule\Segment\SourceAccessCriteria;
use Crm\UsersModule\Segment\SourceCriteria;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Nette\Security\User;
use Tomaj\Hermes\Dispatcher;

class UsersModule extends CrmModule
{
    public function __construct(
        Container $container,
        Translator $translator,
        private User $user,
        private Permissions $permissions
    ) {
        parent::__construct($container, $translator);
    }

    public function registerAuthenticators(AuthenticatorManagerInterface $authenticatorManager)
    {
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(AutoLoginAuthenticator::class),
            700
        );
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(UsersAuthenticator::class),
            500
        );
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(AccessTokenAuthenticator::class),
            200
        );
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(AutoLoginTokenAuthenticator::class),
            800
        );
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'user-token-usage',
            $this->getInstance(UserTokenUsageHandler::class)
        );
    }


    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem($this->translator->translate('users.menu.people'), ':Users:UsersAdmin:', 'fa fa-users', 10, true);

        $menuItem1 = new MenuItem($this->translator->translate('users.menu.users'), ':Users:UsersAdmin:', 'fa fa-user', 50, true);
        $menuItem2 = new MenuItem($this->translator->translate('users.menu.groups'), ':Users:GroupsAdmin:', 'fa fa-users', 80, true);
        $menuItem3 = new MenuItem($this->translator->translate('users.menu.login_attempts'), ':Users:LoginAttemptsAdmin:', 'fa fa-hand-paper', 85, true);
        $menuItem4 = new MenuItem($this->translator->translate('users.menu.events'), ':Users:UserActionsLogAdmin:', 'fa fa-user-clock', 87, true);
        $menuItem5 = new MenuItem($this->translator->translate('users.menu.cheaters'), ':Users:AbusiveUsersAdmin:default', 'fa fa-frown', 90, true);
        $menuItem6 = new MenuItem($this->translator->translate('users.menu.admin_rights'), ':Users:AdminGroupAdmin:', 'fa fa-lock', 100, true);

        $mainMenu->addChild($menuItem1);
        $mainMenu->addChild($menuItem2);
        $mainMenu->addChild($menuItem3);
        $mainMenu->addChild($menuItem4);
        $mainMenu->addChild($menuItem5);
        $mainMenu->addChild($menuItem6);

        $menuContainer->attachMenuItem($mainMenu);

        // dashboard menu item

        $menuItem = new MenuItem(
            $this->translator->translate('users.menu.stats'),
            ':Users:Dashboard:default',
            'fa fa-users',
            200
        );
        $menuContainer->attachMenuItemToForeignModule('#dashboard', $mainMenu, $menuItem);
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('users.menu.settings'), ':Users:Users:settings', '', 850, true);
        $menuContainer->attachMenuItem($menuItem);

        $menuItem = new MenuItem($this->translator->translate('users.menu.sign_out'), ':Users:Sign:out', '', 4999, true);
        $menuContainer->attachMenuItem($menuItem);

        if ($this->user->isLoggedIn() && $this->user->getIdentity()->role === UsersRepository::ROLE_ADMIN) {
            $links = [
                'Users:UsersAdmin' => 'default',
//                'Content:Content' => 'default',
                'Dashboard:Dashboard' => 'default',
                'Invoices:InvoicesAdmin' => 'default',
            ];
            foreach ($this->user->getRoles() as $role) {
                foreach ($links as $key => $value) {
                    if ($this->permissions->allowed($role, $key, $value)) {
                        $menuItem = new MenuItem('ADMIN', ":{$key}:{$value}", '', 15000, true, ['target' => '_top']);
                        $menuContainer->attachMenuItem($menuItem);

                        // pozor je tu return
                        return;
                    }
                }
            }
        }
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            LoginAttemptEvent::class,
            LoginAttemptHandler::class
        );
        $emitter->addListener(
            UserLastAccessEvent::class,
            UserLastAccessHandler::class
        );
        $emitter->addListener(
            UserUpdatedEvent::class,
            RefreshUserDataTokenHandler::class
        );
        $emitter->addListener(
            UserUpdatedEvent::class,
            UserUpdatedHandler::class
        );
        $emitter->addListener(
            UserMetaEvent::class,
            RefreshUserDataTokenHandler::class
        );
        $emitter->addListener(
            UserSignInEvent::class,
            SignEventHandler::class
        );
        $emitter->addListener(
            UserSignOutEvent::class,
            SignEventHandler::class
        );
        $emitter->addListener(
            AuthenticationEvent::class,
            AuthenticationHandler::class
        );
        $emitter->addListener(
            NewAccessTokenEvent::class,
            NewAccessTokenHandler::class
        );
        $emitter->addListener(
            RemovedAccessTokenEvent::class,
            RemovedAccessTokenHandler::class
        );
        $emitter->addListener(
            FrontendRequestEvent::class,
            FrontendRequestAccessTokenAutologinHandler::class
        );
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(AddUserCommand::class));
        $commandsContainer->registerCommand($this->getInstance(GenerateAccessCommand::class));
        $commandsContainer->registerCommand($this->getInstance(UpdateLoginAttemptsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(CheckEmailsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(DisableUserCommand::class));
        $commandsContainer->registerCommand($this->getInstance(ReconstructUserDataCommand::class));
        $commandsContainer->registerCommand($this->getInstance(GenerateUuidForUsersCommand::class));
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserLoginAttempts::class,
            710
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserPasswordChanges::class,
            1700
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            AutologinTokens::class,
            1900
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserMeta::class,
            960
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserTokens::class,
            1235
        );

        $widgetManager->registerWidget(
            'admin.user.detail.box',
            UserSourceAccesses::class,
            580
        );
        $widgetManager->registerWidget(
            'admin.user.detail.left',
            UserConnectedAccountsListWidget::class,
            710
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.totals',
            ActiveRegisteredUsersStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.today',
            TodayUsersStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.month',
            MonthUsersStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.mtd',
            MonthToDateUsersStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'admin.users.header',
            MonthUsersSmallBarGraphWidget::class,
            500
        );

        $widgetManager->registerWidget(
            'admin.user.address.partial',
            AddressWidget::class,
            100
        );

        $widgetManager->registerWidget(
            'frontend.user.address.partial',
            AddressWidget::class,
            100
        );
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('user', 'source', $this->getInstance(UserSourceCriteria::class));
        $scenariosCriteriaStorage->register('user', UserGroupsCriteria::KEY, $this->getInstance(UserGroupsCriteria::class));
        $scenariosCriteriaStorage->register('user', UserHasAddressCriteria::KEY, $this->getInstance(UserHasAddressCriteria::class));
        $scenariosCriteriaStorage->register('user', IsConfirmedCriteria::KEY, $this->getInstance(IsConfirmedCriteria::class));
        $scenariosCriteriaStorage->register('address', AddressTypeCriteria::KEY, $this->getInstance(AddressTypeCriteria::class));
        $scenariosCriteriaStorage->register('user', LocaleCriteria::KEY, $this->getInstance(LocaleCriteria::class));
        $scenariosCriteriaStorage->register('user', UserCreatedAtCriteria::KEY, $this->getInstance(UserCreatedAtCriteria::class));

        $scenariosCriteriaStorage->registerConditionModel(
            'address',
            $this->getInstance(AddressScenarioConditionModel::class)
        );
        $scenariosCriteriaStorage->registerConditionModel(
            'user',
            $this->getInstance(UserScenarioConditionModel::class)
        );
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user', 'info'), UserInfoHandler::class, UserTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'login'), UsersLoginHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'logout'), UsersLogoutHandler::class, UserTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'email'), UsersEmailHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('2', 'users', 'email'), \Crm\UsersModule\Api\v2\UsersEmailHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'email-check'), UsersEmailCheckHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'create'), UsersCreateHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'update'), UsersUpdateHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'add-to-group'), UserGroupApiHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'remove-from-group'), UserGroupApiHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'addresses'), AddressesHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'address'), CreateAddressHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'change-address-request'), CreateAddressChangeRequestHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'list'), ListUsersHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'confirm'), UsersConfirmApiHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user-meta', 'list'), UserMetaListHandler::class, ServiceTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user-meta', 'key-users'), UserMetaKeyUsersHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user-meta', 'delete'), UserMetaDeleteHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user-meta', 'upsert'), UserMetaUpsertHandler::class, BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'autologin-token-login'), AutoLoginTokenLoginApiHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'google-token-sign-in'), GoogleTokenSignInHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'apple-token-sign-in'), AppleTokenSignInHandler::class, NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user', 'delete'), DeleteUserApiHandler::class, UserTokenAuthorization::class)
        );

        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'users', 'data'),
            UserDataHandler::class,
            NoAuthorization::class
        ));

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'get-device-token'),
                GetDeviceTokenApiHandler::class,
                NoAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'set-email-validated'),
                EmailValidationApiHandler::class,
                BearerTokenAuthorization::class
            )
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'set-email-invalidated'),
                EmailValidationApiHandler::class,
                BearerTokenAuthorization::class
            )
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('2', 'users', 'set-email-validated'),
                EmailValidationApiHandlerV2::class,
                BearerTokenAuthorization::class
            )
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('2', 'users', 'set-email-invalidated'),
                EmailValidationApiHandlerV2::class,
                BearerTokenAuthorization::class
            )
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'touch'),
                UsersTouchApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user', 'addresses'),
                UserAddressesHandler::class,
                UserTokenAuthorization::class
            )
        );
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(BasicUserDataProvider::class), 10); // low priority; should be executed last
        $dataRegistrator->addUserDataProvider($this->getInstance(AddressesUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(AutoLoginTokensUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(UserMetaUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(UserStatsUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(AdminUserGroupsUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(UserConnectedAccountsDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(LoginAttemptsUserDataProvider::class));
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        $criteriaStorage->register('users', 'active', $this->getInstance(ActiveCriteria::class));
        $criteriaStorage->register('users', 'deleted', $this->getInstance(DeletedCriteria::class));
        $criteriaStorage->register('users', 'source', $this->getInstance(SourceCriteria::class));
        $criteriaStorage->register('users', 'source_access', $this->getInstance(SourceAccessCriteria::class));
        $criteriaStorage->register('users', 'email', $this->getInstance(EmailCriteria::class));
        $criteriaStorage->register('users', 'created', $this->getInstance(CreatedCriteria::class));
        $criteriaStorage->register('users', 'group', $this->getInstance(GroupCriteria::class));

        $criteriaStorage->setDefaultFields('users', ['id', 'email']);
        $criteriaStorage->setFields('users', [
            'first_name',
            'last_name',
            'public_name',
            'role',
            'active',
            'source',
            'confirmed_at',
            'email_validated_at',
            'current_sign_in_at',
            'created_at'
        ]);
    }

    public function registerRoutes(RouteList $router)
    {
        $router->addRoute('sign/in/', 'Users:Sign:in');
        $router->addRoute('sign/up/', 'Users:Sign:up');

        $router->addRoute('users/users/request-password', 'Users:Users:settings', Route::ONE_WAY);
        $router->addRoute('users/users/change-password', 'Users:Users:settings', Route::ONE_WAY);
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(UsersSeeder::class));
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
        $seederManager->addSeeder($this->getInstance(SnippetsSeeder::class));
        $seederManager->addSeeder($this->getInstance(MeasurementsSeeder::class));
    }

    public function registerCleanupFunction(CallbackManagerInterface $cleanUpManager)
    {
        $cleanUpManager->add(ChangePasswordsLogsRepository::class, function (Container $container) {
            /** @var ChangePasswordsLogsRepository $changePasswordLogsRepository */
            $changePasswordLogsRepository = $container->getByType(ChangePasswordsLogsRepository::class);
            $changePasswordLogsRepository->removeOldData();
        });
        $cleanUpManager->add(UserActionsLogRepository::class, function (Container $container) {
            /** @var UserActionsLogRepository $userActionsLogRepository */
            $userActionsLogRepository = $container->getByType(UserActionsLogRepository::class);
            $userActionsLogRepository->removeOldData();
        });
        $cleanUpManager->add(AutoLoginTokensRepository::class, function (Container $container) {
            /** @var AutoLoginTokensRepository $tokensRepository */
            $tokensRepository = $container->getByType(AutoLoginTokensRepository::class);
            $tokensRepository->removeOldData();
        });
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('address_changed', AddressChangedEvent::class, true);
        $eventsStorage->register('login_attempt', LoginAttemptEvent::class);
        $eventsStorage->register('new_access_token', NewAccessTokenEvent::class);
        $eventsStorage->register('new_address', NewAddressEvent::class);
        $eventsStorage->register('notification', NotificationEvent::class);
        $eventsStorage->register('removed_access_token', RemovedAccessTokenEvent::class);
        $eventsStorage->register('user_change_password', UserChangePasswordEvent::class);
        $eventsStorage->register('user_change_password_request', UserChangePasswordRequestEvent::class);
        $eventsStorage->register('user_confirmed', UserConfirmedEvent::class);
        $eventsStorage->register('user_registered', UserRegisteredEvent::class, true);
        $eventsStorage->register('user_enabled', UserEnabledEvent::class);
        $eventsStorage->register('user_disabled', UserDisabledEvent::class);
        $eventsStorage->register('user_last_access', UserLastAccessEvent::class);
        $eventsStorage->register('user_meta', UserMetaEvent::class);
        $eventsStorage->register('user_reset_password', UserResetPasswordEvent::class);
        $eventsStorage->register('user_suspicious', UserSuspiciousEvent::class);
        $eventsStorage->register('user_sign_in', UserSignInEvent::class);
        $eventsStorage->register('user_sign_out', UserSignOutEvent::class);
        $eventsStorage->register('user_updated', UserUpdatedEvent::class);
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.claim_unclaimed_user',
            $this->getInstance(UsersClaimUserDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'admin.dataprovider.universal_search',
            $this->getInstance(UniversalSearchDataProvider::class)
        );
    }
}
