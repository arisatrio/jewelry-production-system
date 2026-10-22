<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkChain;
use App\Support\SpkService;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validSpkChainPayload(array $overrides = []): array
{
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

    return [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'SPK with chains',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        ...$overrides,
    ];
}

test('spk can be created with chain list', function () {
    $description = 'SPK chains '.Str::upper(Str::random(6));

    $response = $this->post(route('spk.store'), validSpkChainPayload([
        'description' => $description,
        'chains' => [
            [
                'category' => 'Rantai',
                'item' => 'Cable Chain',
                'color' => 'Yellow Gold',
                'size' => '40',
            ],
            [
                'category' => 'Kalung',
                'item' => 'Box Chain',
                'color' => 'White Gold',
                'size' => '45',
            ],
        ],
    ]));

    $production = Production::query()
        ->notDeleted()
        ->where('description', $description)
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull();
    $response->assertRedirect(route('spk.show', $production->spk_no));

    $chains = SpkChain::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->orderBy('line_id')
        ->get();

    expect($chains)->toHaveCount(2)
        ->and($chains[0]->category)->toBe('Rantai')
        ->and($chains[0]->item)->toBe('Cable Chain')
        ->and($chains[0]->color)->toBe('Yellow Gold')
        ->and($chains[0]->size)->toBe('40')
        ->and($chains[1]->item)->toBe('Box Chain');

    $chains->each->delete();
    $production->delete();
});

test('spk form save replaces chain list', function () {
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

    SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'category' => 'Old',
        'item' => 'Old chain',
        'color' => 'Yellow Gold',
        'size' => '30',
    ]);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Updated with chains',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        'chains' => [
            [
                'category' => 'Gelang',
                'item' => 'Snake Chain',
                'color' => 'Rose Gold',
                'size' => '18',
            ],
        ],
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $active = SpkChain::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active[0]->item)->toBe('Snake Chain')
        ->and($active[0]->size)->toBe('18');

    expect(
        SpkChain::query()
            ->where('row_id', $production->row_id)
            ->where('is_deleted', 1)
            ->count()
    )->toBeGreaterThanOrEqual(1);

    SpkChain::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
    $sku->delete();
});
