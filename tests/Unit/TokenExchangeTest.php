<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\Token;

function callbackRequest(array $query = ['code' => 'auth-code']): Request
{
    $request = Request::create('/callback', 'GET', $query);
    $request->setLaravelSession(arraySession());

    return $request;
}

describe('token fields', function () {
    it('sends everything Passport needs for the authorization_code grant', function () {
        $fields = invoke(provider()->stateless(), 'getTokenFields', 'auth-code');

        expect($fields)
            ->grant_type->toBe('authorization_code')
            ->client_id->toBe('client-id')
            ->client_secret->toBe('client-secret')
            ->code->toBe('auth-code')
            ->redirect_uri->toBe('https://example.com/callback');
    });

    it('sends the code verifier of the preceding redirect', function () {
        $request = callbackRequest();
        $provider = provider($request)->stateless();
        $provider->redirect();
        $verifier = $request->session()->get('code_verifier');

        expect(invoke($provider, 'getTokenFields', 'auth-code'))->code_verifier->toBe($verifier);
    });

    it('consumes the code verifier so it cannot be replayed', function () {
        $request = callbackRequest();
        $provider = provider($request)->stateless();
        $provider->redirect();

        invoke($provider, 'getTokenFields', 'auth-code');

        expect($request->session()->has('code_verifier'))->toBeFalse();
    });
});

describe('getAccessTokenResponse', function () {
    it('posts the form fields to the token endpoint and decodes the json', function () {
        $history = [];
        $provider = provider()->stateless();
        $provider->setHttpClient(httpClient([jsonResponse(tokenResponse())], $history));

        $body = $provider->getAccessTokenResponse('auth-code');

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        parse_str((string) $request->getBody(), $sent);

        expect($body)->toBe(tokenResponse())
            ->and($request->getMethod())->toBe('POST')
            ->and((string) $request->getUri())->toBe('https://app.podcaster.de/oauth/token')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json')
            ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
            ->and($sent)->toMatchArray(['grant_type' => 'authorization_code', 'code' => 'auth-code']);
    });

    it('surfaces OAuth errors like invalid_grant as http exceptions', function () {
        $provider = provider()->stateless();
        $provider->setHttpClient(httpClient([jsonResponse(['error' => 'invalid_grant'], 400)]));

        $provider->getAccessTokenResponse('expired-code');
    })->throws(ClientException::class, 'invalid_grant');
});

describe('refreshToken', function () {
    it('requests a new access token with the refresh_token grant', function () {
        $history = [];
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(tokenResponse(['user-read-only', 'feeds-read-only'], [
            'refresh_token' => 'new-refresh-token',
        ]))], $history));

        $token = $provider->refreshToken('old-refresh-token');

        parse_str((string) $history[0]['request']->getBody(), $sent);

        expect($token)->toBeInstanceOf(Token::class)
            ->token->toStartWith('eyJ')
            ->refreshToken->toBe('new-refresh-token')
            ->expiresIn->toBe(31536000)
            ->approvedScopes->toBe(['user-read-only', 'feeds-read-only'])
            ->and((string) $history[0]['request']->getUri())->toBe('https://app.podcaster.de/oauth/token')
            ->and($sent)->toBe([
                'grant_type'    => 'refresh_token',
                'refresh_token' => 'old-refresh-token',
                'client_id'     => 'client-id',
                'client_secret' => 'client-secret',
            ]);
    });

    it('returns no approved scopes instead of an empty string when none are known', function () {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['access_token' => 'opaque', 'refresh_token' => 'r2', 'expires_in' => 60])]));

        expect($provider->refreshToken('r')->approvedScopes)->toBe([]);
    });

    it('keeps the old refresh token when the server does not rotate it (RFC 6749 section 6)', function (array $response) {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse($response)]));

        expect($provider->refreshToken('old-refresh-token')->refreshToken)->toBe('old-refresh-token');
    })->with([
        'missing' => [['access_token' => 'new', 'expires_in' => 60]],
        'null'    => [['access_token' => 'new', 'refresh_token' => null, 'expires_in' => 60]],
        'empty'   => [['access_token' => 'new', 'refresh_token' => '', 'expires_in' => 60]],
    ]);

    it('keeps a numeric refresh token as string', function () {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['access_token' => 'new', 'expires_in' => 60])]));

        expect($provider->refreshToken(12345)->refreshToken)->toBe('12345');
    });

    it('normalises expires_in to an integer', function (mixed $expiresIn, int $expected) {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['access_token' => 'new', 'expires_in' => $expiresIn])]));

        expect($provider->refreshToken('r')->expiresIn)->toBe($expected);
    })->with([
        'integer'        => [3600, 3600],
        'numeric string' => ['3600', 3600],
        'missing'        => [null, 0],
        'garbage'        => ['soon', 0],
    ]);

    it('rejects a response without access token', function (array|string $body) {
        $provider = provider();
        $provider->setHttpClient(httpClient([new Response(200, [], is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body)]));

        $provider->refreshToken('r');
    })->with([
        'missing'    => [['refresh_token' => 'r2']],
        'empty'      => [['access_token' => '']],
        'not string' => [['access_token' => ['x']]],
        'json null'  => ['null'],
    ])->throws(UnexpectedValueException::class, 'does not contain an access token');

    it('fails on a revoked refresh token', function () {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['error' => 'invalid_request'], 401)]));

        $provider->refreshToken('revoked');
    })->throws(ClientException::class);
});
