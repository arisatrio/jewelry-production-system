<?php

use App\Models\MsShape;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('spk service generates yearly sequential number', function () {
    $service = app(SpkService::class);
    $year = now()->format('Y');
    $prefix = "{$year}/PRD/";

    $latest = Production::query()
        ->where('spk_no', 'like', $prefix.'%')
        ->orderByDesc('spk_no')
        ->value('spk_no');

    $expectedNext = 1;

    if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches) === 1) {
        $expectedNext = (int) $matches[1] + 1;
    }

    $number = $service->generateNumber(Carbon::parse("{$year}-06-01"));

    expect($number)->toBe(sprintf('%s%05d', $prefix, $expectedNext));
});

test('spk service creates stock header with empty status', function () {
    $production = app(SpkService::class)->createStock('tester');

    expect($production->spk_type)->toBe('Stock')
        ->and($production->status)->toBe('')
        ->and($production->spk_no)->toMatch('/^\d{4}\/PRD\/\d{5}$/')
        ->and($production->created_by)->toBe('tester')
        ->and($production->is_deleted)->toBe(0);

    $production->delete();
});

test('spk service copies fields from reference spk', function () {
    $reference = Production::query()
        ->notDeleted()
        ->where('status', 'SPKDONE')
        ->whereNotNull('spk_no')
        ->orderByDesc('row_id')
        ->first();

    expect($reference)->not->toBeNull();

    $production = app(SpkService::class)->createFromReferenceSpk(
        (int) $reference->row_id,
        'Reparasi',
        'tester',
    );

    expect($production->spk_type)->toBe('Reparasi')
        ->and($production->ref_spk_id)->toBe($reference->row_id)
        ->and($production->description)->toBe($reference->description)
        ->and($production->item_id)->toBe($reference->item_id)
        ->and($production->qty)->toBe($reference->qty)
        ->and($production->diameter_length_ringsize)->toBe($reference->diameter_length_ringsize)
        ->and($production->gold_weight)->toEqual($reference->gold_weight)
        ->and($production->gold_color)->toBe($reference->gold_color)
        ->and($production->gold_content)->toBe($reference->gold_content)
        ->and($production->priority)->toBe($reference->priority)
        ->and($production->category_prefix_id)->toBe($reference->category_prefix_id)
        ->and($production->sku_id)->toBe($reference->sku_id);

    $production->delete();
});

test('spk service creates stock with details and generates number on save', function () {
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

    $production = app(SpkService::class)->createWithDetails([
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Create with details',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'frame_id' => null,
        'qty' => 2,
        'satuan' => 'Pcs',
        'status_order' => 'NO',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 2.5,
        'gold_color' => 'Yellow Gold',
        'gold_content' => 'Polish',
        'notes' => 'Unit test',
    ], 'tester');

    expect($production->spk_type)->toBe('Stock')
        ->and($production->spk_no)->toMatch('/^\d{4}\/PRD\/\d{5}$/')
        ->and($production->description)->toBe('Create with details')
        ->and($production->work_estimated)->toBe(5)
        ->and($production->estimated_delivery_time?->toDateString())->toBe('2026-08-10')
        ->and($production->category_prefix_id)->toBe($category->id)
        ->and($production->item_id)->toBeNull()
        ->and($production->item_name)->toBe($category->category)
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->supplier_id)->toBe(SpkService::DEFAULT_SUPPLIER_ID)
        ->and($production->created_by)->toBe('tester');

    $production->delete();
    $sku->delete();
});

test('spk service links selected sku master as product item', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Service Type '.fake()->unique()->numerify('####'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'SVC-'.fake()->unique()->numerify('####'),
        'item_original' => 'Service Product',
    ]);
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    $production = app(SpkService::class)->createWithDetails([
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 3,
        'priority' => 'NO',
        'description' => 'Service Product',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'diameter' => '15',
        'dimensi' => null,
        'ring_size' => null,
        'qty' => 1,
        'satuan' => 'Pcs',
        'status_order' => 'NO',
        'diameter_length_ringsize' => '15',
        'gold_weight' => 1.75,
        'gold_color' => 'Rose Gold',
        'jwcad_3d' => 'CAD-1',
        'notes' => null,
        'stones' => [
            [
                'shape_id' => $shape->row_id,
                'pcs' => 2,
                'carat_per_pcs' => '0.250',
                'size' => '1.00',
            ],
        ],
    ], 'tester');

    expect($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id)
        ->and($production->description)->toBe('Service Product');

    $production->delete();
    $sku->delete();
});

test('spk service calculates estimated delivery from working days', function () {
    $service = app(SpkService::class);

    // Senin 3 Agu 2026 + 5 hari kerja = Senin 10 Agu 2026
    $delivery = $service->calculateEstimatedDelivery(
        Carbon::parse('2026-08-03'),
        5,
    );

    expect($delivery->toDateString())->toBe('2026-08-10');

    // Jumat + 1 hari kerja = Senin
    $fridayPlusOne = $service->calculateEstimatedDelivery(
        Carbon::parse('2026-08-07'),
        1,
    );

    expect($fridayPlusOne->toDateString())->toBe('2026-08-10');
});
