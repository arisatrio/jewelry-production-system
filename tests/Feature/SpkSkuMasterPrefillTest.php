<?php

use App\Models\MsShape;
use App\Models\SkuMaster;
use App\Models\SkuMasterDiamond;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spk create options include gold weight and sku diamonds', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $round = MsShape::query()->notDeleted()->where('code', 'R')->first();

    expect($round)->not->toBeNull();

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Prefill SKU '.Str::upper(Str::random(4)),
        'gold_weight' => 5.75,
    ]);
    $diamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'diamond_type' => 'RD',
        'grain' => 12,
        'grade' => '0.110',
        'diameter' => null,
        'position' => null,
    ]);

    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('options.skus', function ($skus) use ($sku, $round) {
                $match = collect($skus)->firstWhere('value', (string) $sku->id);

                if ($match === null) {
                    return false;
                }

                $stone = $match['stones'][0] ?? null;

                return $match['goldWeight'] === '5.750'
                    && is_array($stone)
                    && $stone['shapeId'] === (string) $round->row_id
                    && $stone['pcs'] === '12'
                    && $stone['caratPerPcs'] === '0.110';
            })
        );

    $diamond->delete();
    $sku->delete();
});

test('spk sku options omit gold weight when sku master gold_weight is empty', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'gold_weight' => null,
    ]);

    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('options.skus', function ($skus) use ($sku) {
                $match = collect($skus)->firstWhere('value', (string) $sku->id);

                return is_array($match) && $match['goldWeight'] === null;
            })
        );

    $sku->delete();
});

test('spk create options include jwcad and design image from sku master', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'file_jwlcad' => 'JWC-PREFILL-001',
        'design_image' => '1782887215_design_prefill.jpg',
        'image_url' => 'https://example.com/old-image.jpg',
    ]);

    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('options.skus', function ($skus) use ($sku) {
                $match = collect($skus)->firstWhere('value', (string) $sku->id);

                if ($match === null) {
                    return false;
                }

                return $match['jwcad3d'] === 'JWC-PREFILL-001'
                    && $match['imageUrl'] === 'https://storage.googleapis.com/system-mahakarya/produksi/1782887215_design_prefill.jpg';
            })
        );

    $sku->delete();
});

test('spk edit form keeps saved stones instead of sku diamonds', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $shape = MsShape::query()->notDeleted()->where('code', 'OV')->first()
        ?? MsShape::query()->notDeleted()->orderBy('row_id')->first();

    expect($shape)->not->toBeNull();

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'gold_weight' => 4.20,
    ]);
    $diamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'diamond_type' => 'R',
        'grain' => 8,
        'grade' => '0.200',
    ]);

    $production = app(SpkService::class)->createWithDetails([
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Keep saved stones '.Str::upper(Str::random(4)),
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 2.25,
        'gold_color' => 'Yellow Gold',
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'pcs' => 3,
                'carat_per_pcs' => '0.500',
                'size' => '2.00',
            ],
        ],
    ], 'system');

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.goldWeight', '2.250')
            ->has('stones', 1)
            ->where('stones.0.pcs', 3)
            ->where('stones.0.shapeId', (string) $shape->row_id)
        );

    $this->get(route('spk.show', $production->spk_no))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('stones.0.pcs', 3)
            ->where('stones.0.caratPerPcs', '0.500')
            ->where('stones.0.size', '2.00')
            ->where('stones.0.master.pcs', '8')
            ->where('stones.0.master.caratPerPcs', '0.200')
        );

    SpkStone::query()->where('row_id', $production->row_id)->delete();
    $production->delete();
    $diamond->delete();
    $sku->delete();
});
