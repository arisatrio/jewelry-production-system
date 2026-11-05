<?php

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

test('stone purchase index lists spk stones with ordering status', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::NOT_ALLOCATED]);

    $this->get(route('inventory.stone-purchases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/pembelian-batu/index')
            ->where('listAvailable', true)
            ->where('stones.data', fn ($rows) => collect($rows)->doesntContain(
                fn (array $row): bool => (int) ($row['lineId'] ?? 0) === (int) $stone->line_id
            ))
        );

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $this->get(route('inventory.stone-purchases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/pembelian-batu/index')
            ->where('stones.data', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => (int) ($row['lineId'] ?? 0) === (int) $stone->line_id
                    && ($row['fulfillmentLabel'] ?? null) === 'Sedang Diorder'
            ))
        );
});

test('stone purchase index lists spk stones with shipping status', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::SHIPPING]);

    $this->get(route('inventory.stone-purchases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stones.data', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => (int) ($row['lineId'] ?? 0) === (int) $stone->line_id
                    && ($row['fulfillmentStatus'] ?? null) === SpkStoneFulfillment::SHIPPING
                    && ($row['fulfillmentLabel'] ?? null) === 'Sedang Dikirim Supplier'
                    && in_array($row['stoneType'] ?? null, ['dossier', 'micro'], true)
                    && in_array($row['stoneTypeLabel'] ?? null, ['Dossier', 'Mikro'], true)
            ))
        );
});

test('stone purchase status can toggle between ordering and shipping', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $this->patch(route('inventory.stone-purchases.update-status', $stone), [
        'status' => SpkStoneFulfillment::ORDERED,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stone->refresh()->fulfillment_status)
        ->toBe(SpkStoneFulfillment::ORDERED);

    $this->patch(route('inventory.stone-purchases.update-status', $stone), [
        'status' => SpkStoneFulfillment::SHIPPING,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stone->refresh()->fulfillment_status)
        ->toBe(SpkStoneFulfillment::SHIPPING);

    $this->patch(route('inventory.stone-purchases.update-status', $stone), [
        'status' => SpkStoneFulfillment::ORDERING,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stone->refresh()->fulfillment_status)
        ->toBe(SpkStoneFulfillment::ORDERING);
});

test('stone purchase destroy resets fulfillment to not allocated', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $this->delete(route('inventory.stone-purchases.destroy', $stone))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stone->refresh()->fulfillment_status)
        ->toBe(SpkStoneFulfillment::NOT_ALLOCATED);
});

test('stone purchase index filters by spk number search', function () {
    $stone = SpkStone::query()
        ->notDeleted()
        ->with('production')
        ->first();

    expect($stone)->not->toBeNull();
    expect($stone->production)->not->toBeNull();

    $this->actingAs(User::factory()->create());

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $spkNo = (string) $stone->production->spk_no;

    $this->get(route('inventory.stone-purchases.index', ['search' => $spkNo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', $spkNo)
            ->where('stones.data', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => (int) ($row['lineId'] ?? 0) === (int) $stone->line_id
            ))
        );

    $this->get(route('inventory.stone-purchases.index', ['search' => 'ZZZ-NOMOR-TIDAK-ADA']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stones.data', fn ($rows) => collect($rows)->doesntContain(
                fn (array $row): bool => (int) ($row['lineId'] ?? 0) === (int) $stone->line_id
            ))
        );
});
