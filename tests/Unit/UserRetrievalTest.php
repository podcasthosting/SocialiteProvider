<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use SocialiteProviders\Manager\OAuth2\User;

describe('getUserByToken', function () {
    it('fetches the current account from podcaster.rest with the bearer token', function () {
        $history = [];
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(userResponse())], $history));

        $user = invoke($provider, 'getUserByToken', 'access-token');

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];

        expect($user)->toBe(userResponse())
            ->and($history)->toHaveCount(1)
            ->and($request->getMethod())->toBe('GET')
            ->and((string) $request->getUri())->toBe('https://podcaster.rest/v1/user')
            ->and($request->getHeaderLine('Authorization'))->toBe('Bearer access-token')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json');
    });

    it('does not use the legacy /api/user route of app.podcaster.de', function () {
        $history = [];
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(userResponse())], $history));

        invoke($provider, 'getUserByToken', 'access-token');

        expect((string) $history[0]['request']->getUri())->not->toContain('app.podcaster.de/api/user');
    });

    it('rejects a body that is not json', function () {
        $provider = provider();
        $provider->setHttpClient(httpClient([new Response(200, [], '<html>Maintenance</html>')]));

        invoke($provider, 'getUserByToken', 'access-token');
    })->throws(JsonException::class);

    it('treats a json scalar as an empty user', function (string $body) {
        $provider = provider();
        $provider->setHttpClient(httpClient([new Response(200, [], $body)]));

        expect(invoke($provider, 'getUserByToken', 'access-token'))->toBe([]);
    })->with(['null', '"string"', '42', 'true']);

    it('propagates http errors of the api', function (int $status, string $exception) {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['message' => 'nope'], $status)]));

        expect(fn () => invoke($provider, 'getUserByToken', 'access-token'))->toThrow($exception);
    })->with([
        'unauthenticated'     => [401, ClientException::class],
        'missing scope'       => [403, ClientException::class],
        'server error'        => [500, ServerException::class],
        'service unavailable' => [503, ServerException::class],
    ]);
});

describe('mapUserToObject', function () {
    it('maps the UserResource attributes onto the Socialite user', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse());

        expect($user)->toBeInstanceOf(User::class)
            ->getId()->toBe(42)
            ->getNickname()->toBe('fabio')
            ->getName()->toBe('Fabio Bacigalupo')
            ->getEmail()->toBe('fabio@example.com')
            ->getAvatar()->toBe('https://app.podcaster.de/storage/avatar.png');
    });

    it('uses the podcaster username as nickname', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse(['username' => 'podcastfan', 'nickname' => 'ignored']));

        expect($user->getNickname())->toBe('podcastfan');
    });

    it('keeps all attributes as raw data', function () {
        $response = userResponse(['organisation' => 'podcaster GmbH', 'country' => 'DE']);

        $user = invoke(provider(), 'mapUserToObject', $response);

        expect($user->getRaw())->toBe($response['data']['attributes'])
            ->and($user['organisation'])->toBe('podcaster GmbH')
            ->and($user['country'])->toBe('DE');
    });

    it('maps missing attributes to null', function (string $attribute, string $getter) {
        $response = userResponse();
        unset($response['data']['attributes'][$attribute]);

        expect(invoke(provider(), 'mapUserToObject', $response)->{$getter}())->toBeNull();
    })->with([
        'id'       => ['id', 'getId'],
        'nickname' => ['username', 'getNickname'],
        'name'     => ['name', 'getName'],
        'email'    => ['email', 'getEmail'],
        'avatar'   => ['avatar', 'getAvatar'],
    ]);

    it('maps an account without avatar to null', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse(['avatar' => null]));

        expect($user->getAvatar())->toBeNull();
    });

    it('survives unexpected payloads without crashing', function (array $payload) {
        $user = invoke(provider(), 'mapUserToObject', $payload);

        expect($user)
            ->getId()->toBeNull()
            ->getEmail()->toBeNull()
            ->getRaw()->toBe([]);
    })->with([
        'empty'                  => [[]],
        'raw model, no envelope' => [['id' => 42, 'email' => 'fabio@example.com']],
        'no attributes'          => [['data' => ['type' => 'user', 'id' => 42]]],
        'error body'             => [['message' => 'Unauthenticated.']],
    ]);
});
