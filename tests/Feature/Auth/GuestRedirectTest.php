<?php

use App\Models\User;

test('guests are redirected to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    'home' => '/',
    'dashboard' => '/dashboard',
    'spk index' => '/spk',
    'spk create' => '/spk/create',
    'spk print' => '/spk/print',
    'master data tipe item' => '/master-data/tipe-item',
    'master data varian item' => '/master-data/varian-item',
    'settings profile' => '/settings/profile',
    'settings security' => '/settings/security',
    'settings appearance' => '/settings/appearance',
]);

test('authenticated users can visit the home page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk();
});
