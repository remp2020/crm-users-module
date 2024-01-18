<?php

namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProviders\GoogleSignInDataProviderInterface;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Google\Client;
use Google\Service\Oauth2;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Security\User;

class GoogleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO = 'web+google_sso';
    public const USER_SOURCE_GOOGLE_SSO = "google_sso";
    public const USER_GOOGLE_REGISTRATION_CHANNEL = "google";

    private const COOKIE_GSI_STATE = 'gsi_state';
    private const COOKIE_GSI_SOURCE = 'gsi_source';
    private const COOKIE_GSI_USER_ID = 'gsi_user_id';

    // Default scopes MUST be included for OpenID Connect.
    private const DEFAULT_SCOPES =  [
        'email',
    ];

    private ?string $clientId;
    private ?string $clientSecret;
    private ?Client $googleClient = null;

    public function __construct(
        ?string $clientId,
        ?string $clientSecret,
        private ConfigsRepository $configsRepository,
        private SsoUserManager $ssoUserManager,
        private User $user,
        private DataProviderManager $dataProviderManager,
        private Response $response,
        private Request $request,
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function isEnabled(): bool
    {
        return (boolean) ($this->configsRepository->loadByName('google_sign_in_enabled')->value ?? false);
    }

    public function setGoogleClient(Client $googleClient): void
    {
        $this->googleClient = $googleClient;
    }

    /**
     * Implements validation of ID token (JWT token) as described in:
     * https://developers.google.com/identity/sign-in/web/backend-auth
     *
     * If token is successfully verified, user with Google connected account will be created (or matched to an existing user).
     * Note: Access token is not automatically created
     *
     * @param string $idToken
     * @param string|null $gsiAccessToken
     * @param int|null $loggedUserId
     * @param string|null $source
     * @param string|null $locale if user is created, this locale will be set as a default user locale
     * @return ActiveRow|null created/matched user
     * @throws AlreadyLinkedAccountSsoException
     * @throws DataProviderException
     */
    public function signInUsingIdToken(
        string $idToken,
        string $gsiAccessToken = null,
        int $loggedUserId = null,
        string $source = null,
        ?string $locale = null
    ): ?ActiveRow {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $client = $this->getClient();
        $payload = $client->verifyIdToken($idToken);
        if (!$payload) {
            return null;
        }

        $userEmail = $payload['email'];
        // 'sub' represents Google ID in id_token
        //
        // Note: A Google account's email address can change, so don't use it to identify a user.
        // Instead, use the account's ID, which you can get on the client with getBasicProfile().getId(),
        // and on the backend from the sub claim of the ID token.
        // https://developers.google.com/identity/sign-in/web/people
        $googleUserId = $payload['sub'];

        // Match only already connected accounts (DO NOT provide email here) before any external matching (via data provider) is done
        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->provide([
                'matchedUser' => $matchedUser,
                'googleUserEmail' => $userEmail,
                'googleUserId' => $googleUserId,
                'gsiAccessToken' => $gsiAccessToken,
                'locale' => $locale,
            ]);
        }

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $source ?? self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        // Match google user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $googleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
            $userBuilder,
            $payload,
            $loggedUserId
        );
    }

    /**
     * Exchanges one-time auth code for credentials, containing id_token, access_token, ...
     * Useful e.g. for offline access for users logged in apps.
     * See:
     * - https://developers.google.com/identity/sign-in/android/offline-access
     * - https://developers.google.com/identity/sign-in/ios/offline-access
     *
     * @param string      $gsiAuthCode
     * @param string      $redirectUri redirectUri depends on how auth_code was initially requested.
     *                                 In case of web surface, one may use 'postmessage' redirectUri,
     *                                 which is a reserved URI string in Google-land.
     *                                 Otherwise, use standard callback URI registered for OAuth client.
     *
     * @return array keys 'access_token', 'scope', 'id_token', 'token_type', 'refresh_token', 'expires_in', 'created'
     * @throws \Exception
     */
    public function exchangeAuthCode(string $gsiAuthCode, string $redirectUri): array
    {
        $client = $this->getClient($redirectUri);
        return $client->fetchAccessTokenWithAuthCode($gsiAuthCode);
    }

    private function setLoginCookie(string $key, $value): void
    {
        $this->response->setCookie(
            $key,
            $value,
            strtotime('+10 minutes'),
            '/',
            null,
            null,
            true,
            'Lax'
        );
    }

    // Function to delete cookie(s) has to match cookie-domain set in `setLoginCookie()`,
    // otherwise cookie will not be deleted.
    private function deleteLoginCookies(string...$keys): void
    {
        foreach ($keys as $key) {
            $this->response->deleteCookie($key, '/');
        }
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' to verify later in callback
     *
     * @param string      $redirectUri
     * @param string|null $source
     *
     * @return string
     * @throws SsoException
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $code = $this->request->getQuery('code');
        if ($code !== null) {
            throw new SsoException("Invalid call, 'code' GET parameter should be passed to redirect URI link");
        }

        $client = $this->getClient($redirectUri);

        // Parameters are set according to required parameters in documentation
        // https://github.com/googleapis/google-api-php-client/blob/master/docs/oauth-web.md#Step-1-Set-authorization-parameters
        $client->setScopes(self::DEFAULT_SCOPES);
        $client->setAccessType('online'); //without refresh-token (alternative is 'offline', with refresh token)

        // State is created according to documentation
        // https://developers.google.com/identity/protocols/oauth2/openid-connect#createxsrftoken
        $state = bin2hex(random_bytes(128/8));
        $client->setState($state);

        //save cookie for later verification (or delete to remove any stale cookies)
        $this->setLoginCookie(self::COOKIE_GSI_STATE, $state);
        if ($source) {
            $this->setLoginCookie(self::COOKIE_GSI_SOURCE, $source);
        } else {
            $this->deleteLoginCookies(self::COOKIE_GSI_SOURCE);
        }
        $userId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ($userId) {
            $this->setLoginCookie(self::COOKIE_GSI_USER_ID, $userId);
        } else {
            $this->deleteLoginCookies(self::COOKIE_GSI_USER_ID);
        }

        return $client->createAuthUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Google connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string      $redirectUri
     * @param string|null $referer to save with user if user is created
     * @param string|null $locale
     *
     * @return ActiveRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     * @throws DataProviderException
     */
    public function signInCallback(string $redirectUri, ?string $referer = null, ?string $locale = null): ActiveRow
    {
        $gsiState = $this->request->getCookie(self::COOKIE_GSI_STATE);
        $gsiUserId = $this->request->getCookie(self::COOKIE_GSI_USER_ID);
        $gsiSource = $this->request->getCookie(self::COOKIE_GSI_SOURCE);

        // Currently OAUTH variables are not deleted right after sign-in, but let to expire in 10 minutes
        //$this->deleteLoginCookies(self::COOKIE_GSI_STATE, self::COOKIE_GSI_USER_ID, self::COOKIE_GSI_SOURCE);

        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $error = $this->request->getQuery('error');
        if (!empty($error)) {
            // Got an error, probably user denied access
            throw new SsoException('Google SignIn error: ' . htmlspecialchars($error, ENT_QUOTES));
        }

        $code = $this->request->getQuery('code');
        if (empty($code)) {
            throw new SsoException('Google SignIn error: missing code');
        }

        // Check internal state
        $requestState = $this->request->getQuery('state');
        if (empty($requestState) || ($requestState !== $gsiState)) {
            // State is invalid, possible CSRF attack in progress
            throw new SsoException("Google SignIn error: invalid state (current state: [{$requestState}], cookie state: [{$gsiState}]).");
        }

        // Check user state
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ((string) $loggedUserId !== (string) $gsiUserId) {
            // State is invalid, possible user change between login request and callback
            throw new SsoException('Google SignIn error: invalid user state (current userId: '. $loggedUserId . ', cookie userId: ' . $gsiUserId . ')');
        }

        // Get OAuth access token
        $client = $this->getClient($redirectUri);
        $client->fetchAccessTokenWithAuthCode($code);

        // Get user details using access token
        $service = new Oauth2($client);
        try {
            $userInfo = $service->userinfo->get();
        } catch (\Google\Service\Exception $e) {
            throw new SsoException('Google SignIn error: unable to retrieve user info', $e->getCode(), $e);
        }

        $userEmail =  $userInfo->getEmail();
        // 'sub' represents Google ID in id_token
        //
        // Note: A Google account's email address can change, so don't use it to identify a user.
        // Instead, use the account's ID, which you can get on the client with getBasicProfile().getId(),
        // and on the backend from the sub claim of the ID token.
        // https://developers.google.com/identity/sign-in/web/people
        $googleUserId =  $userInfo->getId();

        // Match only already connected accounts (DO NOT provide email here) before any external matching (via data provider) is done
        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->provide([
                'matchedUser' => $matchedUser,
                'googleUserEmail' => $userEmail,
                'googleUserId' => $googleUserId,
                'gsiAccessToken' => $client->getAccessToken()['access_token'],
                'redirectUri' => $redirectUri,
                'locale' => $locale,
            ]);
        }

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $gsiSource ?? self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL,
            $referer
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        // Match google user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $googleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
            $userBuilder,
            $userInfo->toSimpleObject(),
            $loggedUserId,
        );
    }

    private function getClient(?string $redirectUri = null): Client
    {
        if ($this->googleClient) {
            return $this->googleClient;
        }

        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception("Google Sign In Client ID and Secret not configured, please configure 'users.sso.google' parameter based on the README file.");
        }

        $googleClient = new Client([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
        if ($redirectUri) {
            $googleClient->setRedirectUri($redirectUri);
        }
        return $googleClient;
    }
}
