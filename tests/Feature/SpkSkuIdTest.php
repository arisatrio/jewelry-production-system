<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('spk table has sku_id column on third connection', function () {
    expect(Schema::connection('third')->hasColumn('spk', 'sku_id'))->toBeTrue();
});

test('spk store saves sku_id for selected product item', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Sku Product '.Str::upper(Str::random(6)),
    ]);

    $response = $this->post(route('spk.store'), [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Save sku id',
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
        ->where('description', 'Save sku id')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $response->assertRedirect(route('spk.form', $production->row_id));

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.skuId', (string) $sku->id)
            ->where('production.categoryPrefixId', (string) $category->id)
            ->has('options.skus')
            ->has('options.categories')
        );

    $production->delete();
    $sku->delete();
});

test('spk form update persists sku_id', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'UPD-'.Str::upper(Str::random(8)),
    ]);
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'description' => 'Update sku',
        'category_prefix_id' => $category->id,
        'sku_id' => null,
        'status' => '',
        'is_deleted' => 0,
    ]);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 3,
        'priority' => 'YES',
        'description' => 'Update sku',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '15',
        'gold_weight' => 2,
        'gold_color' => 'White Gold',
        'status_order' => 'RO',
    ])->assertRedirect();

    $production->refresh();

    expect($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $production->delete();
    $sku->delete();
});
