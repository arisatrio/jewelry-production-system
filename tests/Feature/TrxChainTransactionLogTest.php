<?php

use App\Models\MsChain;
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

test('chain transaction log page is accessible', function () {
    $this->get(route('inventory.chain-transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/chain/transactions/index')
            ->has('transactions.data')
            ->has('transactions.total')
            ->where('filters.search', '')
            ->where('filters.per_page', 50)
        );
});

test('chain transaction log lists trxchain rows with master info', function () {
    $token = 'TrxLog '.Str::upper(Str::random(8));
    $master = MsChain::factory()->create([
        'item' => $token,
        'item_code' => 'LOG-'.Str::upper(Str::random(8)),
    ]);
    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'name' => $token.' Unit',
        'item_code' => $master->item_code,
    ]);

    try {
        $this->get(route('inventory.chain-transactions.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/transactions/index')
                ->where('filters.search', $token)
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $unit->row_id)
                ->where('transactions.data.0.msChainId', $master->row_id)
                ->where('transactions.data.0.masterItemCode', $master->item_code)
                ->where('transactions.data.0.name', $token.' Unit')
            );
    } finally {
        $unit->delete();
        $master->delete();
    }
});
