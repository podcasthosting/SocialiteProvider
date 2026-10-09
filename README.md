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

The provider requests the `user-read-only` scope by default.

To additionally request read access to the user's podcasts/feeds, call `withFeedsReadOnly()`:

```php
return Socialite::driver('podcaster')->withFeedsReadOnly()->redirect();
```

`feeds-read-only` is optional for the user: it can be deselected on the podcaster consent screen. Check which scopes were actually granted:

```php
use PodcastHosting\Podcaster\SocialiteProvider\Scope;

$user = Socialite::driver('podcaster')->user();

if (in_array(Scope::FeedsReadOnly->value, $user->approvedScopes, true)) {
    // access token may read feeds
}
```

The granted scopes are taken from the `scopes` claim of the access token.

## User data

User details are fetched from `https://podcaster.rest/v1/user`. The Socialite user is mapped as follows: `id`, `nickname` (podcaster username), `name`, `email`, `avatar` (`null` if the account has no avatar). All attributes returned by the API are available via `getRaw()`.

## PKCE

PKCE (RFC 7636) is **enabled by default** with `S256` as code challenge method. The provider stores the `code_verifier` in the Laravel session during `redirect()` and submits it automatically in the token exchange request. No additional configuration is required.

If you need to disable PKCE for any reason, extend the provider and set `protected $usesPKCE = false;`.

## Testing

The test suite uses [Pest](https://pestphp.com) and [Orchestra Testbench](https://github.com/orchestral/testbench):

```bash
composer test      # Pest
composer analyse   # PHPStan (level max)
composer lint      # php-cs-fixer dry run, `composer format` applies fixes
composer coverage  # code coverage (min. 100 %, needs PCOV or Xdebug)
composer mutate    # mutation testing (min. 100 % score)
composer check     # lint, analyse, test
```

- `tests/Unit` – the provider in isolation (authorization URL, scopes, PKCE, token exchange, user mapping, approved scopes)
- `tests/Feature` – the full login flow through `Socialite::driver('podcaster')` inside a Laravel application
- `tests/ArchTest.php` – architecture rules (strict types, no debug calls, class contracts)
