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

test('spk chain allocation units endpoint returns in stock units', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsChain::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.chains.allocation-units', $chain))
            ->assertOk()
            ->assertJsonPath('data.allocation.status', 'not_allocated')
            ->assertJsonPath('data.allocation.label', 'Belum Dialokasikan')
            ->assertJsonPath('data.units.0.id', $unit->row_id);
    } finally {
        $unit->delete();
        $chain->delete();
        $master->delete();
    }
});
