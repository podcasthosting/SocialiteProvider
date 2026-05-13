<?php

declare(strict_types=1);

namespace PodcastHosting\Podcaster\SocialiteProvider;

use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PODCASTER';

    public const BASE_URL = 'https://app.podcaster.de';

    protected $scopes = ['read-only-user'];

    protected $usesPKCE = true;

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
                    'Accept'        => 'application/json',
                ],
            ],
        );

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function mapUserToObject(array $user): User
    {
        $attributes = $user['data']['attributes'] ?? [];

        return (new User())->setRaw($attributes)->map([
            'id'       => $attributes['id'] ?? null,
            'nickname' => $attributes['nickname'] ?? null,
            'name'     => $attributes['name'] ?? null,
            'email'    => $attributes['email'] ?? null,
            'avatar'   => $attributes['avatar'] ?? null,
        ]);
    }

    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
