<?php

use App\Models\MsShape;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validSpkStoneSizePayload(array $overrides = []): array
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
        'description' => 'SPK with stone size',
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

test('spk can save stone size as pxl string', function () {
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    $description = 'SPK stone PxL '.Str::upper(Str::random(6));

    $response = $this->post(route('spk.store'), validSpkStoneSizePayload([
        'description' => $description,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'pcs' => 2,
                'carat_per_pcs' => '0.150',
                'size' => '3.50x2.10',
            ],
        ],
    ]));

    $production = Production::query()
        ->notDeleted()
        ->where('description', $description)
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull();
    $response->assertRedirect(route('spk.form', $production->row_id));

    $stone = SpkStone::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone->size)->toBe('3.50x2.10')
        ->and((string) $stone->carat)->toBe('0.300');

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
});

test('spk normalizes multiplication sign in stone size to x', function () {
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    $description = 'SPK stone times '.Str::upper(Str::random(6));

    $this->post(route('spk.store'), validSpkStoneSizePayload([
        'description' => $description,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'pcs' => 1,
                'carat_per_pcs' => '0.100',
                'size' => '3,50×2,10',
            ],
        ],
    ]))->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', $description)
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull();

    $stone = SpkStone::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone->size)->toBe('3,50x2,10');

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
});

test('spk rejects invalid stone size format', function () {
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    $this->from(route('spk.create'))
        ->post(route('spk.store'), validSpkStoneSizePayload([
            'stones' => [
                [
                    'shape_id' => $shape->row_id,
                    'pcs' => 1,
                    'carat_per_pcs' => '0.100',
                    'size' => 'abc',
                ],
            ],
        ]))
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('stones.0.size');
});
