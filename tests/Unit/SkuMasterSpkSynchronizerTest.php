<?php

use App\Models\MsShape;
use App\Models\SkuMaster;
use App\Models\SkuMasterDiamond;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Support\SkuMasterSpkSynchronizer;
use App\Support\SpkService;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array{0: array<string, mixed>, 1: SkuMaster}
 */
function skuMasterSyncSpkPayload(array $overrides = []): array
{
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = $overrides['sku'] ?? SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'gold_weight' => 5.750,
    ]);

    unset($overrides['sku']);

    $payload = [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'SPK sku sync '.Str::upper(Str::random(6)),
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 5.750,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        ...$overrides,
    ];

    return [$payload, $sku];
}

test('synchronizer updates gold weight only when value differs', function () {
    $sku = SkuMaster::factory()->create([
        'gold_weight' => 2.500,
        'modified_by' => 'before',
    ]);

    $sync = app(SkuMasterSpkSynchronizer::class);

    $sync->sync($sku, [
        'gold_weight' => 2.500,
        'stones' => [],
    ], 'actor-a');

    $sku->refresh();

    expect((float) $sku->gold_weight)->toBe(2.5)
        ->and($sku->modified_by)->toBe('before');

    $sync->sync($sku, [
        'gold_weight' => 3.125,
        'stones' => [],
    ], 'actor-b');

    $sku->refresh();

    expect((float) $sku->gold_weight)->toBe(3.125)
        ->and($sku->modified_by)->toBe('actor-b');

    $sku->delete();
});

test('synchronizer replaces diamonds only when stone list differs', function () {
    $shape = MsShape::query()->notDeleted()->whereNotNull('code')->orderBy('name')->first();
    expect($shape)->not->toBeNull();

    $sku = SkuMaster::factory()->create([
        'gold_weight' => 1.000,
    ]);

    $diamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'grain' => 3,
        'grade' => '0.150',
        'diamond_type' => $shape->code,
        'diameter' => '1.20',
        'position' => 'Top',
        'modified_by' => 'seed',
    ]);

    $sync = app(SkuMasterSpkSynchronizer::class);

    $sync->sync($sku, [
        'gold_weight' => 1.000,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'Top',
                'pcs' => 3,
                'carat_per_pcs' => '0.150',
                'size' => '1.20',
            ],
        ],
    ], 'actor-same');

    $diamond->refresh();

    expect($diamond->is_deleted)->toBe(0)
        ->and($diamond->modified_by)->toBe('seed');

    $sync->sync($sku, [
        'gold_weight' => 1.000,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'Bottom',
                'pcs' => 5,
                'carat_per_pcs' => '0.200',
                'size' => '1.80',
            ],
        ],
    ], 'actor-changed');

    $diamond->refresh();
    $active = SkuMasterDiamond::query()
        ->notDeleted()
        ->where('row_id', $sku->id)
        ->get();

    expect($diamond->is_deleted)->toBe(1)
        ->and($active)->toHaveCount(1)
        ->and($active[0]->grain)->toBe(5)
        ->and((string) $active[0]->position)->toBe('Bottom')
        ->and($active[0]->modified_by)->toBe('actor-changed');

    SkuMasterDiamond::query()->where('row_id', $sku->id)->delete();
    $sku->delete();
});

test('synchronizer skips when sku is null', function () {
    app(SkuMasterSpkSynchronizer::class)->sync(null, [
        'gold_weight' => 9.9,
        'stones' => [],
    ], 'actor');

    expect(true)->toBeTrue();
});

