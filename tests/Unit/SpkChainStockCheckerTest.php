<?php

use App\Models\MsChain;
use App\Models\SpkChain;
use Tests\TestCase;

uses(TestCase::class);

use App\Models\TrxChain;
use App\Support\SpkChainFulfillment;
use App\Support\SpkChainStockChecker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('trxchain')) {
        $this->markTestSkipped('trxchain table is not available.');
    }
});

test('chain stock checker marks row available when master has in-stock units', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'CHK-'.Str::upper(Str::random(6)),
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
        'chain_length' => 18,
    ]);

    $chain = SpkChain::factory()->make([
        'line_id' => 900001,
        'mschain_id' => $master->row_id,
    ]);

    try {
        $results = app(SpkChainStockChecker::class)->forChains(collect([$chain]));

        expect($results[900001])
            ->status->toBe(SpkChainStockChecker::STATUS_AVAILABLE)
            ->availableUnits->toBe(1)
            ->requiredUnits->toBe(1)
            ->balanceLength->toBe('18.00');
    } finally {
        $unit->delete();
        $master->delete();
    }
});

test('chain stock checker is unavailable when master is not linked', function () {
    $chain = SpkChain::factory()->make([
        'line_id' => 900002,
        'mschain_id' => null,
    ]);

    $results = app(SpkChainStockChecker::class)->allocationForChains(collect([$chain]));

    expect($results[900002])
        ->status->toBe(SpkChainFulfillment::UNLINKED)
        ->allocatedUnits->toBe(0);
});
