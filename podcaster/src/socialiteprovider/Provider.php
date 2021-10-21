<?php
/**
 * User: Fabio Bacigalupo
 * Date: 18.06.19
 * Time: 09:37
 */

namespace podcasthosting\Socialiteprovider;

use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'PODCASTER';

    const BASE_URL = 'https://www.podcaster.de';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['*'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase( self::BASE_URL . '/oauth/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return self::BASE_URL . '/oauth/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get( self::BASE_URL . '/api/user', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $u = $user['data']['attributes'];

        return (new User())->setRaw($u)->map([
            'id'       => $u['id'],
            'nickname' => $u['username'],
            'name'     => $u['name'],
            'email'    => $u['email'],
            //'avatar'   => $user['avatar'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code'
        ]);
    }
}
