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

## PKCE

PKCE (RFC 7636) is **enabled by default** with `S256` as code challenge method. The provider stores the `code_verifier` in the Laravel session during `redirect()` and submits it automatically in the token exchange request. No additional configuration is required.

If you need to disable PKCE for any reason, extend the provider and set `protected $usesPKCE = false;`.
