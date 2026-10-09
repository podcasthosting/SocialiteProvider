<?php

declare(strict_types=1);

namespace PodcastHosting\Podcaster\SocialiteProvider;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\Token;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use UnexpectedValueException;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PODCASTER';

    public const BASE_URL = 'https://app.podcaster.de';

    public const API_URL = 'https://podcaster.rest/v1';

    /** @var list<string> */
    protected $scopes = [Scope::UserReadOnly->value];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    /**
     * Additionally request read access to the user's podcasts/feeds. The user
     * may deselect it on the consent screen, so check $user->approvedScopes.
     */
    public function withFeedsReadOnly(): static
    {
        return $this->scopes([Scope::FeedsReadOnly->value]);
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::BASE_URL . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return self::BASE_URL . '/oauth/token';
    }

    /**
     * @return array<mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            self::API_URL . '/user',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ],
        );

        $user = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        return is_array($user) ? $user : [];
    }

    /**
     * @param  array<mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $data = $user['data'] ?? null;
        $attributes = is_array($data) && is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return (new User())->setRaw($attributes)->map([
            'id'       => $attributes['id'] ?? null,
            'nickname' => $attributes['username'] ?? null,
            'name'     => $attributes['name'] ?? null,
            'email'    => $attributes['email'] ?? null,
            'avatar'   => $attributes['avatar'] ?? null,
        ]);
    }

    public function refreshToken($refreshToken): Token
    {
        $response = $this->getRefreshTokenResponse($refreshToken);
        $response = is_array($response) ? $response : [];

        $accessToken = Arr::get($response, 'access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new UnexpectedValueException('The token response of podcaster does not contain an access token.');
        }

        // RFC 6749 section 6: without a new refresh token the client keeps the old one.
        $newRefreshToken = Arr::get($response, 'refresh_token');
        $expiresIn = Arr::get($response, 'expires_in');

        return new Token(
            $accessToken,
            is_string($newRefreshToken) && $newRefreshToken !== '' ? $newRefreshToken : (string) $refreshToken,
            is_numeric($expiresIn) ? (int) $expiresIn : 0,
            $this->parseApprovedScopes($response),
        );
    }

    /**
     * Passport does not return the granted scopes in the token response, but
     * embeds them as "scopes" claim in the access token (JWT).
     *
     * @param  array<mixed>  $body
     * @return list<string>
     */
    protected function parseApprovedScopes($body): array
    {
        $scopes = array_values(array_filter(
            parent::parseApprovedScopes($body),
            static fn (mixed $scope): bool => is_string($scope) && $scope !== '',
        ));

        if ($scopes !== []) {
            return $scopes;
        }

        $accessToken = $body['access_token'] ?? null;
        $segments = is_string($accessToken) ? explode('.', $accessToken) : [];

        if (count($segments) !== 3) {
            return [];
        }

        $payload = json_decode(
            (string) base64_decode(strtr($segments[1], '-_', '+/'), true),
            true,
        );

        $claimed = is_array($payload) ? ($payload['scopes'] ?? []) : [];

        return is_array($claimed) ? array_values(array_filter($claimed, 'is_string')) : [];
    }
}
