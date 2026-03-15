<?php

declare(strict_types=1);

namespace podcasthosting\podcaster\socialiteprovider;

use SocialiteProviders\Manager\SocialiteWasCalled;

class PodcasterExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('podcaster', Provider::class);
    }
}
