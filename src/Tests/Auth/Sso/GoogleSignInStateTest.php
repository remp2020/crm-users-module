<?php

namespace Crm\UsersModule\Tests\Auth\Sso;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsSeeder as ApplicationConfigsSeeder;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignInConfig;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignInPromptEnum;
use Crm\UsersModule\Models\Auth\Sso\SsoException;
use Crm\UsersModule\Models\Auth\Sso\SsoStateStore;
use Crm\UsersModule\Models\Auth\Sso\SsoUserManager;
use Crm\UsersModule\Models\Auth\Sso\StaleSsoCallbackException;
use Crm\UsersModule\Seeders\ConfigsSeeder as UsersConfigsSeeder;
use Crm\UsersModule\Tests\BaseTestCase;
use Google\Client;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use PHPUnit\Framework\MockObject\Stub;

class GoogleSignInStateTest extends BaseTestCase
{
    private const CALLBACK_URL = 'https://crm.press/users/google/callback';

    private const STATE = '124151b5a05794b4430ac77afb50419e';

    private ConfigsRepository $configsRepository;

    private SsoStateStore&Stub $ssoStateStore;

    protected function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            ConfigCategoriesRepository::class,
            ConfigsRepository::class,
        ]);
    }

    protected function requiredSeeders(): array
    {
        return array_merge(parent::requiredSeeders(), [
            ApplicationConfigsSeeder::class,
            UsersConfigsSeeder::class,
        ]);
    }

    public function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->configsRepository = $this->getRepository(ConfigsRepository::class);
        $this->setConfig('google_sign_in_enabled', true);

        $this->ssoStateStore = $this->createStub(SsoStateStore::class);
    }

    public function testSignInRedirectIssuesStateAndPutsItInAuthUrl(): void
    {
        $issuedState = null;
        $ssoStateStore = $this->createMock(SsoStateStore::class);
        $ssoStateStore->expects($this->once())
            ->method('issue')
            ->willReturnCallback(
                function (string $provider, string $state, ?int $userId) use (&$issuedState) {
                    $this->assertSame(SsoStateStore::PROVIDER_GOOGLE, $provider);
                    $this->assertNull($userId);
                    $issuedState = $state;
                },
            );
        $this->ssoStateStore = $ssoStateStore;

        $googleSignIn = $this->createGoogleSignIn($this->createRequest());
        $authUrl = $googleSignIn->signInRedirect(self::CALLBACK_URL);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $issuedState);
        $this->assertSame($issuedState, $this->authUrlQuery($authUrl)['state'] ?? null);
    }

    public function testSignInRedirectPassesConfiguredPromptToGoogle(): void
    {
        $googleSignIn = $this->createGoogleSignIn($this->createRequest());
        $this->assertSame('select_account', $this->authUrlQuery($googleSignIn->signInRedirect(self::CALLBACK_URL))['prompt'] ?? null);

        $googleSignIn = $this->createGoogleSignIn(
            $this->createRequest(),
            prompt: GoogleSignInPromptEnum::Consent,
        );
        $this->assertSame('consent', $this->authUrlQuery($googleSignIn->signInRedirect(self::CALLBACK_URL))['prompt'] ?? null);

        $googleSignIn = $this->createGoogleSignIn($this->createRequest(), prompt: null);
        $this->assertArrayNotHasKey('prompt', $this->authUrlQuery($googleSignIn->signInRedirect(self::CALLBACK_URL)));
    }

    /**
     * The confirmed production case: the browser re-ran the Google authorization request long after the
     * sign-in was completed, so a valid callback arrives without any of our login cookies.
     */
    public function testCallbackWithoutCookiesOfConsumedStateIsReportedAsReplay(): void
    {
        $this->ssoStateStore->method('get')->willReturn([
            'issued_at' => '2026-08-15T07:01:28+02:00',
            'user_id' => null,
            'consumed_at' => '2026-08-15T07:01:29+02:00',
        ]);

        $googleSignIn = $this->createGoogleSignIn($this->createCallbackRequest());

        try {
            $googleSignIn->signInCallback(self::CALLBACK_URL);
            $this->fail('Stale callback should have been refused.');
        } catch (StaleSsoCallbackException $e) {
            $this->assertSame(StaleSsoCallbackException::REASON_REPLAYED, $e->getReason());
            $this->assertTrue($e->isExpected());
            $this->assertTrue($e->isAlreadyCompleted());
            $this->assertSame('2026-08-15T07:01:29+02:00', $e->getContext()['state_consumed_at']);
            $this->assertSame('none', $e->getContext()['query']['prompt']);
        }
    }

    public function testCallbackWithoutCookiesOfUnfinishedStateIsReportedAsExpired(): void
    {
        $this->ssoStateStore->method('get')->willReturn([
            'issued_at' => '2026-08-15T07:01:28+02:00',
            'user_id' => null,
            'consumed_at' => null,
        ]);

        $googleSignIn = $this->createGoogleSignIn($this->createCallbackRequest());

        try {
            $googleSignIn->signInCallback(self::CALLBACK_URL);
            $this->fail('Stale callback should have been refused.');
        } catch (StaleSsoCallbackException $e) {
            $this->assertSame(StaleSsoCallbackException::REASON_EXPIRED, $e->getReason());
            $this->assertTrue($e->isExpected());
            $this->assertFalse($e->isAlreadyCompleted());
        }
    }

    public function testCallbackWithUnknownStateIsNotTreatedAsExpected(): void
    {
        $this->ssoStateStore->method('get')->willReturn(null);

        $googleSignIn = $this->createGoogleSignIn($this->createCallbackRequest());

        try {
            $googleSignIn->signInCallback(self::CALLBACK_URL);
            $this->fail('Callback with an unknown state should have been refused.');
        } catch (StaleSsoCallbackException $e) {
            $this->assertSame(StaleSsoCallbackException::REASON_UNKNOWN_STATE, $e->getReason());
            $this->assertFalse($e->isExpected());
            $this->assertFalse($e->isAlreadyCompleted());
        }
    }

    public function testCallbackWithMismatchedStateCookieStaysLoud(): void
    {
        $ssoStateStore = $this->createMock(SsoStateStore::class);
        $ssoStateStore->expects($this->never())->method('get');
        $this->ssoStateStore = $ssoStateStore;

        $googleSignIn = $this->createGoogleSignIn($this->createCallbackRequest([
            'gsi_state' => 'ff00ff00ff00ff00ff00ff00ff00ff00',
        ]));

        $this->expectException(SsoException::class);
        $this->expectExceptionMessage('invalid state');
        $googleSignIn->signInCallback(self::CALLBACK_URL);
    }

    public function testCallbackOfAnonymousFlowWithSignedInUserIsReportedAsStale(): void
    {
        $this->ssoStateStore->method('get')->willReturn([
            'issued_at' => '2026-08-15T07:01:28+02:00',
            'user_id' => null,
            'consumed_at' => '2026-08-15T07:01:29+02:00',
        ]);

        $googleSignIn = $this->createGoogleSignIn(
            $this->createCallbackRequest(['gsi_state' => self::STATE]),
            user: $this->createUser(903066),
        );

        try {
            $googleSignIn->signInCallback(self::CALLBACK_URL);
            $this->fail('Stale callback should have been refused.');
        } catch (StaleSsoCallbackException $e) {
            $this->assertSame(StaleSsoCallbackException::REASON_REPLAYED, $e->getReason());
            $this->assertStringContainsString('invalid user state', $e->getMessage());
            $this->assertTrue($e->getContext()['logged_in']);
        }
    }

    public function testCallbackWithUserChangeStaysLoud(): void
    {
        $googleSignIn = $this->createGoogleSignIn(
            $this->createCallbackRequest([
                'gsi_state' => self::STATE,
                'gsi_user_id' => '1049552',
            ]),
            user: $this->createUser(903066),
        );

        $this->expectException(SsoException::class);
        $this->expectExceptionMessage('invalid user state');
        $googleSignIn->signInCallback(self::CALLBACK_URL);
    }

    private function createGoogleSignIn(
        Request $request,
        ?Response $response = null,
        ?User $user = null,
        ?GoogleSignInPromptEnum $prompt = GoogleSignInPromptEnum::SelectAccount,
    ): GoogleSignIn {
        $containerConfig = $this->inject(GoogleSignInConfig::class);

        $googleSignIn = new GoogleSignIn(
            new GoogleSignInConfig(
                $containerConfig->clientId,
                $containerConfig->clientSecret,
                $prompt,
            ),
            $this->configsRepository,
            $this->inject(SsoUserManager::class),
            $user ?? $this->createUser(),
            $this->inject(DataProviderManager::class),
            $response ?? new Response(),
            $request,
            $this->ssoStateStore,
        );
        $googleSignIn->setGoogleClient(new Client([
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => self::CALLBACK_URL,
        ]));

        return $googleSignIn;
    }

    private function authUrlQuery(string $authUrl): array
    {
        parse_str(parse_url($authUrl, PHP_URL_QUERY), $query);

        return $query;
    }

    private function createRequest(array $query = [], array $cookies = []): Request
    {
        $url = (new UrlScript(self::CALLBACK_URL))->withQuery($query);

        return new Request($url, cookies: $cookies);
    }

    /**
     * Callback as Google sends it when it silently re-approves an authorization request it has already
     * approved before.
     */
    private function createCallbackRequest(array $cookies = []): Request
    {
        return $this->createRequest([
            'state' => self::STATE,
            'code' => '4/0ATsMZqCCEfQMHGUkWgHHU7ugLYEDgBgUU7IXePtd5gFlEJ6HP',
            'scope' => 'email openid https://www.googleapis.com/auth/userinfo.email',
            'authuser' => '0',
            'prompt' => 'none',
            'iss' => 'https://accounts.google.com',
        ], $cookies);
    }

    private function createUser(?int $userId = null): User
    {
        $storage = $this->createStub(UserStorage::class);
        $storage->method('getState')
            ->willReturn([$userId !== null, $userId === null ? null : new SimpleIdentity($userId), null]);

        return new User($storage);
    }

    private function setConfig(string $name, $value): void
    {
        $config = $this->configsRepository->loadByName($name);
        $this->configsRepository->update($config, ['value' => $value]);
    }
}
