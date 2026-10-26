<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkComponent;
use App\Support\SpkService;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validSpkComponentPayload(array $overrides = []): array
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
        'description' => 'SPK with components',
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

test('spk can be created with component list', function () {
    $description = 'SPK components '.Str::upper(Str::random(6));

    $response = $this->post(route('spk.store'), validSpkComponentPayload([
        'description' => $description,
        'components' => [
            [
                'category' => 'Komponen',
                'item' => 'Clasp A',
                'color' => 'Yellow Gold',
                'size' => '2',
            ],
            [
                'category' => 'Stopper',
                'item' => 'Silicone Stopper',
                'color' => 'White Gold',
                'size' => '1',
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

    $components = SpkComponent::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->orderBy('line_id')
        ->get();

    expect($components)->toHaveCount(2)
        ->and($components[0]->category)->toBe('Komponen')
        ->and($components[0]->item)->toBe('Clasp A')
        ->and($components[0]->color)->toBe('Yellow Gold')
        ->and($components[0]->size)->toBe('2')
        ->and($components[1]->category)->toBe('Stopper');

    $components->each->delete();
    $production->delete();
});

test('spk form save replaces component list', function () {
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

    SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'category' => 'Komponen',
        'item' => 'Old part',
        'color' => 'Yellow Gold',
        'size' => '3',
    ]);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Updated with components',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        'components' => [
            [
                'category' => 'Stopper',
                'item' => 'New stopper',
                'color' => 'Rose Gold',
                'size' => '1.5',
            ],
        ],
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $active = SpkComponent::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active[0]->item)->toBe('New stopper')
        ->and($active[0]->size)->toBe('1.5');

    expect(
        SpkComponent::query()
            ->where('row_id', $production->row_id)
            ->where('is_deleted', 1)
            ->count()
    )->toBeGreaterThanOrEqual(1);

    SpkComponent::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
    $sku->delete();
});
