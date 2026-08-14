<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('spk table has category_prefix_id column on third connection', function () {
    expect(Schema::connection('third')->hasColumn('spk', 'category_prefix_id'))->toBeTrue();
});

test('spk store saves category_prefix_id for selected tipe item', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST CAT '.Str::upper(Str::random(4)),
            'prefix' => 'T'.Str::upper(Str::random(2)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'CAT-'.Str::upper(Str::random(8)),
    ]);

    $response = $this->post(route('spk.store'), [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Save category prefix',
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
        ->where('description', 'Save category prefix')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->category_prefix_id)->toBe($category->id)
        ->and($production->item_name)->toBe($category->category)
        ->and($production->sku_id)->toBe($sku->id);

    $response->assertRedirect(route('spk.form', $production->row_id));

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.categoryPrefixId', (string) $category->id)
            ->has('options.categories')
        );

    $production->delete();
    $sku->delete();
});
