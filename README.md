# SocialiteProvider for podcaster

Laravel Socialite Provider for logging in via the podcaster service ([www.podcaster.de](https://www.podcaster.de)).

## Requirements

- PHP `^8.3`
- `socialiteproviders/manager` `^4.4`

## Installation

```bash
composer require podcasthosting/socialiteprovider
```

## Configuration

### 1. Add credentials to `config/services.php`

```php
'podcaster' => [
    'client_id'     => env('PODCASTER_CLIENT_ID'),
    'client_secret' => env('PODCASTER_CLIENT_SECRET'),
    'redirect'      => env('PODCASTER_REDIRECT_URI'),
],
```

### 2. Register the event listener

In `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \PodcastHosting\Podcaster\SocialiteProvider\PodcasterExtendSocialite::class,
    ],
];
```

## Usage

```php
return Socialite::driver('podcaster')->redirect();
```

```php
$user = Socialite::driver('podcaster')->user();
```

## Scopes

The provider requests the `read-only-user` scope by default.
