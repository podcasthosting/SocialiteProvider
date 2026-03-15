<?php

declare(strict_types=1);

namespace podcasthosting\podcaster\socialiteprovider\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use podcasthosting\podcaster\socialiteprovider\Provider;
use SocialiteProviders\Manager\OAuth2\User;

class ProviderTest extends TestCase
{
    private function createProvider(?Client $httpClient = null): Provider
    {
        $request = Request::create('/', 'GET');
        $provider = new Provider($request, 'client-id', 'client-secret', 'https://example.com/callback');

        if ($httpClient !== null) {
            $provider->setHttpClient($httpClient);
        }

        return $provider;
    }

    private function createMockHttpClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }

    #[Test]
    public function identifier_is_podcaster(): void
    {
        $this->assertSame('PODCASTER', Provider::IDENTIFIER);
    }

    #[Test]
    public function base_url_points_to_podcaster(): void
    {
        $this->assertSame('https://www.podcaster.de', Provider::BASE_URL);
    }

    #[Test]
    public function auth_url_contains_correct_base(): void
    {
        $provider = $this->createProvider();
        $provider->stateless();

        $redirectUrl = $provider->redirect()->getTargetUrl();

        $this->assertStringStartsWith('https://www.podcaster.de/oauth/authorize', $redirectUrl);
        $this->assertStringContainsString('client_id=client-id', $redirectUrl);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://example.com/callback'), $redirectUrl);
        $this->assertStringContainsString('scope=read-only-user', $redirectUrl);
        $this->assertStringContainsString('response_type=code', $redirectUrl);
    }

    #[Test]
    public function get_user_by_token_sends_bearer_header(): void
    {
        $apiResponse = [
            'data' => [
                'attributes' => [
                    'id' => '123',
                    'nickname' => 'testuser',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => 'https://example.com/avatar.jpg',
                ],
            ],
        ];

        $httpClient = $this->createMockHttpClient([
            new Response(200, [], json_encode($apiResponse)),
        ]);

        $provider = $this->createProvider($httpClient);

        $reflection = new \ReflectionMethod($provider, 'getUserByToken');
        $result = $reflection->invoke($provider, 'test-token');

        $this->assertSame($apiResponse, $result);
    }

    #[Test]
    public function map_user_to_object_returns_user_with_correct_fields(): void
    {
        $provider = $this->createProvider();

        $apiResponse = [
            'data' => [
                'attributes' => [
                    'id' => '42',
                    'nickname' => 'podcaster42',
                    'name' => 'Fabio B.',
                    'email' => 'fabio@example.com',
                    'avatar' => 'https://example.com/avatar.png',
                ],
            ],
        ];

        $reflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $user = $reflection->invoke($provider, $apiResponse);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('42', $user->getId());
        $this->assertSame('podcaster42', $user->getNickname());
        $this->assertSame('Fabio B.', $user->getName());
        $this->assertSame('fabio@example.com', $user->getEmail());
        $this->assertSame('https://example.com/avatar.png', $user->getAvatar());
    }

    #[Test]
    public function map_user_to_object_sets_raw_attributes(): void
    {
        $provider = $this->createProvider();

        $attributes = [
            'id' => '1',
            'nickname' => 'nick',
            'name' => 'Name',
            'email' => 'e@mail.com',
            'avatar' => 'https://example.com/a.jpg',
        ];

        $apiResponse = ['data' => ['attributes' => $attributes]];

        $reflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $user = $reflection->invoke($provider, $apiResponse);

        $this->assertSame($attributes, $user->getRaw());
    }

    #[Test]
    public function get_token_fields_includes_grant_type(): void
    {
        $provider = $this->createProvider();
        $provider->stateless();

        $reflection = new \ReflectionMethod($provider, 'getTokenFields');
        $fields = $reflection->invoke($provider, 'test-code');

        $this->assertSame('authorization_code', $fields['grant_type']);
        $this->assertSame('client-id', $fields['client_id']);
        $this->assertSame('client-secret', $fields['client_secret']);
        $this->assertSame('test-code', $fields['code']);
        $this->assertSame('https://example.com/callback', $fields['redirect_uri']);
    }
}
