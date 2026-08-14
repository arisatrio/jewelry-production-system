<?php

use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Support\SpkService;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validSpkStonePayload(array $overrides = []): array
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
        'description' => 'SPK with stones',
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

test('spk create form includes shape options for stone list', function () {
    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->has('options.shapeOptions')
            ->has('options.positionOptions')
        );
});

test('spk can be created with stone list', function () {
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();
    $position = MsPosition::factory()->create();

    expect($shape)->not->toBeNull();

    $description = 'SPK stones '.Str::upper(Str::random(6));

    $response = $this->post(route('spk.store'), validSpkStonePayload([
        'description' => $description,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_id' => $position->id,
                'pcs' => 4,
                'carat_per_pcs' => '0.250',
                'size' => '1.50',
            ],
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'Posisi SPK '.Str::upper(Str::random(5)),
                'pcs' => 2,
                'carat_per_pcs' => '0.100',
                'size' => '0.80',
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

    $stones = SpkStone::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->orderBy('line_id')
        ->get();

    expect($stones)->toHaveCount(2)
        ->and($stones[0]->shape_id)->toBe($shape->row_id)
        ->and($stones[0]->position_id)->toBe($position->id)
        ->and($stones[0]->pcs)->toBe(4)
        ->and((string) $stones[0]->carat)->toBe('1.000')
        ->and((string) $stones[0]->size)->toBe('1.50')
        ->and($stones[1]->pcs)->toBe(2)
        ->and((string) $stones[1]->carat)->toBe('0.200')
        ->and($stones[1]->position_id)->not->toBeNull();

    $createdPositionId = $stones[1]->position_id;

    $stones->each->delete();
    $production->delete();
    $position->delete();

    if ($createdPositionId !== null) {
        MsPosition::query()->whereKey($createdPositionId)->delete();
    }
});

test('spk form save replaces stone list', function () {
    $production = app(SpkService::class)->createStock('system');
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();
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

    expect($shape)->not->toBeNull();

    SpkStone::factory()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 1,
        'carat' => 0.5,
        'size' => 1,
    ]);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Updated with stones',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'pcs' => 3,
                'carat_per_pcs' => '0.200',
                'size' => '2.00',
            ],
        ],
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $active = SpkStone::query()
        ->notDeleted()
        ->where('row_id', $production->row_id)
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active[0]->pcs)->toBe(3)
        ->and((string) $active[0]->carat)->toBe('0.600');

    expect(
        SpkStone::query()
            ->where('row_id', $production->row_id)
            ->where('is_deleted', 1)
            ->count()
    )->toBeGreaterThanOrEqual(1);

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
    $sku->delete();
});