test('spk createWithDetails syncs changed gold weight and diamonds to sku master', function () {
    $shape = MsShape::query()->notDeleted()->whereNotNull('code')->orderBy('name')->first();
    expect($shape)->not->toBeNull();

    [$payload, $sku] = skuMasterSyncSpkPayload([
        'gold_weight' => 6.900,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'Center Sync',
                'pcs' => 4,
                'carat_per_pcs' => '0.250',
                'size' => '1.50',
            ],
        ],
    ]);

    $production = app(SpkService::class)->createWithDetails($payload, 'sync-actor');

    $sku->refresh();
    $diamonds = SkuMasterDiamond::query()
        ->notDeleted()
        ->where('row_id', $sku->id)
        ->orderBy('line_id')
        ->get();

    expect((float) $production->gold_weight)->toBe(6.9)
        ->and((float) $sku->gold_weight)->toBe(6.9)
        ->and($sku->modified_by)->toBe('sync-actor')
        ->and($diamonds)->toHaveCount(1)
        ->and($diamonds[0]->grain)->toBe(4)
        ->and((string) $diamonds[0]->grade)->toBe('0.250')
        ->and((string) $diamonds[0]->diameter)->toBe('1.50')
        ->and((string) $diamonds[0]->position)->toBe('Center Sync')
        ->and(strtoupper((string) $diamonds[0]->diamond_type))->toBe(strtoupper((string) $shape->code));

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $diamonds->each->delete();
    $production->delete();
    $sku->delete();
});

test('spk createWithDetails does not rewrite sku master when values match', function () {
    $shape = MsShape::query()->notDeleted()->whereNotNull('code')->orderBy('name')->first();
    expect($shape)->not->toBeNull();

    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'gold_weight' => 4.500,
        'modified_by' => 'seed-actor',
    ]);

    $diamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'grain' => 2,
        'grade' => '0.100',
        'diamond_type' => $shape->code,
        'diameter' => '0.80',
        'position' => 'Side',
        'modified_by' => 'seed-actor',
    ]);

    $originalDiamondId = $diamond->line_id;

    [$payload] = skuMasterSyncSpkPayload([
        'sku' => $sku,
        'gold_weight' => 4.500,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'Side',
                'pcs' => 2,
                'carat_per_pcs' => '0.100',
                'size' => '0.80',
            ],
        ],
    ]);

    $production = app(SpkService::class)->createWithDetails($payload, 'sync-actor');

    $sku->refresh();
    $diamond->refresh();

    expect((float) $sku->gold_weight)->toBe(4.5)
        ->and($sku->modified_by)->toBe('seed-actor')
        ->and($diamond->is_deleted)->toBe(0)
        ->and($diamond->line_id)->toBe($originalDiamondId)
        ->and($diamond->modified_by)->toBe('seed-actor');

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $diamond->delete();
    $production->delete();
    $sku->delete();
});

test('spk saveHeader syncs diamond changes without rewriting unchanged gold weight', function () {
    $shape = MsShape::query()->notDeleted()->whereNotNull('code')->orderBy('name')->first();
    expect($shape)->not->toBeNull();

    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'gold_weight' => 3.000,
        'modified_by' => 'seed-actor',
    ]);

    $oldDiamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'grain' => 1,
        'grade' => '0.050',
        'diamond_type' => $shape->code,
        'diameter' => '1.00',
        'position' => 'Old',
    ]);

    $production = app(SpkService::class)->createStock('system');

    app(SpkService::class)->saveHeader($production, [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Updated stones sync '.Str::upper(Str::random(4)),
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.000,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'position_nama' => 'New Pos',
                'pcs' => 6,
                'carat_per_pcs' => '0.200',
                'size' => '2.10',
            ],
        ],
    ], 'sync-actor');

    $oldDiamond->refresh();
    $sku->refresh();
    $active = SkuMasterDiamond::query()
        ->notDeleted()
        ->where('row_id', $sku->id)
        ->orderBy('line_id')
        ->get();

    expect((float) $sku->gold_weight)->toBe(3.0)
        ->and($sku->modified_by)->toBe('seed-actor')
        ->and($oldDiamond->is_deleted)->toBe(1)
        ->and($active)->toHaveCount(1)
        ->and($active[0]->grain)->toBe(6)
        ->and((string) $active[0]->position)->toBe('New Pos');

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    SkuMasterDiamond::query()->where('row_id', $sku->id)->delete();
    $production->delete();
    $sku->delete();
});
