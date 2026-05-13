<?php

declare(strict_types=1);

namespace PodcastHosting\Podcaster\SocialiteProvider;

use SocialiteProviders\Manager\SocialiteWasCalled;

final class PodcasterExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('podcaster', Provider::class);
    }
}
