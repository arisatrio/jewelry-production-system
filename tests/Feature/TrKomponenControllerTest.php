<?php

use App\Models\Production;
use App\Models\TrKomponen;
use Illuminate\Support\Str;

test('transaksi komponen index page is accessible', function () {
    $this->get(route('inventory.component-transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-komponen/index')
            ->has('items.data')
            ->has('items.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('transaksi komponen index exposes status and production number', function () {
    $production = Production::factory()->create();
    $item = TrKomponen::factory()->create([
        'spk_id' => $production->row_id,
        'is_used' => 0,
        'is_reserved' => 1,
    ]);

    $this->get(route('inventory.component-transactions.index', ['search' => $item->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.status', 'reserved')
            ->where('items.data.0.productionNo', $production->spk_no)
        );

    $item->delete();
    $production->delete();
});

test('transaksi komponen index page can filter by search query', function () {
    $token = 'KomponenFilter '.Str::upper(Str::random(8));
    $item = TrKomponen::factory()->create([
        'name' => $token,
        'jenis_komponen' => 'Chain',
    ]);

    $this->get(route('inventory.component-transactions.index', ['search' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-komponen/index')
            ->where('filters.search', $token)
            ->has('items.data.0')
            ->where('items.data.0.name', $token)
        );

    $item->delete();
});

test('transaksi komponen create page is accessible', function () {
    $this->get(route('inventory.component-transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-komponen/create')
        );
});

test('transaksi komponen can be stored', function () {
    $name = 'KomponenStore '.Str::upper(Str::random(8));

    $this->post(route('inventory.component-transactions.store'), [
        'jenis_komponen' => 'Chain',
        'name' => $name,
        'item_code' => 'TEST001',
        'length' => '40.00',
        'weight' => '1.50',
        'is_used' => 0,
        'is_reserved' => 0,
    ])
        ->assertRedirect(route('inventory.component-transactions.index'));

    $item = TrKomponen::query()
        ->notDeleted()
        ->where('name', $name)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item->jenis_komponen)->toBe('Chain');

    $item?->delete();
});

test('transaksi komponen show and edit pages are accessible', function () {
    $item = TrKomponen::factory()->create();

    $this->get(route('inventory.component-transactions.show', $item))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-komponen/show')
            ->where('item.id', $item->row_id)
        );

    $this->get(route('inventory.component-transactions.edit', $item))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-komponen/edit')
            ->where('item.id', $item->row_id)
        );

    $item->delete();
});

test('deleted transaksi komponen returns not found on show and edit', function () {
    $item = TrKomponen::factory()->deleted()->create();

    $this->get(route('inventory.component-transactions.show', $item))
        ->assertNotFound();

    $this->get(route('inventory.component-transactions.edit', $item))
        ->assertNotFound();

    $item->delete();
});

test('transaksi komponen can be updated', function () {
    $item = TrKomponen::factory()->create([
        'name' => 'Before Update',
    ]);

    $this->put(route('inventory.component-transactions.update', $item), [
        'name' => 'After Update',
        'jenis_komponen' => 'Clasp',
        'is_reserved' => 1,
    ])
        ->assertRedirect(route('inventory.component-transactions.index'));

    $item->refresh();

    expect($item->name)->toBe('After Update')
        ->and($item->jenis_komponen)->toBe('Clasp')
        ->and($item->is_reserved)->toBe(1);

    $item->delete();
});

test('transaksi komponen can be soft deleted', function () {
    $item = TrKomponen::factory()->create();

    $this->delete(route('inventory.component-transactions.destroy', $item))
        ->assertRedirect(route('inventory.component-transactions.index'));

    $item->refresh();

    expect($item->is_deleted)->toBe(1);

    $item->delete();
});
