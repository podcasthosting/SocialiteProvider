<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use PodcastHosting\Podcaster\SocialiteProvider\Provider;
use PodcastHosting\Podcaster\SocialiteProvider\Scope;
use SocialiteProviders\Manager\OAuth2\User;

/**
 * Simulates the browser coming back from app.podcaster.de: same session as
 * the redirect, with code and state in the query string.
 */
function returnFromPodcaster(array $query): void
{
    $session = app('request')->session();

    $callback = Request::create('/callback', 'GET', $query);
    $callback->setLaravelSession($session);

    app()->instance('request', $callback);
    Socialite::forgetDrivers();
}

beforeEach(function () {
    $request = Request::create('/login/podcaster');
    $request->setLaravelSession(app('session')->driver('array'));
    app()->instance('request', $request);
});

describe('driver resolution', function () {
    it('resolves the podcaster driver through socialiteproviders/manager', function () {
        expect(Socialite::driver('podcaster'))->toBeInstanceOf(Provider::class);
    });

    it('reads client credentials and redirect from config/services.php', function () {
        $query = queryOf(Socialite::driver('podcaster')->stateless()->redirect()->getTargetUrl());

        expect($query)
            ->client_id->toBe('client-id')
            ->redirect_uri->toBe('https://example.com/callback');
    });

    it('picks up changed config values', function () {
        config(['services.podcaster.client_id' => 'other-client', 'services.podcaster.redirect' => '/relative/callback']);
        Socialite::forgetDrivers();

        $query = queryOf(Socialite::driver('podcaster')->stateless()->redirect()->getTargetUrl());

        expect($query['client_id'])->toBe('other-client')
            ->and($query['redirect_uri'])->toBe(url('/relative/callback'));
    });
});

describe('complete login', function () {
    it('logs a user in end to end', function () {
        $history = [];
        $redirect = Socialite::driver('podcaster')->redirect()->getTargetUrl();
        $state = queryOf($redirect)['state'];
        $verifier = app('request')->session()->get('code_verifier');

        returnFromPodcaster(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcaster')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())], $history))
            ->user();

        parse_str((string) $history[0]['request']->getBody(), $tokenRequest);

        expect($user)->toBeInstanceOf(User::class)
            ->getId()->toBe(42)
            ->getNickname()->toBe('fabio')
            ->getEmail()->toBe('fabio@example.com')
            ->token->toBe(tokenResponse()['access_token'])
            ->refreshToken->toBe('refresh-token')
            ->expiresIn->toBe(31536000)
            ->approvedScopes->toBe(['user-read-only'])
            ->and($user->accessTokenResponseBody)->toBe(tokenResponse())
            ->and($history)->toHaveCount(2)
            ->and($tokenRequest)->toMatchArray([
                'grant_type'    => 'authorization_code',
                'code'          => 'auth-code',
                'code_verifier' => $verifier,
                'redirect_uri'  => 'https://example.com/callback',
            ])
            ->and($history[1]['request']->getHeaderLine('Authorization'))->toBe('Bearer ' . tokenResponse()['access_token']);
    });

    it('reports a granted feeds-read-only scope', function () {
        $state = queryOf(Socialite::driver('podcaster')->withFeedsReadOnly()->redirect()->getTargetUrl())['state'];
        returnFromPodcaster(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcaster')
            ->setHttpClient(httpClient([
                jsonResponse(tokenResponse(['user-read-only', 'feeds-read-only'])),
                jsonResponse(userResponse()),
            ]))
            ->user();

        expect($user->approvedScopes)->toContain(Scope::FeedsReadOnly->value);
    });

    it('reports feeds-read-only as missing when the user deselected it on the consent screen', function () {
        $state = queryOf(Socialite::driver('podcaster')->withFeedsReadOnly()->redirect()->getTargetUrl())['state'];
        returnFromPodcaster(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcaster')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse(['user-read-only'])), jsonResponse(userResponse())]))
            ->user();

        expect($user->approvedScopes)->toBe(['user-read-only'])->not->toContain(Scope::FeedsReadOnly->value);
    });

    it('caches the user for the lifetime of the provider instance', function () {
        $history = [];
        returnFromPodcaster(['code' => 'auth-code']);
        $provider = Socialite::driver('podcaster')->stateless();
        $provider->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())], $history));

        expect($provider->user())->toBe($provider->user())
            ->and($history)->toHaveCount(2);
    });
});

describe('state protection (CSRF)', function () {
    it('rejects a callback with a foreign state', function () {
        Socialite::driver('podcaster')->redirect();
        returnFromPodcaster(['code' => 'auth-code', 'state' => 'forged']);

        Socialite::driver('podcaster')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('rejects a callback without any state', function () {
        Socialite::driver('podcaster')->redirect();
        returnFromPodcaster(['code' => 'auth-code']);

        Socialite::driver('podcaster')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('rejects a callback when no login was started', function () {
        returnFromPodcaster(['code' => 'auth-code', 'state' => 'whatever']);

        Socialite::driver('podcaster')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('does not accept the same state twice', function () {
        $state = queryOf(Socialite::driver('podcaster')->redirect()->getTargetUrl())['state'];
        returnFromPodcaster(['code' => 'auth-code', 'state' => $state]);
        Socialite::driver('podcaster')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())]))
            ->user();

        returnFromPodcaster(['code' => 'auth-code', 'state' => $state]);
        Socialite::driver('podcaster')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('skips the state check when used stateless', function () {
        returnFromPodcaster(['code' => 'auth-code']);

        $user = Socialite::driver('podcaster')
            ->stateless()
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())]))
            ->user();

        expect($user->getId())->toBe(42);
    });
});

describe('failures', function () {
    it('does not call the user api when the token exchange fails', function () {
        $history = [];
        returnFromPodcaster(['code' => 'expired']);

        try {
            Socialite::driver('podcaster')
                ->stateless()
                ->setHttpClient(httpClient([jsonResponse(['error' => 'invalid_grant'], 400)], $history))
                ->user();
        } finally {
            expect($history)->toHaveCount(1);
        }
    })->throws(ClientException::class);

    it('fails when the token lacks the scope required by the user api', function () {
        returnFromPodcaster(['code' => 'auth-code']);

        Socialite::driver('podcaster')
            ->stateless()
            ->setHttpClient(httpClient([
                jsonResponse(tokenResponse(['feeds-read-only'])),
                jsonResponse(['message' => 'Invalid scope(s) provided.'], 403),
            ]))
            ->user();
    })->throws(ClientException::class, 'Invalid scope(s) provided.');
});

describe('existing tokens', function () {
    it('loads a user from a stored access token', function () {
        $history = [];

        $user = Socialite::driver('podcaster')
            ->setHttpClient(httpClient([jsonResponse(userResponse())], $history))
            ->userFromToken('stored-token');

        expect($user)
            ->getId()->toBe(42)
            ->token->toBe('stored-token')
            ->and($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer stored-token');
    });

    it('refreshes an access token and keeps track of the granted scopes', function () {
        $token = Socialite::driver('podcaster')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse(['user-read-only', 'feeds-read-only'], ['refresh_token' => 'rotated']))]))
            ->refreshToken('refresh-token');

        expect($token)
            ->refreshToken->toBe('rotated')
            ->approvedScopes->toBe(['user-read-only', 'feeds-read-only']);
    });
});
