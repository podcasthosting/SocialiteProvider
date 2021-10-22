<?php
/**
 * User: Fabio Bacigalupo
 * Date: 18.06.19
 * Time: 09:29
 */

namespace podcasthosting\podcaster\socialiteprovider;

use SocialiteProviders\Manager\SocialiteWasCalled;

class PodcasterExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('podcaster', __NAMESPACE__ . '\Provider');
    }
}
