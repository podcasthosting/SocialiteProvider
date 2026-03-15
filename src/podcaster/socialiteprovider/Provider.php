<?php

declare(strict_types=1);

namespace podcasthosting\podcaster\socialiteprovider;

use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PODCASTER';

    public const BASE_URL = 'https://app.podcaster.de';

    protected $scopes = ['read-only-user'];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::BASE_URL . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return self::BASE_URL . '/oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            self::BASE_URL . '/api/user',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ],
        );

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
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

    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
