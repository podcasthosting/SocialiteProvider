<?php
/**
 * User: Fabio Bacigalupo
 * Date: 18.06.19
 * Time: 09:37
 */

namespace podcasthosting\podcaster\socialiteprovider;

use SocialiteProviders\Manager\Contracts\OAuth2\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'PODCASTER';

    const BASE_URL = 'https://www.podcaster.de';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['read-only-user'];

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
        $response = $this->getHttpClient()->get(
            self::BASE_URL . '/api/user',
            $this->getRequestOptions($token)
        );

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
            'nickname' => $u['nickname'],
            'name'     => $u['name'],
            'email'    => $u['email'],
            'avatar'   => $u['avatar'],
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

    /**
     * Get the default options for an HTTP request.
     *
     * @param  string  $token
     * @return array
     */
    protected function getRequestOptions($token)
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ];
    }
}
