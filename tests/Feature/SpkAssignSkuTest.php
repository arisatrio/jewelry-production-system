<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use Illuminate\Support\Str;

test('spk assign sku endpoint links sku to production without sku', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'ASN-'.Str::upper(Str::random(8)),
    ]);

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'sku_id' => null,
        'category_prefix_id' => null,
        'is_deleted' => 0,
    ]);

    try {
        $this->postJson(route('spk.assign-sku', $production->row_id), [
            'category_prefix_id' => $category->id,
            'sku_id' => $sku->id,
        ])
            ->assertOk()
            ->assertJsonPath('rowId', (int) $production->row_id);

        $production->refresh();

        expect($production->sku_id)->toBe($sku->id)
            ->and($production->category_prefix_id)->toBe($category->id);
    } finally {
        $production->delete();
        $sku->delete();
    }
});

test('spk assign sku options endpoint returns categories and skus', function () {
    $this->getJson(route('spk.assign-sku.options'))
        ->assertOk()
        ->assertJsonStructure([
            'categories',
            'skus',
        ]);
});

test('spk index exposes can assign sku flag', function () {
    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('bulkActions.canAssignSku')
        );
});
