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

    $category = 'Alloc Test '.Str::upper(Str::random(6));

    $master = MsChain::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'chain_category' => $category,
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
            ->assertJsonPath('data.units.0.id', $unit->row_id)
            ->assertJsonStructure([
                'data' => [
                    'colorOptions',
                    'suggestedColor',
                ],
            ]);
    } finally {
        $unit->delete();
        $chain->delete();
        $master->delete();
    }
});

test('spk chain allocation units endpoint lists linked master units first', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Test '.Str::upper(Str::random(6));

    $linkedMaster = MsChain::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'chain_category' => $category,
    ]);

    $otherMaster = MsChain::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'chain_category' => $category,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $linkedMaster->row_id,
    ]);

    $linkedUnit = TrxChain::factory()->create([
        'mschain_id' => $linkedMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $otherMasterUnit = TrxChain::factory()->create([
        'mschain_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.chains.allocation-units', $chain))
            ->assertOk()
            ->assertJsonCount(2, 'data.units')
            ->assertJsonPath('data.units.0.id', $linkedUnit->row_id)
            ->assertJsonPath('data.units.0.matchesMasterChain', true)
            ->assertJsonPath('data.units.1.id', $otherMasterUnit->row_id)
            ->assertJsonPath('data.units.1.matchesMasterChain', false);
    } finally {
        $otherMasterUnit->delete();
        $linkedUnit->delete();
        $chain->delete();
        $otherMaster->delete();
        $linkedMaster->delete();
    }
});

test('spk chain allocation units endpoint returns in stock units for same category masters', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Test '.Str::upper(Str::random(6));
    $otherCategory = 'Alloc Other '.Str::upper(Str::random(6));

    $linkedMaster = MsChain::factory()->create([
        'item_code' => 'ALC-A-'.Str::upper(Str::random(4)),
        'chain_category' => $category,
    ]);

    $otherMaster = MsChain::factory()->create([
        'item_code' => 'ALC-B-'.Str::upper(Str::random(4)),
        'chain_category' => $category,
    ]);

    $braceletMaster = MsChain::factory()->create([
        'item_code' => 'ALC-C-'.Str::upper(Str::random(4)),
        'chain_category' => $otherCategory,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $linkedMaster->row_id,
    ]);

    $linkedUnit = TrxChain::factory()->create([
        'mschain_id' => $linkedMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $sameCategoryUnit = TrxChain::factory()->create([
        'mschain_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $otherCategoryUnit = TrxChain::factory()->create([
        'mschain_id' => $braceletMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $response = $this->getJson(route('spk.chains.allocation-units', $chain))
            ->assertOk();

        $unitIds = collect($response->json('data.units'))->pluck('id')->all();

        expect($unitIds)
            ->toContain($linkedUnit->row_id, $sameCategoryUnit->row_id)
            ->not->toContain($otherCategoryUnit->row_id)
            ->and($unitIds[0])->toBe($linkedUnit->row_id);
    } finally {
        $otherCategoryUnit->delete();
        $sameCategoryUnit->delete();
        $linkedUnit->delete();
        $chain->delete();
        $braceletMaster->delete();
        $otherMaster->delete();
        $linkedMaster->delete();
    }
});

test('spk chain allocation action is offered when linked master stock is empty', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Empty '.Str::upper(Str::random(6));

    $linkedMaster = MsChain::factory()->create([
        'item_code' => 'ALC-E-'.Str::upper(Str::random(4)),
        'chain_category' => $category,
    ]);

    $otherMaster = MsChain::factory()->create([
        'item_code' => 'ALC-F-'.Str::upper(Str::random(4)),
        'chain_category' => $category,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $linkedMaster->row_id,
    ]);

    $otherUnit = TrxChain::factory()->create([
        'mschain_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.chains.allocation-units', $chain))
            ->assertOk()
            ->assertJsonPath('data.allocation.nextAction', 'allocate')
            ->assertJsonPath('data.allocation.nextActionLabel', 'Alokasi Chain')
            ->assertJsonPath('data.units.0.id', $otherUnit->row_id);
    } finally {
        $otherUnit->delete();
        $chain->delete();
        $otherMaster->delete();
        $linkedMaster->delete();
    }
});
