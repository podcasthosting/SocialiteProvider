<?php

declare(strict_types=1);

namespace PodcastHosting\Podcaster\SocialiteProvider;

enum Scope: string
{
    case UserReadOnly = 'user-read-only';
    case FeedsReadOnly = 'feeds-read-only';
}
