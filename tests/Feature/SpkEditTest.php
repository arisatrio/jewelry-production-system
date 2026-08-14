<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;

test('spk edit form loads existing production data', function () {
    $production = app(SpkService::class)->createStock('system');
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

    $production->forceFill([
        'description' => 'Edit preload',
        'category_prefix_id' => $category->id,
        'item_id' => null,
        'item_name' => $category->category,
        'sku_id' => $sku->id,
        'qty' => 3,
        'satuan' => 'Pcs',
        'priority' => 'YES',
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
    ])->save();

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.isNew', false)
            ->where('production.id', $production->row_id)
            ->where('production.spkNo', $production->spk_no)
            ->where('production.description', 'Edit preload')
            ->where('production.skuId', (string) $sku->id)
            ->where('production.categoryPrefixId', (string) $category->id)
            ->where('production.qty', '3')
            ->where('production.satuan', 'Pcs')
            ->where('production.priority', 'YES')
        );

    $production->delete();
    $sku->delete();
});

test('spk update redirects to detail page', function () {
    $production = app(SpkService::class)->createStock('system');
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

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Updated via edit',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.5,
        'gold_color' => 'Yellow Gold',
        'gold_content' => 'Polish',
        'status_order' => 'NO',
        'notes' => 'Catatan edit',
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $production->refresh();

    expect($production->description)->toBe('Updated via edit')
        ->and($production->priority)->toBe('YES')
        ->and($production->qty)->toBe(2)
        ->and($production->notes)->toBe('Catatan edit')
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $this->get(route('spk.show', $production->spk_no))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.id', (string) $production->row_id)
            ->where('production.produksiNo', $production->spk_no)
            ->where('production.description', 'Updated via edit')
        );

    $production->delete();
    $sku->delete();
});
