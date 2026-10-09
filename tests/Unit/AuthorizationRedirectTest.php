<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PodcastHosting\Podcaster\SocialiteProvider\Provider;
use PodcastHosting\Podcaster\SocialiteProvider\Scope;

describe('endpoints', function () {
    it('authorizes against app.podcaster.de', function () {
        expect(Provider::BASE_URL)->toBe('https://app.podcaster.de')
            ->and(provider()->redirect()->getTargetUrl())->toStartWith('https://app.podcaster.de/oauth/authorize?');
    });

    it('exchanges the code at the Passport token endpoint', function () {
        expect(invoke(provider(), 'getTokenUrl'))->toBe('https://app.podcaster.de/oauth/token');
    });

    it('reads user data from the versioned REST API', function () {
        expect(Provider::API_URL)->toBe('https://podcaster.rest/v1');
    });

    it('only talks https', function (string $url) {
        expect($url)->toStartWith('https://');
    })->with([
        'base url' => Provider::BASE_URL,
        'api url'  => Provider::API_URL,
    ]);
});

describe('redirect', function () {
    it('returns a redirect response to the authorize endpoint', function () {
        $response = provider()->redirect();

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toStartWith('https://app.podcaster.de/oauth/authorize?');
    });

    it('sends the authorization code request parameters', function () {
        $query = queryOf(provider()->stateless()->redirect()->getTargetUrl());

        expect($query)
            ->client_id->toBe('client-id')
            ->redirect_uri->toBe('https://example.com/callback')
            ->response_type->toBe('code')
            ->scope->toBe('user-read-only')
            ->not->toHaveKey('state');
    });

    it('stores a random state in the session and sends it along', function () {
        $request = Request::create('/');
        $request->setLaravelSession($session = arraySession());

        $query = queryOf(provider($request)->redirect()->getTargetUrl());

        expect($session->get('state'))->toBeString()->toHaveLength(40)
            ->and($query['state'])->toBe($session->get('state'));
    });

    it('generates a fresh state for every redirect', function () {
        $first = queryOf(provider()->redirect()->getTargetUrl())['state'];
        $second = queryOf(provider()->redirect()->getTargetUrl())['state'];

        expect($first)->not->toBe($second);
    });

    it('omits the state when used stateless', function () {
        $request = Request::create('/');
        $request->setLaravelSession($session = arraySession());

        provider($request)->stateless()->redirect();

        expect($session->has('state'))->toBeFalse();
    });

    it('passes custom parameters through with()', function () {
        $query = queryOf(provider()->with(['prompt' => 'consent'])->redirect()->getTargetUrl());

        expect($query['prompt'])->toBe('consent');
    });

    it('allows overriding the redirect url', function () {
        $query = queryOf(provider()->redirectUrl('https://other.example/cb')->redirect()->getTargetUrl());

        expect($query['redirect_uri'])->toBe('https://other.example/cb');
    });
});

describe('scopes', function () {
    it('requests user-read-only by default', function () {
        expect(provider()->getScopes())->toBe([Scope::UserReadOnly->value]);
    });

    it('never requests the legacy, unknown scope name', function () {
        expect(provider()->stateless()->redirect()->getTargetUrl())->not->toContain('read-only-user');
    });

    it('adds feeds-read-only on request', function () {
        expect(provider()->withFeedsReadOnly()->getScopes())->toBe(['user-read-only', 'feeds-read-only']);
    });

    it('is fluent', function () {
        $provider = provider();

        expect($provider->withFeedsReadOnly())->toBe($provider);
    });

    it('does not request feeds-read-only twice', function () {
        expect(provider()->withFeedsReadOnly()->withFeedsReadOnly()->scopes(['feeds-read-only'])->getScopes())
            ->toBe(['user-read-only', 'feeds-read-only']);
    });

    it('separates scopes with a space as Passport expects', function () {
        $url = provider()->withFeedsReadOnly()->stateless()->redirect()->getTargetUrl();

        expect(queryOf($url)['scope'])->toBe('user-read-only feeds-read-only')
            ->and($url)->not->toContain('user-read-only%2Cfeeds-read-only');
    });

    it('supports replacing the scopes entirely', function () {
        $query = queryOf(provider()->setScopes(['feeds-read-only'])->stateless()->redirect()->getTargetUrl());

        expect($query['scope'])->toBe('feeds-read-only');
    });
});

describe('PKCE', function () {
    it('is enabled by default', function () {
        expect(invoke(provider(), 'usesPKCE'))->toBeTrue();
    });

    it('sends an S256 code challenge', function () {
        $query = queryOf(provider()->stateless()->redirect()->getTargetUrl());

        expect($query)
            ->code_challenge_method->toBe('S256')
            ->code_challenge->toMatch('/^[A-Za-z0-9_-]{43}$/');
    });

    it('derives the challenge from the verifier stored in the session (RFC 7636)', function () {
        $request = Request::create('/');
        $request->setLaravelSession($session = arraySession());

        $query = queryOf(provider($request)->stateless()->redirect()->getTargetUrl());

        $verifier = $session->get('code_verifier');
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        expect($verifier)->toMatch('/^[A-Za-z0-9\-._~]{43,128}$/')
            ->and($query['code_challenge'])->toBe($expected);
    });

    it('creates a new verifier for every redirect', function () {
        $request = Request::create('/');
        $request->setLaravelSession($session = arraySession());
        $provider = provider($request)->stateless();

        $provider->redirect();
        $first = $session->get('code_verifier');
        $provider->redirect();

        expect($session->get('code_verifier'))->not->toBe($first);
    });
});
