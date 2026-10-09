<?php

declare(strict_types=1);

use SocialiteProviders\Manager\OAuth2\AbstractProvider;

arch('every file declares strict types')
    ->expect('PodcastHosting\Podcaster\SocialiteProvider')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->preset()->php();

arch('no security pitfalls')
    ->preset()->security();

arch('the provider is a socialiteproviders OAuth2 provider')
    ->expect('PodcastHosting\Podcaster\SocialiteProvider\Provider')
    ->toExtend(AbstractProvider::class);

arch('scopes are a backed string enum')
    ->expect('PodcastHosting\Podcaster\SocialiteProvider\Scope')
    ->toBeStringBackedEnum();

arch('the listener cannot be extended')
    ->expect('PodcastHosting\Podcaster\SocialiteProvider\PodcasterExtendSocialite')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('production code does not depend on tests')
    ->expect('PodcastHosting\Podcaster\SocialiteProvider')
    ->not->toUse('PodcastHosting\Podcaster\SocialiteProvider\Tests');
