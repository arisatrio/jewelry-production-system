<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SpkChain;
use App\Models\TrxChain;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spkchains', 'trxchain', 'mschain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }
});

test('spk show includes issued chain unit for allocated stock', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'ISS-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/ISS/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'color' => 'White Gold',
        'size' => '45',
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
        'color' => 'Rose Gold',
        'chain_length' => 44.5,
        'weight' => 10.25,
    ]);

    try {
        $this->get(route('spk.show', $production))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('spk/show')
                ->has('chains.0.issuedUnit.color')
                ->where('chains.0.issuedUnit.color', 'Rose Gold')
                ->where('chains.0.issuedUnit.size', '45 cm')
                ->where('chains.0.issuedUnit.chainLength', '45')
                ->where('chains.0.color', 'White Gold')
            );
    } finally {
        $unit->delete();
        $chain->delete();
        $production->delete();
        $master->delete();
    }
});
