<?php

use App\Models\Production;
use App\Models\SpkStone;
use App\Models\User;
use App\Support\SpkStoneFulfillment;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('spkstone')) {
        $this->markTestSkipped('Table spkstone is not available.');
    }

    if (! Schema::connection('third')->hasColumn('spkstone', 'fulfillment_status')) {
        $this->markTestSkipped('Column spkstone.fulfillment_status is not available.');
    }
});

test('spk stone fulfillment marks row as ordering', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $production = Production::query()->notDeleted()->find($stone->row_id);

    expect($production)->not->toBeNull();

    $user = User::factory()->create(['name' => 'Stone Order Tester']);

    $this->actingAs($user);

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::NOT_ALLOCATED]);

    $this->post(route('spk.stones.fulfillment', $stone), [
        'action' => SpkStoneFulfillment::ACTION_MARK_ORDERING,
    ])->assertRedirect();

    expect($stone->refresh()->fulfillment_status)
        ->toBe(SpkStoneFulfillment::ORDERING);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('stones', fn ($stones) => collect($stones)->contains(
                fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $stone->line_id
                    && ($row['allocation']['status'] ?? null) === SpkStoneFulfillment::ORDERING
                    && ($row['allocation']['label'] ?? null) === 'Sedang Diorder'
            ))
        );
});

test('spk stone fulfillment rejects duplicate order action', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $this->post(route('spk.stones.fulfillment', $stone), [
        'action' => SpkStoneFulfillment::ACTION_MARK_ORDERING,
    ])->assertStatus(422);
});
