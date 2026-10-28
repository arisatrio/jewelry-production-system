<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SpkChain;
use App\Models\TrxChain;
use App\Support\SpkChainFulfillment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spkchains', 'trxchain', 'mschain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    if (
        ! Schema::connection('third')->hasColumn('spkchains', 'fulfillment_status')
        && ! Schema::connection('third')->hasColumn('spk_chains', 'fulfillment_status')
    ) {
        $this->markTestSkipped('fulfillment_status column is not available.');
    }
});

test('spk chain revoke allocation returns unit to stock', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'REV-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/REV/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'fulfillment_status' => SpkChainFulfillment::ALLOCATED,
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
        'is_used' => 1,
    ]);

    try {
        $this->post(route('spk.chains.revoke-allocation', $chain))
            ->assertRedirect();

        $unit->refresh();
        $chain->refresh();

        expect($unit->out_date)->toBeNull()
            ->and((int) $unit->is_used)->toBe(0)
            ->and($unit->spk_id)->toBeNull()
            ->and($chain->fulfillment_status)->toBe(SpkChainFulfillment::NOT_ALLOCATED);
    } finally {
        $unit->delete();
        $chain->delete();
        $production->delete();
        $master->delete();
    }
});
