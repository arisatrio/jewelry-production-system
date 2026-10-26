<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SpkChain;
use App\Models\TrxChain;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spk_chains', 'trxchain', 'mschain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }
});

test('chain index includes pending spk chain request count', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsChain::factory()->create([
        'item_code' => 'REQ-'.Str::upper(Str::random(6)),
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain pending test',
    ]);

    try {
        $this->get(route('inventory.chains.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chainRequestCount', fn ($count) => $count >= 1)
            );
    } finally {
        $chain->delete();
        $master->delete();
    }
});

test('chain requests api lists pending spk chains', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsChain::factory()->create([
        'item_code' => 'API-'.Str::upper(Str::random(6)),
        'item_name' => 'Chain API Test',
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain API',
        'color' => 'Rose Gold',
        'size' => '18',
    ]);

    try {
        $this->getJson(route('inventory.chains.requests', ['search' => $production->spk_no]))
            ->assertOk()
            ->assertJsonPath('meta.tabCounts.pending', fn ($count) => $count >= 1)
            ->assertJsonFragment([
                'spkId' => $production->row_id,
                'msChainId' => $master->row_id,
                'chainColor' => 'Rose Gold',
            ]);
    } finally {
        $chain->delete();
        $master->delete();
    }
});

test('fulfilled spk chain moves to fulfilled tab when trxchain issued', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsChain::factory()->create([
        'item_code' => 'FUL-'.Str::upper(Str::random(6)),
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'out_date' => now()->toDateString(),
    ]);

    try {
        $this->getJson(route('inventory.chains.requests', [
            'tab' => 'fulfilled',
            'search' => $production->spk_no,
        ]))
            ->assertOk()
            ->assertJsonFragment([
                'lineId' => $chain->line_id,
                'spkId' => $production->row_id,
            ]);

        $pendingResponse = $this->getJson(route('inventory.chains.requests', [
            'tab' => 'pending',
            'search' => $production->spk_no,
        ]))
            ->assertOk();

        $pendingLineIds = collect($pendingResponse->json('data'))->pluck('lineId');

        expect($pendingLineIds)->not->toContain($chain->line_id);
    } finally {
        $unit->delete();
        $chain->delete();
        $master->delete();
    }
});
