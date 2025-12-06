<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Models\User\RedisUserDataStorage;
use Crm\ApplicationModule\Models\User\UserDataRegistrator;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UserInfoHandler;
use Crm\UsersModule\DataProviders\BasicUserDataProvider;
use Crm\UsersModule\DataProviders\UserMetaUserDataProvider;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\UserData;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UserInfoHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private UserInfoHandler $handler;
    private UserManager $userManager;
    private UsersRepository $usersRepository;
    private AccessTokensRepository $accessTokensRepository;
    private UserMetaRepository $userMetaRepository;
    private RedisUserDataStorage $userDataStorage;
    private ActiveRowFactory $activeRowFactory;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            AccessTokensRepository::class,
            UserMetaRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(UserInfoHandler::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->userMetaRepository = $this->getRepository(UserMetaRepository::class);
        $this->userDataStorage = $this->inject(RedisUserDataStorage::class);
        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testNoAuthorization(): void
    {
        $tokenWithoutUser = $this->activeRowFactory->create([
            'token' => 'fake_token',
        ]);
        $this->handler->setAuthorization(new TestUserTokenAuthorization($tokenWithoutUser, null));

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S403_Forbidden, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('no_authorization', $payload['error']);
    }

    public static function userInfoDataProvider(): array
    {
        return [
            'UnconfirmedUser_NoMeta' => [
                'confirmedAt' => null,
                'userMeta' => null,
            ],
            'ConfirmedUser_NoMeta' => [
                'confirmedAt' => [
                    'source' => '2025-08-20 10:20:30',
                    'expected' => '2025-08-20T10:20:30+02:00',
                ],
                'userMeta' => null,
            ],
            'ConfirmedUser_WithMeta' => [
                'confirmedAt' => [
                    'source' => '2025-08-20 10:20:30',
                    'expected' => '2025-08-20T10:20:30+02:00',
                ],
                'userMeta' => [
                    'source' => [
                        [
                            'key' => 'first_name',
                            'value' => 'Jon',
                            'public' => true,
                        ],
                        [
                            'key' => 'last_name',
                            'value' => 'Snow',
                            'public' => true,
                        ],
                        [
                            'key' => 'auth',
                            'value' => 'secret',
                            'public' => false,
                        ],
                    ],
                    'expected' => [
                        'first_name' => 'Jon',
                        'last_name' => 'Snow',
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('userInfoDataProvider')]
    public function testUserInfoWithDatabaseSource(
        ?array $confirmedAt,
        ?array $userMeta,
    ): void {
        // Create user
        $user = $this->userManager->addNewUser('test@example.com');

        if ($confirmedAt) {
            $this->userManager->confirmUser($user, DateTime::from($confirmedAt['source']));
            $user = $this->usersRepository->find($user->id);
        }

        if ($userMeta) {
            {
            foreach ($userMeta['source'] as $record) {
                $this->userMetaRepository->add(
                    user: $user,
                    key: $record['key'],
                    value: $record['value'],
                    isPublic: $record['public'],
                );
            }
            }
        }

        $accessToken = $this->accessTokensRepository->add($user);
        $this->handler->setAuthorization(new TestUserTokenAuthorization($accessToken, $user));

        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertEquals($user->id, $payload['user']['id']);
        $this->assertEquals($user->email, $payload['user']['email']);
        $this->assertEquals($user->uuid, $payload['user']['uuid']);

        if ($confirmedAt) {
            $this->assertEquals($confirmedAt['expected'], $payload['user']['confirmed_at']);
        } else {
            $this->assertNull($payload['user']['confirmed_at']);
        }

        if ($userMeta) {
            foreach ($userMeta['expected'] as $key => $value) {
                $this->assertEquals($value, $payload['user_meta']->{$key});
            }
        }
    }

    public function testUserInfoWithCachedSource(): void
    {
        $userData = $this->inject(UserData::class);
        $userDataRegistrator = $this->inject(UserDataRegistrator::class);
        $userDataRegistrator->addUserDataProvider($this->inject(BasicUserDataProvider::class));
        $userDataRegistrator->addUserDataProvider($this->inject(UserMetaUserDataProvider::class));

        $user = $this->userManager->addNewUser('cached@example.com');
        $confirmationDate = DateTime::from('2025-07-24 12:14:16');
        $this->userManager->confirmUser($user, $confirmationDate);
        $user = $this->usersRepository->find($user->id);

        $this->userMetaRepository->add($user, 'first_name', 'Jon', null, true);
        $this->userMetaRepository->add($user, 'last_name', 'Snow', null, true);
        $this->userMetaRepository->add($user, 'secret', 'nbusr123', null, false);

        $userData->refreshUserTokens($user->id);

        $accessToken = $user->related('access_tokens')->fetch();

        $cachedData = $this->userDataStorage->load($accessToken->token);
        $this->assertNotNull($cachedData);

        $this->handler->setAuthorization(new TestUserTokenAuthorization($accessToken, $user));
        $response = $this->runJsonApi($this->handler);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertEquals($user->id, $payload['user']['id']);
        $this->assertEquals($user->email, $payload['user']['email']);
        $this->assertEquals($user->uuid, $payload['user']['uuid']);
        $this->assertEquals('2025-07-24T12:14:16+02:00', $payload['user']['confirmed_at']);
        $this->assertEquals('Jon', $payload['user_meta']->first_name);
        $this->assertEquals('Snow', $payload['user_meta']->last_name);
        $this->assertObjectNotHasProperty('secret', $payload['user_meta']);
    }
}
