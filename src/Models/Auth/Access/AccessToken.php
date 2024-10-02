<?php

namespace Crm\UsersModule\Models\Auth\Access;

use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Http\IRequest;
use Nette\Http\Request;
use Nette\Http\Response;

class AccessToken
{
    private $version = 3;

    private $accessTokenRepository;

    private $usersRepository;

    protected $cookieName = 'n_token';

    private $sameSiteFlag = 'Lax';

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UsersRepository $usersRepository
    ) {
        $this->accessTokenRepository = $accessTokensRepository;
        $this->usersRepository = $usersRepository;
    }

    /**
     * Optionally, one can modify `SameSite` flag sent when setting n_token cookie
     * By default, 'Lax' value is used
     * @param string $sameSiteFlag
     */
    public function setSameSiteFlag(string $sameSiteFlag): void
    {
        $this->sameSiteFlag = $sameSiteFlag;
    }

    public function addUserToken($user, Request $request = null, Response $response = null, ?string $source = null)
    {
        $userRow = $this->usersRepository->find($user->id);
        if (isset($userRow->deleted_at)) {
            throw new \Exception("Unable to create access token for deleted user ID: [{$user->id}]");
        }

        // remove old token if exists
        if ($request) {
            $cookieToken = $request->getCookie($this->cookieName);
            $token = $this->accessTokenRepository->loadToken($cookieToken);
            if ($token && $token->user_id == $userRow->id) {
                $this->accessTokenRepository->remove($token->token);
            }
        }

        $token = $this->accessTokenRepository->add($userRow, $this->version, $source);

        if ($response && !\Crm\ApplicationModule\Models\Request::isApi()) {
            $response->setCookie(
                $this->cookieName,
                $token->token,
                strtotime('+10 years'),
                '/',
                \Crm\ApplicationModule\Models\Request::getDomain(),
                $request->isSecured(),
                false,
                $this->sameSiteFlag
            );
        }

        return $userRow;
    }

    public function deleteActualUserToken($user, Request $request, Response $response)
    {
        $cookieToken = $request->getCookie($this->cookieName);
        $token = $this->accessTokenRepository->loadToken($cookieToken);
        if ($token && $token->user_id == $user->id) {
            $this->accessTokenRepository->remove($token->token);
        }

        $response->deleteCookie($this->cookieName, '/', \Crm\ApplicationModule\Models\Request::getDomain());
        $response->deleteCookie('n_version', '/', \Crm\ApplicationModule\Models\Request::getDomain());
    }

    public function getToken(IRequest $request)
    {
        return $request->getCookie($this->cookieName);
    }

    public function lastVersion()
    {
        return $this->version;
    }
}
