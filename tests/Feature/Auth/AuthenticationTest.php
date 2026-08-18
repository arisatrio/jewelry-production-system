<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'user_id' => $user->user_id,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'user_id' => $user->user_id,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'user_id' => $user->user_id,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate with unknown user id', function () {
    $this->post(route('login.store'), [
        'user_id' => 'unknown-user',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users can logout from the home page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('home'))
        ->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->user_id, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'user_id' => $user->user_id,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('users can authenticate with quick login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.quick'), [
        'user_id' => $user->user_id,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));
});

test('quick login fails for unknown user id', function () {
    $response = $this->post(route('login.quick'), [
        'user_id' => 'unknown-user',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('user_id');
});

test('quick login with two factor enabled is redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.quick'), [
        'user_id' => $user->user_id,
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});
