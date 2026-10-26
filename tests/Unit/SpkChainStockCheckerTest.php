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
        'color' => '',
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

test('chain stock checker requires matching in-stock color when spk specifies color', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'CLR-'.Str::upper(Str::random(6)),
    ]);

    $matchingUnit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
        'color' => 'White Gold',
        'chain_length' => 20,
    ]);

    $otherColorUnit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
        'color' => 'Rose Gold',
        'chain_length' => 22,
    ]);

    $chainWhite = SpkChain::factory()->make([
        'line_id' => 900010,
        'mschain_id' => $master->row_id,
        'color' => 'White Gold',
    ]);

    $chainRose = SpkChain::factory()->make([
        'line_id' => 900011,
        'mschain_id' => $master->row_id,
        'color' => 'Rose Gold',
    ]);

    $chainYellow = SpkChain::factory()->make([
        'line_id' => 900012,
        'mschain_id' => $master->row_id,
        'color' => 'Yellow Gold',
    ]);

    try {
        $results = app(SpkChainStockChecker::class)->forChains(collect([
            $chainWhite,
            $chainRose,
            $chainYellow,
        ]));

        expect($results[900010])
            ->status->toBe(SpkChainStockChecker::STATUS_AVAILABLE)
            ->availableUnits->toBe(1);

        expect($results[900011])
            ->status->toBe(SpkChainStockChecker::STATUS_AVAILABLE)
            ->availableUnits->toBe(1);

        expect($results[900012])
            ->status->toBe(SpkChainStockChecker::STATUS_UNAVAILABLE)
            ->availableUnits->toBe(0)
            ->note->toBe(
                'Tidak ada stok untuk warna Yellow Gold. Masih bisa alokasi dari stok warna lain.',
            );
    } finally {
        $matchingUnit->delete();
        $otherColorUnit->delete();
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
