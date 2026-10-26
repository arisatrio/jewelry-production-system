<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\TrxChain;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['mschain', 'trxchain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }
});

test('chain index includes stock aggregates from trxchain', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'STK-'.Str::upper(Str::random(8)),
        'length' => '45.00',
    ]);

    $inStock = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);
    $issued = TrxChain::factory()->issued()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    try {
        $this->get(route('inventory.chains.index', ['search' => $master->item_code]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/index')
                ->where('items.data.0.stockIn', 2)
                ->where('items.data.0.stockOut', 1)
                ->where('items.data.0.balance', 1)
            );
    } finally {
        $inStock->delete();
        $issued->delete();
        $master->delete();
    }
});

test('chain show page lists trxchain units', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'SHW-'.Str::upper(Str::random(8)),
    ]);
    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'name' => 'Show Unit Test',
    ]);

    try {
        $this->get(route('inventory.chains.show', $master))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/show')
                ->where('item.id', $master->row_id)
                ->where('item.stockIn', 1)
                ->where('item.balance', 1)
                ->has('colorOptions', 4)
                ->has('units.data', 1)
                ->where('units.data.0.name', 'Show Unit Test')
            );
    } finally {
        $unit->delete();
        $master->delete();
    }
});

test('chain stock can be received from supplier', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'RCV-'.Str::upper(Str::random(8)),
        'length' => '40.00',
    ]);

    try {
        $this->post(route('inventory.chains.stock.store', $master), [
            'entry_date' => '2026-10-23',
            'supplier' => 'Supplier Test',
            'weight' => '1.25',
            'purchase_price' => '1250.50',
            'color' => 'White Gold',
            'notes' => 'Masuk gudang',
            'return_to' => 'show',
        ])
            ->assertRedirect(route('inventory.chains.show', $master));

        $unit = TrxChain::query()
            ->where('mschain_id', $master->row_id)
            ->where('supplier', 'Supplier Test')
            ->first();

        expect($unit)->not->toBeNull()
            ->and($unit->unit_no)->toMatch('/^CHA\d{4}$/')
            ->and($unit->source_type)->toBe(TrxChain::SOURCE_PURCHASE)
            ->and($unit->chain_length)->toBe('40.00')
            ->and($unit->weight)->toBe('1.25')
            ->and($unit->purchase_price)->toBe('1250.50')
            ->and($unit->color)->toBe('White Gold')
            ->and($unit->out_date)->toBeNull()
            ->and($unit->created_by)->toBe('system');

        $unit?->delete();
    } finally {
        $master->delete();
    }
});

test('in stock chain unit can be issued manually', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'OUT-'.Str::upper(Str::random(8)),
    ]);
    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
    ]);
    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/OUT/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    try {
        $this->post(route('inventory.chains.stock.issue', $unit), [
            'out_date' => '2026-10-24',
            'spk_id' => $production->row_id,
            'notes' => 'Keluar manual',
        ])
            ->assertRedirect(route('inventory.chains.show', $master));

        $unit->refresh();

        expect($unit->out_date?->format('Y-m-d'))->toBe('2026-10-24')
            ->and($unit->is_used)->toBe(1)
            ->and((int) $unit->spk_id)->toBe((int) $production->row_id)
            ->and($unit->notes)->toBe('Keluar manual');
    } finally {
        $unit->delete();
        $production->delete();
        $master->delete();
    }
});

test('chain stock issue from spk allocation redirects to spk detail', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'OUT-SPK-'.Str::upper(Str::random(8)),
    ]);
    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
    ]);
    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/SPK/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    try {
        $this->post(route('inventory.chains.stock.issue', $unit), [
            'out_date' => '2026-10-24',
            'spk_id' => $production->row_id,
            'return_to' => 'spk',
        ])
            ->assertRedirect(route('spk.show', $production->spk_no));
    } finally {
        $unit->delete();
        $production->delete();
        $master->delete();
    }
});

test('chain stock issue requires valid spk', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'OUT-INV-'.Str::upper(Str::random(8)),
    ]);
    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
    ]);

    try {
        $this->from(route('inventory.chains.show', $master))
            ->post(route('inventory.chains.stock.issue', $unit), [
                'out_date' => '2026-10-24',
            ])
            ->assertSessionHasErrors('spk_id');

        expect($unit->fresh()->out_date)->toBeNull();
    } finally {
        $unit->delete();
        $master->delete();
    }
});

test('chain stock store can redirect back to index', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'IDX-'.Str::upper(Str::random(8)),
        'length' => '40.00',
    ]);

    try {
        $this->post(route('inventory.chains.stock.store', $master), [
            'entry_date' => '2026-10-23',
            'supplier' => 'Supplier Index',
            'weight' => '2.00',
            'purchase_price' => '99.00',
            'color' => 'Yellow Gold',
            'return_to' => 'index',
        ])
            ->assertRedirect(route('inventory.chains.index'));

        TrxChain::query()
            ->where('mschain_id', $master->row_id)
            ->where('supplier', 'Supplier Index')
            ->first()
            ?->delete();
    } finally {
        $master->delete();
    }
});

test('chain stock create page is accessible', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'CRT-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.chains.stock.create', $master))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/stock/create')
                ->where('item.id', $master->row_id)
                ->has('colorOptions', 4)
            );
    } finally {
        $master->delete();
    }
});
