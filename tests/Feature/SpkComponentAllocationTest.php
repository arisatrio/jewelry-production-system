<?php

use App\Models\MsComponent;
use App\Models\Production;
use App\Models\SpkComponent;
use App\Models\TrxComponent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spkcomponents', 'trxcomponent', 'mscomponent'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }
});

test('spk component allocation units endpoint returns in stock units', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Test '.Str::upper(Str::random(6));

    $master = MsComponent::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'component_category' => $category,
    ]);

    $chain = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $master->row_id,
    ]);

    $unit = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.components.allocation-units', $chain))
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

test('spk component allocation units endpoint lists linked master units first', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Test '.Str::upper(Str::random(6));

    $linkedMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'component_category' => $category,
    ]);

    $otherMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-'.Str::upper(Str::random(6)),
        'component_category' => $category,
    ]);

    $chain = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $linkedMaster->row_id,
    ]);

    $linkedUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $linkedMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $otherMasterUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.components.allocation-units', $chain))
            ->assertOk()
            ->assertJsonCount(2, 'data.units')
            ->assertJsonPath('data.units.0.id', $linkedUnit->row_id)
            ->assertJsonPath('data.units.0.matchesMasterComponent', true)
            ->assertJsonPath('data.units.1.id', $otherMasterUnit->row_id)
            ->assertJsonPath('data.units.1.matchesMasterComponent', false);
    } finally {
        $otherMasterUnit->delete();
        $linkedUnit->delete();
        $chain->delete();
        $otherMaster->delete();
        $linkedMaster->delete();
    }
});

test('spk component allocation units endpoint returns in stock units for same category masters', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Test '.Str::upper(Str::random(6));
    $otherCategory = 'Alloc Other '.Str::upper(Str::random(6));

    $linkedMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-A-'.Str::upper(Str::random(4)),
        'component_category' => $category,
    ]);

    $otherMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-B-'.Str::upper(Str::random(4)),
        'component_category' => $category,
    ]);

    $braceletMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-C-'.Str::upper(Str::random(4)),
        'component_category' => $otherCategory,
    ]);

    $chain = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $linkedMaster->row_id,
    ]);

    $linkedUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $linkedMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $sameCategoryUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    $otherCategoryUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $braceletMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $response = $this->getJson(route('spk.components.allocation-units', $chain))
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

test('spk component allocation action is offered when linked master stock is empty', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $category = 'Alloc Empty '.Str::upper(Str::random(6));

    $linkedMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-E-'.Str::upper(Str::random(4)),
        'component_category' => $category,
    ]);

    $otherMaster = MsComponent::factory()->create([
        'item_code' => 'ALC-F-'.Str::upper(Str::random(4)),
        'component_category' => $category,
    ]);

    $chain = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $linkedMaster->row_id,
    ]);

    $otherUnit = TrxComponent::factory()->create([
        'mscomponent_id' => $otherMaster->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->getJson(route('spk.components.allocation-units', $chain))
            ->assertOk()
            ->assertJsonPath('data.allocation.nextAction', 'allocate')
            ->assertJsonPath('data.allocation.nextActionLabel', 'Alokasi Komponen')
            ->assertJsonPath('data.units.0.id', $otherUnit->row_id);
    } finally {
        $otherUnit->delete();
        $chain->delete();
        $otherMaster->delete();
        $linkedMaster->delete();
    }
});

test('spk component can be allocated by issuing stock to spk line', function () {
    if (! Schema::connection('third')->hasColumn('spkcomponents', 'fulfillment_status')) {
        $this->markTestSkipped('Column spkcomponents.fulfillment_status is not available.');
    }

    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsComponent::factory()->create([
        'item_code' => 'ISS-'.Str::upper(Str::random(6)),
    ]);

    $spkComponent = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $master->row_id,
    ]);

    $unit = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);

    try {
        $this->post(route('inventory.components.stock.issue', $unit), [
            'out_date' => now()->toDateString(),
            'spk_id' => $production->row_id,
            'spk_chain_line_id' => $spkComponent->line_id,
            'return_to' => 'spk',
        ])->assertRedirect(route('spk.show', $production));

        $spkComponent->refresh();
        $unit->refresh();

        expect($spkComponent->fulfillment_status)->toBe('allocated')
            ->and((int) $spkComponent->mscomponent_id)->toBe((int) $master->row_id)
            ->and($unit->out_date)->not->toBeNull()
            ->and((int) $unit->spk_id)->toBe((int) $production->row_id);

        $unit->update([
            'out_date' => null,
            'spk_id' => null,
            'is_used' => 0,
        ]);
    } finally {
        $unit->delete();
        $spkComponent->delete();
        $master->delete();
    }
});
