<?php

declare(strict_types=1);

namespace podcasthosting\podcaster\socialiteprovider\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use podcasthosting\podcaster\socialiteprovider\PodcasterExtendSocialite;
use podcasthosting\podcaster\socialiteprovider\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class PodcasterExtendSocialiteTest extends TestCase
{
    #[Test]
    public function handle_extends_socialite_with_provider_class(): void
    {
        $socialiteWasCalled = $this->createMock(SocialiteWasCalled::class);
        $socialiteWasCalled
            ->expects($this->once())
            ->method('extendSocialite')
            ->with('podcaster', Provider::class);

        $listener = new PodcasterExtendSocialite();
        $listener->handle($socialiteWasCalled);
    }
}
