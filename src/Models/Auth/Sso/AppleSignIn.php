<?php

namespace Crm\UsersModule\Models\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Request as CrmRequest;
use Crm\UsersModule\Repositories\UserConnectedAccountsRepository;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\Url;
use Nette\Security\User;
use Nette\Utils\Json;

class AppleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO = 'web+apple_sso';
    public const USER_SOURCE_APPLE_SSO = "apple_sso";
    public const USER_APPLE_REGISTRATION_CHANNEL = "apple";

    private const COOKIE_ASI_STATE = 'asi_state';
    private const COOKIE_ASI_SOURCE = 'asi_source';
    private const COOKIE_ASI_USER_ID = 'asi_user_id';
    private const COOKIE_ASI_NONCE = 'asi_nonce';

    private ?string $clientId;
    private array $trustedClientIds = [];

    public function __construct(
        ?string $clientId,
        array $trustedClientIds,
        private ConfigsRepository $configsRepository,
        private SsoUserManager $ssoUserManager,
        private User $user,
        private Response $response,
        private Request $request,
        private Session $session,
    ) {
        $this->clientId = $clientId;

        if ($clientId !== null) {
            $this->trustedClientIds[$clientId] = true;
        }
        foreach (array_filter($trustedClientIds) as $trustedClientId) {
            $this->trustedClientIds[$trustedClientId] = true;
        }
    }

    public function isEnabled(): bool
    {
        return (boolean)($this->configsRepository->loadByName('apple_sign_in_enabled')->value ?? false);
    }

    private function setLoginCookie(string $key, $value)
    {
        $this->response->setCookie(
            $key,
            $value,
            strtotime('+10 minutes'),
            '/',
            null,
            true, // "SameSite: None" has to have "Secure: true"
            true,
            'None' // Lax cannot be used with POST request (response from Apple is POST)
        );
    }

    // Function to delete cookie(s) has to match cookie-domain set in `setLoginCookie()`,
    // otherwise cookie will not be deleted.
    private function deleteLoginCookies(string...$keys): void
    {
        foreach ($keys as $key) {
            // Deleting "SameSite: None" cookie has to have "Secure: true" as well
            $this->response->deleteCookie($key, '/', null, true);
        }
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' and 'nonce' to verify later in callback
     *
     * @param string      $redirectUri
     * @param string|null $source
     *
     * @return string
     * @throws \Exception
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $state = bin2hex(random_bytes(128 / 8));
        $nonce = bin2hex(random_bytes(128 / 8));

        $url = new Url('https://appleid.apple.com/auth/authorize');
        $url->setQuery([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_mode' => 'form_post',
            'response_type' => 'id_token code',
            'scope' => 'email',
            'state' => $state,
            'nonce' => $nonce
        ]);

        //save cookie for later verification
        $this->setLoginCookie(self::COOKIE_ASI_STATE, $state);
        if ($source) {
            $this->setLoginCookie(self::COOKIE_ASI_SOURCE, $source);
        } else {
            $this->deleteLoginCookies(self::COOKIE_ASI_SOURCE);
        }
        $this->setLoginCookie(self::COOKIE_ASI_NONCE, $nonce);

        $userId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ($userId) {
            $this->setLoginCookie(self::COOKIE_ASI_USER_ID, $userId);
        } else {
            $this->deleteLoginCookies(self::COOKIE_ASI_USER_ID);
        }
        $this->setSessionCookieForCallback($redirectUri);

        return $url->getAbsoluteUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string|null $referer to save with user if user is created
     * @param string|null $locale
     *
     * @return ActiveRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     */
    public function signInCallback(?string $referer = null, ?string $locale = null): ActiveRow
    {
        $asiState = $this->request->getCookie(self::COOKIE_ASI_STATE);
        $asiUserId = $this->request->getCookie(self::COOKIE_ASI_USER_ID);
        $asiSource = $this->request->getCookie(self::COOKIE_ASI_SOURCE);
        $asiNonce = $this->request->getCookie(self::COOKIE_ASI_NONCE);

        $this->deleteLoginCookies(self::COOKIE_ASI_STATE, self::COOKIE_ASI_USER_ID, self::COOKIE_ASI_SOURCE, self::COOKIE_ASI_NONCE);

        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $error = $this->request->getPost('error');
        if (!empty($error)) {
            $code = match ($error) {
                'user_cancelled_authorize' => SsoException::CODE_CANCELLED,
                default => 0,
            };
            // Got an error, probably user denied access
            throw new SsoException('Apple SignIn error: ' . htmlspecialchars($error, ENT_QUOTES), $code);
        }

        // Check internal state
        $requestState = $this->request->getPost('state');
        if ($requestState !== $asiState) {
            // State is invalid, possible CSRF attack in progress
            throw new SsoException('Apple SignIn error: invalid state');
        }

        // Check user state
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ((string) $loggedUserId !== (string) $asiUserId) {
            // State is invalid, possible user change between login request and callback
            throw new SsoException("Apple SignIn error: invalid user state (current userId: {$loggedUserId}, cookie userId: {$asiUserId})");
        }

        $encodedIdToken = $this->request->getPost('id_token');
        if (empty($encodedIdToken)) {
            throw new SsoException('Apple SignIn error: id token is not present, possibly bogus request');
        }

        try {
            $idToken = $this->decodeIdToken($encodedIdToken);
        } catch (\Exception $exception) {
            throw new SsoException('Apple SignIn error: unable to verify id token');
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken, $asiNonce)) {
            // Id token is invalid
            throw new SsoException('Apple SignIn error: invalid id token');
        }

        // Check code
        if (!$this->isCodeValid($this->request->getPost('code'), $idToken)) {
            // Code is invalid
            throw new SsoException('Apple SignIn error: invalid code');
        }

        if (!isset($idToken->email)) {
            throw new SsoException('Apple SignIn error: missing email in ID token; idToken: ' . $encodedIdToken);
        }

        $userEmail = $idToken->email;
        // 'sub' represents Apple ID in id_token
        // Note: Use 'sub' to identify users, email can change or be private
        $appleUserId = $idToken->sub;

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $asiSource ?? self::USER_SOURCE_APPLE_SSO,
            self::USER_APPLE_REGISTRATION_CHANNEL,
            $referer
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            $userBuilder,
            null,
            $loggedUserId
        );
    }

    /**
     * Implements validation of ID token (JWT token)
     *
     * If token is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     * Note: Access token is not automatically created
     *
     * @param string $idTokenInput
     * @param string|null $locale if user is created, this locale will be set as a default user locale
     *
     * @return ActiveRow|null created/matched user
     * @throws \Exception
     */
    public function signInUsingIdToken(string $idTokenInput, ?string $locale = null): ?ActiveRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!$this->clientId) {
            throw new \Exception("Apple Sign In Client ID not configured, please configure 'users.sso.apple' parameter based on the README file.");
        }

        try {
            $idToken = $this->decodeIdToken($idTokenInput);
        } catch (\Exception $exception) {
            return null;
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken)) {
            // Id token is invalid
            return null;
        }

        $userEmail = $idToken->email;
        // 'sub' represents Apple ID in id_token
        // Note: Use 'sub' to identify users, email can change or be private
        $appleUserId = $idToken->sub;

        // Match apple user to CRM user
        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            self::USER_SOURCE_APPLE_SSO,
            self::USER_APPLE_REGISTRATION_CHANNEL
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            $userBuilder
        );
    }

    private function decodeIdToken($idToken): \stdClass
    {
        $client = new Client();
        $response = $client->get('https://appleid.apple.com/auth/keys');
        $response = Json::decode($response->getBody()->getContents(), Json::FORCE_ARRAY);

        // RS256 = openssl + SHA256
        return JWT::decode($idToken, JWK::parseKeySet($response, 'RS256'));
    }

    private function isIdTokenValid($idToken, $nonce = null): bool
    {
        if ($idToken->iss !== 'https://appleid.apple.com') {
            return false;
        }

        if (!isset($this->trustedClientIds[$idToken->aud])) {
            return false;
        }

        if ($idToken->exp < time()) {
            return false;
        }

        if ($idToken->nonce_supported) {
            if ($nonce && ($idToken->nonce ?? null) !== $nonce) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets short-lived session cookie having `SameSite: None` flag and callback URI path .
     *
     * This is required for session cookie to be sent along with callback (POST) request from Apple after successful SSO login.
     * Normal session cookie has `SameSite: Lax` flag and therefore is not sent along with POST (cross-origin) requests.
     *
     * Session is required to check if user that triggered OAuth flow is the same that we check against in the callback (see `COOKIE_ASI_USER_ID`).
     *
     * @param string $redirectUri
     *
     * @return void
     */
    private function setSessionCookieForCallback(string $redirectUri): void
    {
        $url = new Url($redirectUri);

        $cookie = session_get_cookie_params();
        $this->response->setCookie(
            $this->session->getName(),
            $this->session->getId(),
            strtotime('+5 minutes'), // this is short-lived session cookie
            $url->getPath(), // valid only for callback path
            $cookie['domain'],
            true, // "SameSite: None" requires secure to be set to "true"
            $cookie['httponly'],
            'None'
        );

        // If n_token was missing, user would be logged out.
        // Therefore, we need to set it too.
        $cookieToken = $this->request->getCookie('n_token');

        // Domain same as when setting 'n_token' in AccessToken
        if ($cookieToken) {
            $this->response->setCookie(
                'n_token',
                $cookieToken,
                strtotime('+5 minutes'), // this is short-lived cookie
                $url->getPath(), // valid only for callback path
                CrmRequest::getDomain(),
                true, // "SameSite: None" requires secure to be set to "true"
                $cookie['httponly'],
                'None'
            );
        }
    }

    private function isCodeValid($code, $idToken): bool
    {
        $hash = hash('sha256', $code, true);
        $firstHalfHash = substr($hash, 0, strlen($hash) / 2);

        return JWT::urlsafeB64Encode($firstHalfHash) === $idToken->c_hash;
    }
}
