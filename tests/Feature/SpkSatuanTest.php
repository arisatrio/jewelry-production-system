<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;

test('spk create page includes unit options', function () {
    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.satuan', 'Pcs')
            ->where('options.units', SpkService::UNITS)
        );
});

test('spk can be created with pasang unit', function () {
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
        'description' => 'Satuan pasang test',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pasang',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
    ]);

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Satuan pasang test')
        ->where('created_by', 'system')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->qty)->toBe(2)
        ->and($production->satuan)->toBe('Pasang')
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
    $sku->delete();
});

test('spk store validates satuan option', function () {
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

    $this->from(route('spk.create'))
        ->post(route('spk.store'), [
            'spk_type' => 'Stock',
            'order_date' => '2026-08-03',
            'work_estimated' => 5,
            'priority' => 'NO',
            'description' => 'Satuan invalid test',
            'category_prefix_id' => $category->id,
            'sku_id' => $sku->id,
            'qty' => 1,
            'satuan' => 'Gram',
            'diameter_length_ringsize' => '16',
            'gold_weight' => 1.5,
            'gold_color' => 'Yellow Gold',
            'status_order' => 'NO',
        ])
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('satuan');

    $sku->delete();
});
