<?php

use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

test('it accepts a matching laravel password', function () {
    $user = User::factory()->make();

    expect($user->matchesPassword('password'))->toBeTrue();
});

test('it rejects an invalid password', function () {
    $user = User::factory()->make();

    expect($user->matchesPassword('wrong-password'))->toBeFalse();
});

test('it accepts a matching legacy md5 password', function () {
    $user = new User;
    $user->setRawAttributes([
        'password' => '',
        'legacy_password' => md5('secret'),
    ]);

    expect($user->matchesPassword('secret'))->toBeTrue();
});

test('it accepts a matching legacy bcrypt password', function () {
    $user = new User;
    $user->setRawAttributes([
        'password' => '',
        'legacy_password' => password_hash('secret', PASSWORD_BCRYPT),
    ]);

    expect($user->matchesPassword('secret'))->toBeTrue();
});
