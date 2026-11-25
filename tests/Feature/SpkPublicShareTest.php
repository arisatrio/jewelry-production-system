<?php

use App\Models\Production;
use App\Models\User;
use App\Support\SpkPublicShareToken;

test('spk show includes public share url with sharer user id', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();
    $user = auth()->user();

    expect($production)->not->toBeNull();
    expect($user)->not->toBeNull();

    $expectedUrl = route('spk.public.share', [
        'token' => SpkPublicShareToken::forProduction($production, $user->id),
    ], absolute: true);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('publicShareUrl', $expectedUrl)
        );

    $token = SpkPublicShareToken::forProduction($production, $user->id);

    expect(SpkPublicShareToken::resolve($token)['sharedByUserId'])->toBe($user->id);
});

test('guest can view public spk share page with aside data only', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();
    $user = User::factory()->create([
        'name' => 'Sharer User',
        'user_id' => 'sharer-user-'.uniqid(),
    ]);

    expect($production)->not->toBeNull();

    $token = SpkPublicShareToken::forProduction($production, $user->id);

    auth()->logout();

    $this->get(route('spk.public.share', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/spk-share')
            ->has('production')
            ->has('item')
            ->has('approvalFooter', 3)
            ->where('sharedBy.key', $user->user_id)
            ->where('sharedBy.name', $user->name)
            ->where('production.modifiedDate', $production->modified_date?->format('d-M-Y H:i') ?? '-')
            ->missing('processes')
            ->missing('stones')
            ->missing('approval')
        );
});

test('invalid public spk share token returns not found', function () {
    auth()->logout();

    $this->get(route('spk.public.share', ['token' => '1.0000000000000000']))
        ->assertNotFound();
});

test('tampered public spk share token returns not found', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();
    $user = User::factory()->create();

    expect($production)->not->toBeNull();

    $token = SpkPublicShareToken::forProduction($production, $user->id);
    $tampered = preg_replace('/\.[a-f0-9]{16}$/', '.aaaaaaaaaaaaaaaa', $token);

    auth()->logout();

    $this->get(route('spk.public.share', ['token' => $tampered]))
        ->assertNotFound();
});
