<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProviders\GoogleTokenSignInDataProviderInterface;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Models\Auth\Sso\AdminAccountSsoLinkingException;
use Crm\UsersModule\Models\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Implements validation of Google ID Token
 * see https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
 *
 * @package Crm\UsersModule\Api
 */
class GoogleTokenSignInHandler extends ApiHandler
{
    public function __construct(
        private GoogleSignIn $googleSignIn,
        private AccessTokensRepository $accessTokensRepository,
        private DeviceTokensRepository $deviceTokensRepository,
        private UsersRepository $usersRepository,
        private DataProviderManager $dataProviderManager,
        private Emitter $emitter,
        private \Tomaj\Hermes\Emitter $hermesEmitter,
        LinkGenerator $linkGenerator
    ) {
        parent::__construct();
        $this->linkGenerator = $linkGenerator;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('id_token'))->setRequired(),
            new PostInputParam('create_access_token'),
            new PostInputParam('device_token'),
            new PostInputParam('gsi_auth_code'),
            new PostInputParam('is_web'),
            new PostInputParam('source'),
            new PostInputParam('locale'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $idToken = $params['id_token'];
        $createAccessToken = filter_var($params['create_access_token'], FILTER_VALIDATE_BOOLEAN);
        $gsiAuthCode = $params['gsi_auth_code'] ?? null;
        $isWeb = filter_var($params['is_web'], FILTER_VALIDATE_BOOLEAN);

        $deviceToken = null;
        if (!empty($params['device_token'])) {
            if (!$createAccessToken) {
                $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'code' => 'no_access_token_to_pair_device_token',
                    'message' => 'There is no access token to pair with device token. Set parameter "create_access_token=true" in your request payload.'
                ]);
                return $response;
            }

            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, [
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist'
                ]);
                return $response;
            }
        }

        // If user provides auth_code, use it to load Google access_token and id_token (replaces one from parameters)
        $gsiAccessToken = null;
        if ($gsiAuthCode) {
            // 'postmessage' is a reserved URI string in Google-land. Use it in case auth_code was requested from web surface.
            // Otherwise, use standard callback URI also used in Google presenter.
            // @see https://github.com/googleapis/google-auth-library-php/blob/21dd478e77b0634ed9e3a68613f74ed250ca9347/src/OAuth2.php#L777
            $redirectUri = $isWeb ? 'postmessage' : $this->linkGenerator->link('Users:Google:Callback');

            try {
                $creds = $this->googleSignIn->exchangeAuthCode($gsiAuthCode, $redirectUri);
                if (!isset($creds['id_token']) || !isset($creds['access_token'])) {
                    // do not break login process if access_token is invalid (and id_token possibly valid)
                    Debugger::log('Unable to exchange auth code for access_token and id_token, creds: ' . Json::encode($creds), ILogger::ERROR);
                } else {
                    $idToken = $creds['id_token'];
                    $gsiAccessToken = $creds['access_token'];
                }
            } catch (\Exception $e) {
                // do not break login process if e.g. network call fails
                Debugger::log($e->getMessage(), Debugger::EXCEPTION);
            }
        }

        try {
            $user = $this->googleSignIn->getUserUsingIdToken($idToken, $gsiAccessToken, null, $params['source'] ?? null, $params['locale'] ?? null);
        } catch (AdminAccountSsoLinkingException $e) {
            return new JsonApiResponse(IResponse::S403_FORBIDDEN, [
                'status' => 'error',
                'code' => 'error_linking_admin_account',
                'message' => 'Unable to log in using Google account, because it cannot be linked to the CRM account' .
                    'with admin rights - this is forbidden for security reasons. Please contact support.',
            ]);
        }

        if (!$user) {
            return new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                'status' => 'error',
                'code' => 'error_verifying_id_token',
                'message' => 'Unable to verify ID token',
            ]);
        }

        $accessToken = null;
        if ($createAccessToken) {
            $accessToken = $this->accessTokensRepository->add($user, 3, GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO);
            $this->addLoginAttempt(
                user: $user,
                source: $params['source'] ?? GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO,
                browserId: $deviceToken->device_id ?? null,
            );
            if ($deviceToken) {
                $this->accessTokensRepository->pairWithDeviceToken($accessToken, $deviceToken);
            }
        }

        return new JsonApiResponse(IResponse::S200_OK, $this->formatResponse($user, $accessToken));
    }

    private function formatResponse(ActiveRow $user, ?ActiveRow $accessToken): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'email' => $user->email,
                'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339),
                'confirmed_at' => $user->confirmed_at?->format(\DateTimeInterface::RFC3339),
            ],
            'user_meta' => new \stdClass(),
        ];

        if ($accessToken) {
            $result['access']['token'] = $accessToken->token;
        }

        $userMetaData = $user->related('user_meta')
            ->where('is_public', 1)
            ->fetchPairs('key', 'value');

        if (count($userMetaData)) {
            $result['user_meta'] = $userMetaData;
        }

        /** @var GoogleTokenSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_token_sign_in', GoogleTokenSignInDataProviderInterface::class);
        $toMerge = [];
        foreach ($providers as $s => $provider) {
            $toMerge[] = $provider->provide([
                'user' => $user
            ]);
        }
        return array_merge($result, ...$toMerge);
    }

    private function addLoginAttempt(ActiveRow $user, string $source, ?string $browserId)
    {
        $date = new \DateTime();
        $this->emitter->emit(new LoginAttemptEvent(
            $user->email,
            $user,
            $source,
            LoginAttemptsRepository::STATUS_API_OK,
            $date
        ));
        $this->hermesEmitter->emit(new HermesMessage(
            'login-attempt',
            [
                'status' => LoginAttemptsRepository::STATUS_API_OK,
                'source' => $source,
                'date' => $date->getTimestamp(),
                'browser_id' => $browserId,
                'user_id' => $user->id,
            ]
        ), HermesMessage::PRIORITY_DEFAULT);
    }
}
