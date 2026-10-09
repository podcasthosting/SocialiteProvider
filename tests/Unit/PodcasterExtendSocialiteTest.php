<?php

declare(strict_types=1);

use PodcastHosting\Podcaster\SocialiteProvider\PodcasterExtendSocialite;
use PodcastHosting\Podcaster\SocialiteProvider\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

it('registers the provider under the "podcaster" driver name', function () {
    $event = Mockery::mock(SocialiteWasCalled::class);
    $event->shouldReceive('extendSocialite')->once()->with('podcaster', Provider::class);

    (new PodcasterExtendSocialite())->handle($event);
});

it('exposes the identifier used by socialiteproviders/manager', function () {
    expect(Provider::IDENTIFIER)->toBe('PODCASTER');
});
