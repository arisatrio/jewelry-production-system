<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spk show page includes item type and variance name', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Bracelet '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'ETERNA '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $typeCode = trim((string) $category->prefix);
    $productItemName = trim((string) $sku->item_original);
    $skuCode = trim((string) $sku->sku_code);
    $expectedName = $typeCode.' | '.$productItemName;
    $itemTypeName = $category->displayName();
    $itemVariance = $sku->displayName();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.name', $expectedName)
            ->where('item.typeCode', $typeCode)
            ->where('item.productItemName', $productItemName)
            ->where('item.skuCode', $skuCode)
            ->where('item.itemType', $itemTypeName)
            ->where('item.itemVariance', $itemVariance)
        );

    $production->delete();
    $sku->delete();
});

test('spk print page includes variance name in type variant', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Ring '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'SOLAIRE '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(70000, 79999)),
    ]);

    $typeCode = trim((string) $category->prefix);
    $productItemName = trim((string) $sku->item_original);
    $skuCode = trim((string) $sku->sku_code);
    $expectedLine = $typeCode.' | '.$productItemName;

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee($expectedLine, false)
        ->assertSee($skuCode, false)
        ->assertSee('spkPrintFieldSku', false)
        ->assertSee('Tipe Item | Product Item', false);

    $production->delete();
    $sku->delete();
});
