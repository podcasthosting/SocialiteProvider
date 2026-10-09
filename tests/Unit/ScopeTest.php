<?php

declare(strict_types=1);

use PodcastHosting\Podcaster\SocialiteProvider\Scope;

it('mirrors the scope names registered with Passport on app.podcaster.de', function (Scope $scope, string $passportName) {
    expect($scope->value)->toBe($passportName);
})->with([
    'user'  => [Scope::UserReadOnly, 'user-read-only'],
    'feeds' => [Scope::FeedsReadOnly, 'feeds-read-only'],
]);

it('only offers read-only scopes', function () {
    expect(array_column(Scope::cases(), 'value'))
        ->toHaveCount(2)
        ->each->toEndWith('-read-only');
});

it('resolves from the raw scope string', function () {
    expect(Scope::from('feeds-read-only'))->toBe(Scope::FeedsReadOnly)
        ->and(Scope::tryFrom('read-only-user'))->toBeNull();
});
