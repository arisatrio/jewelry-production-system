<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spk edit form maps ring size only label into ring size field', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Ring Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Ring Product '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'diameter_length_ringsize' => ' /  / Size 12 HK',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.diameter', '')
            ->where('production.dimensi', '')
            ->where('production.ringSize', 'Size 12 HK')
        );

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('item.diameter', '-')
            ->where('item.dimensi', '-')
            ->where('item.ringSize', 'Size 12 HK')
        );

    $production->delete();
    $sku->delete();
});

test('spk edit form maps collapsed slash ring size label correctly', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Slash Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Slash Product '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'diameter_length_ringsize' => '//Size 12 HK',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(90000, 99999)),
    ]);

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.diameter', '')
            ->where('production.dimensi', '')
            ->where('production.ringSize', 'Size 12 HK')
        );

    $production->delete();
    $sku->delete();
});
