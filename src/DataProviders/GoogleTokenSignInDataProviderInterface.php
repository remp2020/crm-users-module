<?php

namespace Crm\UsersModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface GoogleTokenSignInDataProviderInterface extends DataProviderInterface
{
    /***
     * @param array $params {
     *   @type ActiveRow $user user being signed in
     * }
     * @return array containing additional data to be returned for sign-in user in JSON response
     */
    public function provide(array $params): array;
}
