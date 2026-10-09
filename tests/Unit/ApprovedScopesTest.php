<?php

declare(strict_types=1);

describe('parseApprovedScopes', function () {
    it('reads the scopes claim of the Passport access token', function () {
        $scopes = invoke(provider(), 'parseApprovedScopes', tokenResponse(['user-read-only', 'feeds-read-only']));

        expect($scopes)->toBe(['user-read-only', 'feeds-read-only']);
    });

    it('reports a deselected optional scope as not approved', function () {
        $scopes = invoke(provider(), 'parseApprovedScopes', tokenResponse(['user-read-only']));

        expect($scopes)->toBe(['user-read-only'])->not->toContain('feeds-read-only');
    });

    it('prefers an explicit scope field of the token response', function (string|array $scope, array $expected) {
        $scopes = invoke(provider(), 'parseApprovedScopes', tokenResponse(['user-read-only'], ['scope' => $scope]));

        expect($scopes)->toBe($expected);
    })->with([
        'space separated string' => ['user-read-only feeds-read-only', ['user-read-only', 'feeds-read-only']],
        'double space'           => ['user-read-only  feeds-read-only', ['user-read-only', 'feeds-read-only']],
        'single string'          => ['feeds-read-only', ['feeds-read-only']],
        'array'                  => [['feeds-read-only'], ['feeds-read-only']],
    ]);

    it('falls back to the token claim when the scope field is empty', function (string|array $scope) {
        $scopes = invoke(provider(), 'parseApprovedScopes', tokenResponse(['user-read-only'], ['scope' => $scope]));

        expect($scopes)->toBe(['user-read-only']);
    })->with([
        'empty string' => [''],
        'empty array'  => [[]],
    ]);

    it('decodes base64url payloads containing - and _', function () {
        // "subjects" chosen so the base64 encoding contains characters that differ in base64url
        $claims = ['sub' => '>>>???', 'scopes' => ['user-read-only']];
        $token = jwt($claims);

        expect(explode('.', $token)[1])->toMatch('/[-_]/')
            ->and(invoke(provider(), 'parseApprovedScopes', ['access_token' => $token]))->toBe(['user-read-only']);
    });

    it('returns an empty list for tokens it cannot read', function (mixed $accessToken) {
        expect(invoke(provider(), 'parseApprovedScopes', ['access_token' => $accessToken]))->toBe([]);
    })->with([
        'missing'          => [null],
        'opaque'           => ['opaque-token'],
        'two segments'     => ['a.b'],
        'four segments'    => ['a.b.c.d'],
        'invalid base64'   => ['a.!!!.c'],
        'payload not json' => ['a.' . rtrim(base64_encode('not json'), '=') . '.c'],
        'payload scalar'   => ['a.' . rtrim(base64_encode('42'), '=') . '.c'],
        'non-string token' => [['a', 'b', 'c']],
    ]);

    it('ignores malformed scopes claims', function (mixed $claim, array $expected) {
        $token = jwt(['sub' => '42', 'scopes' => $claim]);

        expect(invoke(provider(), 'parseApprovedScopes', ['access_token' => $token]))->toBe($expected);
    })->with([
        'string instead of list' => ['user-read-only', []],
        'null'                   => [null, []],
        'mixed list'             => [['user-read-only', 7, null, ['x']], ['user-read-only']],
        'empty list'             => [[], []],
    ]);

    it('rejects payloads with characters outside of base64url', function () {
        [$header, $payload, $signature] = explode('.', jwt(['scopes' => ['user-read-only']]));

        $tampered = $header . '.' . substr($payload, 0, 4) . '*' . substr($payload, 4) . '.' . $signature;

        expect(invoke(provider(), 'parseApprovedScopes', ['access_token' => $tampered]))->toBe([]);
    });

    it('returns no scopes when the claim is missing', function () {
        expect(invoke(provider(), 'parseApprovedScopes', ['access_token' => jwt(['sub' => '42'])]))->toBe([]);
    });

    it('returns a list, never an associative array', function () {
        $token = jwt(['scopes' => [5 => 'user-read-only', 9 => 'feeds-read-only']]);

        expect(invoke(provider(), 'parseApprovedScopes', ['access_token' => $token]))->toBeList();
    });
});
