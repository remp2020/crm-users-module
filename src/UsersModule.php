<?php

namespace Crm\UsersModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\CallbackManagerInterface;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\UsersModule\Auth\Permissions;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\ConfigsSeeder;
use Crm\UsersModule\Seeders\SegmentsSeeder;
use Crm\UsersModule\Seeders\UsersSeeder;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Nette\Security\User;
use Tomaj\Hermes\Dispatcher;

class UsersModule extends CrmModule
{
    private $user;

    private $permissions;

    private $usersRepository;

    public function __construct(
        Container $container,
        Translator $translator,
        User $user,
        Permissions $permissions,
        UsersRepository $usersRepository
    ) {
        parent::__construct($container, $translator);
        $this->user = $user;
        $this->permissions = $permissions;
        $this->usersRepository = $usersRepository;
    }

    public function registerAuthenticators(AuthenticatorManagerInterface $authenticatorManager)
    {
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(\Crm\UsersModule\Authenticator\UsersAuthenticator::class),
            500
        );
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'user-token-usage',
            $this->getInstance(\Crm\UsersModule\Hermes\UserTokenUsageHandler::class)
        );
    }


    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem($this->translator->translate('users.menu.people'), ':Users:UsersAdmin:', 'fa fa-users', 10, true);

        $menuItem1 = new MenuItem($this->translator->translate('users.menu.users'), ':Users:UsersAdmin:', 'fa fa-user', 50, true);
        $menuItem2 = new MenuItem($this->translator->translate('users.menu.groups'), ':Users:GroupsAdmin:', 'fa fa-users', 80, true);
        $menuItem3 = new MenuItem($this->translator->translate('users.menu.login_attempts'), ':Users:LoginAttemptsAdmin:', 'fa fa-hand-paper', 85, true);
        $menuItem4 = new MenuItem($this->translator->translate('users.menu.events'), ':Users:UserActionsLogAdmin:', 'fa fa-user-clock', 87, true);
        $menuItem5 = new MenuItem($this->translator->translate('users.menu.cheaters'), ':Users:UsersAdmin:Abusive', 'fa fa-frown', 90, true);
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
        $menuItem = new MenuItem($this->translator->translate('users.menu.change_password'), ':Users:Users:changePassword', '', 800, true);
        $menuContainer->attachMenuItem($menuItem);

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

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\UsersModule\Events\LoginAttemptEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\LoginAttemptHandler::class)
        );
        $emitter->addListener(
            \Crm\MailModule\Events\UserMailEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\UserMailHandler::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\NewAddressEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\UpdateUserNameFromAddress::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\UserLastAccessEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\UserLastAccessHandler::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\UserUpdatedEvent::class,
            $this->getInstance(\Crm\ApplicationModule\Events\RefreshUserDataTokenHandler::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\UserMetaEvent::class,
            $this->getInstance(\Crm\ApplicationModule\Events\RefreshUserDataTokenHandler::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\UserSignInEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\SignEventHandler::class)
        );
        $emitter->addListener(
            \Crm\UsersModule\Events\UserSignOutEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\SignEventHandler::class)
        );
        $emitter->addListener(
            \Crm\ApplicationModule\Events\AuthenticationEvent::class,
            $this->getInstance(\Crm\UsersModule\Events\AuthenticationHandler::class)
        );
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(\Crm\UsersModule\Commands\AddUserCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\UsersModule\Commands\GenerateAccessCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\UsersModule\Commands\UpdateLoginAttemptsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\UsersModule\Commands\CheckEmailsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\UsersModule\Commands\DisableUserCommand::class));
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\UsersModule\Components\UserLoginAttempts::class),
            710
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\UsersModule\Components\UserPasswordChanges::class),
            1700
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\UsersModule\Components\AutologinTokens::class),
            1900
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\UsersModule\Components\UserMeta::class),
            960
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\UsersModule\Components\UserTokens::class),
            1235
        );

        $widgetManager->registerWidget(
            'admin.user.detail.box',
            $this->getInstance(\Crm\UsersModule\Components\UserSourceAccesses::class),
            580
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.totals',
            $this->getInstance(\Crm\UsersModule\Components\ActiveRegisteredUsersStatWidget::class),
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.today',
            $this->getInstance(\Crm\UsersModule\Components\TodayUsersStatWidget::class),
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.month',
            $this->getInstance(\Crm\UsersModule\Components\MonthUsersStatWidget::class),
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.mtd',
            $this->getInstance(\Crm\UsersModule\Components\MonthToDateUsersStatWidget::class),
            500
        );
        $widgetManager->registerWidget(
            'admin.users.header',
            $this->getInstance(\Crm\UsersModule\Components\MonthUsersSmallBarGraphWidget::class),
            500
        );

        $widgetManager->registerWidget(
            'admin.user.address.partial',
            $this->getInstance(\Crm\UsersModule\Components\AddressWidget::class),
            100
        );
        $widgetManager->registerWidget(
            'frontend.user.address.partial',
            $this->getInstance(\Crm\UsersModule\Components\AddressWidget::class),
            100
        );
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'user', 'info'), \Crm\UsersModule\Api\UserInfoHandler::class, \Crm\UsersModule\Auth\UserTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'login'), \Crm\UsersModule\Api\UsersLoginHandler::class, \Crm\ApiModule\Authorization\NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'email'), \Crm\UsersModule\Api\UsersEmailHandler::class, \Crm\ApiModule\Authorization\NoAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'create'), \Crm\UsersModule\Api\UsersCreateHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'add-to-group'), \Crm\UsersModule\Api\UserGroupApiHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'remove-from-group'), \Crm\UsersModule\Api\UserGroupApiHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'addresses'), \Crm\UsersModule\Api\AddressesHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'address'), \Crm\UsersModule\Api\CreateAddressHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'change-address-request'), \Crm\UsersModule\Api\CreateAddressChangeRequestHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'list'), \Crm\UsersModule\Api\ListUsersHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(new ApiIdentifier('1', 'users', 'confirm'), \Crm\UsersModule\Api\UsersConfirmApiHandler::class, \Crm\ApiModule\Authorization\BearerTokenAuthorization::class)
        );
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\UsersModule\User\BasicUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\UsersModule\User\AddressesUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\UsersModule\User\AutoLoginTokensUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\UsersModule\User\UserMetaUserDataProvider::class));
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        $criteriaStorage->register('users', 'active', $this->getInstance(\Crm\UsersModule\Segment\ActiveCriteria::class));
        $criteriaStorage->register('users', 'deleted', $this->getInstance(\Crm\UsersModule\Segment\DeletedCriteria::class));
        $criteriaStorage->register('users', 'source', $this->getInstance(\Crm\UsersModule\Segment\SourceCriteria::class));
        $criteriaStorage->register('users', 'source_access', $this->getInstance(\Crm\UsersModule\Segment\SourceAccessCriteria::class));
        $criteriaStorage->register('users', 'email', $this->getInstance(\Crm\UsersModule\Segment\EmailCriteria::class));
        $criteriaStorage->register('users', 'created', $this->getInstance(\Crm\UsersModule\Segment\CreatedCriteria::class));
        $criteriaStorage->register('users', 'group', $this->getInstance(\Crm\UsersModule\Segment\GroupCriteria::class));

        $criteriaStorage->setDefaultFields('users', ['id', 'email']);
        $criteriaStorage->setFields('users', [
            'first_name',
            'last_name',
            'public_name',
            'role',
            'active',
            'source',
            'confirmed_at',
            'last_sign_in_at',
            'created_at',
        ]);
    }

    public function registerRoutes(RouteList $router)
    {
        $router[] = new Route('sign/in/', 'Users:Sign:in');
        $router[] = new Route('sign/up/', 'Users:Sign:up');
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(UsersSeeder::class));
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
    }

    public function registerCleanupFunction(CallbackManagerInterface $cleanUpManager)
    {
        $cleanUpManager->add(function (Container $container) {
            /** @var ChangePasswordsLogsRepository $changePasswordLogsRepository */
            $changePasswordLogsRepository = $container->getByType(ChangePasswordsLogsRepository::class);
            $changePasswordLogsRepository->removeOldData('-12 months');
        });
        $cleanUpManager->add(function (Container $container) {
            /** @var UserActionsLogRepository $changePasswordLogsRepository */
            $userActionsLogRepository = $container->getByType(UserActionsLogRepository::class);
            $userActionsLogRepository->removeOldData('-12 months');
        });
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('address_changed', Events\AddressChangedEvent::class);
        $eventsStorage->register('login_attempt', Events\LoginAttemptEvent::class);
        $eventsStorage->register('new_access_token', Events\NewAccessTokenEvent::class);
        $eventsStorage->register('new_address', Events\NewAddressEvent::class);
        $eventsStorage->register('notification', Events\NotificationEvent::class);
        $eventsStorage->register('removed_access_token', Events\RemovedAccessTokenEvent::class);
        $eventsStorage->register('user_change_password', Events\UserChangePasswordEvent::class);
        $eventsStorage->register('user_change_password_request', Events\UserChangePasswordRequestEvent::class);
        $eventsStorage->register('user_confirmed', Events\UserConfirmedEvent::class);
        $eventsStorage->register('user_created', Events\UserCreatedEvent::class, true);
        $eventsStorage->register('user_disabled', Events\UserDisabledEvent::class);
        $eventsStorage->register('user_last_access', Events\UserLastAccessEvent::class);
        $eventsStorage->register('user_meta', Events\UserMetaEvent::class);
        $eventsStorage->register('user_reset_password', Events\UserResetPasswordEvent::class);
        $eventsStorage->register('user_suspicious', Events\UserSuspiciousEvent::class);
        $eventsStorage->register('user_sign_in', Events\UserSignInEvent::class);
        $eventsStorage->register('user_sign_out', Events\UserSignOutEvent::class);
        $eventsStorage->register('user_updated', Events\UserUpdatedEvent::class);
    }
}
