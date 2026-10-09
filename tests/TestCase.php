<?php

declare(strict_types=1);

namespace PodcastHosting\Podcaster\SocialiteProvider\Tests;

use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PodcastHosting\Podcaster\SocialiteProvider\PodcasterExtendSocialite;
use SocialiteProviders\Manager\ServiceProvider as SocialiteProvidersServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            SocialiteProvidersServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('services.podcaster', [
            'client_id'     => 'client-id',
            'client_secret' => 'client-secret',
            'redirect'      => 'https://example.com/callback',
        ]);

        // Mirrors the README setup. Must be registered before the app has
        // booted: the manager dispatches SocialiteWasCalled in a booted callback.
        $app['events']->listen(SocialiteWasCalled::class, [PodcasterExtendSocialite::class, 'handle']);
    }
}
