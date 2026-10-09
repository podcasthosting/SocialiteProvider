<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PodcastHosting\Podcaster\SocialiteProvider\Provider;
use PodcastHosting\Podcaster\SocialiteProvider\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function arraySession(): Store
{
    return new Store('podcaster_test', new ArraySessionHandler(60));
}

function provider(?Request $request = null): Provider
{
    if ($request === null) {
        $request = Request::create('/callback', 'GET');
        $request->setLaravelSession(arraySession());
    }

    return new Provider($request, 'client-id', 'client-secret', 'https://example.com/callback');
}

/**
 * Guzzle client answering with the given responses. Every sent request is
 * recorded in $history as ['request' => RequestInterface, ...].
 *
 * @param  list<Response|Throwable>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function httpClient(array $responses, array &$history = []): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client(['handler' => $stack]);
}

function jsonResponse(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
}

/**
 * Unsigned JWT as issued by Passport — only the payload matters to the provider.
 */
function jwt(array $claims): string
{
    $encode = static fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    return $encode(['typ' => 'JWT', 'alg' => 'RS256']) . '.' . $encode($claims) . '.signature';
}

/**
 * Token endpoint response of app.podcaster.de (Passport).
 *
 * @param  list<string>  $scopes
 */
function tokenResponse(array $scopes = ['user-read-only'], array $overrides = []): array
{
    return array_merge([
        'token_type'    => 'Bearer',
        'expires_in'    => 31536000,
        'access_token'  => jwt(['aud' => 'client-id', 'sub' => '42', 'scopes' => $scopes]),
        'refresh_token' => 'refresh-token',
    ], $overrides);
}

/**
 * Response of GET https://podcaster.rest/v1/user (App\Http\Resources\UserResource).
 */
function userResponse(array $attributes = []): array
{
    return [
        'data' => [
            'type'       => 'user',
            'id'         => 42,
            'attributes' => array_merge([
                'id'         => 42,
                'name'       => 'Fabio Bacigalupo',
                'first_name' => 'Fabio',
                'last_name'  => 'Bacigalupo',
                'username'   => 'fabio',
                'email'      => 'fabio@example.com',
                'avatar'     => 'https://app.podcaster.de/storage/avatar.png',
            ], $attributes),
            'links' => [
                'self' => 'https://app.podcaster.de/api/user',
            ],
        ],
    ];
}

/**
 * Query parameters of a redirect URL.
 *
 * @return array<string, string>
 */
function queryOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query;
}

/**
 * Invoke a non-public method of the provider.
 */
function invoke(object $object, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
}
