<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Facades\Schema;

test('spk table has is_from_new_system column on third connection', function () {
    expect(Schema::connection('third')->hasColumn('spk', 'is_from_new_system'))->toBeTrue();
});

test('spk factory defaults is_from_new_system to zero', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'description' => 'Legacy factory default',
        'status' => '',
        'is_deleted' => 0,
    ]);

    expect($production->is_from_new_system)->toBe(0);

    $production->delete();
});

test('spk store sets is_from_new_system to one', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
    ]);

    $response = $this->post(route('spk.store'), [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Create from new system',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
    ]);

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Create from new system')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->is_from_new_system)->toBe(1);

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
    $sku->delete();
});

test('spk service create stock sets is_from_new_system to one', function () {
    $production = app(SpkService::class)->createStock('tester');

    expect($production->is_from_new_system)->toBe(1);

    $production->delete();
});
