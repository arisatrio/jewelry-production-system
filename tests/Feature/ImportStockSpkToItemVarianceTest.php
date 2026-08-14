<?php

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\SpkStone;
use App\Support\ImportStockSpkToItemVariance;
use Illuminate\Support\Str;

test('import stock spk creates variance stones and links item_variance_id', function () {
    $item = MsItem::factory()->create([
        'name' => 'ImportItem '.Str::upper(Str::random(6)),
    ]);
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    $description = 'Import Stock Varian '.Str::upper(Str::random(8));

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => null,
        'description' => $description,
        'order_date' => '2026-03-15',
        'created_date' => '2026-03-15 10:00:00',
        'diameter_length_ringsize' => '14 / 10x8 / 17',
        'gold_weight' => '5.50',
        'gold_color' => 'White Gold',
        'jwcad_3d' => 'JWC-IMPORT-1',
        'file_name' => '1767583141_import_test.jpeg',
        'is_deleted' => 0,
    ]);

    $stone = SpkStone::factory()->create([
        'row_id' => $spk->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 4,
        'carat' => '1.000',
        'size' => '1.50',
        'is_deleted' => 0,
    ]);

    $result = app(ImportStockSpkToItemVariance::class)->handle(
        onlyRowIds: [$spk->row_id],
    );

    expect($result['created'])->toBe(1)
        ->and($result['linked'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($result['errors'])->toBe([]);

    $spk->refresh();

    expect($spk->item_variance_id)->not->toBeNull();

    $variance = MsItemVariance::query()->find($spk->item_variance_id);

    expect($variance)->not->toBeNull()
        ->and($variance->name)->toBe($description)
        ->and($variance->description)->toBe($description)
        ->and($variance->item_id)->toBe($item->row_id)
        ->and($variance->diameter)->toBe('14')
        ->and($variance->dimensi)->toBe('10x8')
        ->and($variance->ring_size)->toBe('17')
        ->and($variance->diameter_length_ringsize)->toBe('14 / 10x8 / 17')
        ->and((string) $variance->gold_weight)->toBe('5.50')
        ->and($variance->gold_color)->toBe('White Gold')
        ->and($variance->jwcad_3d)->toBe('JWC-IMPORT-1')
        ->and($variance->image)->toBe('1767583141_import_test.jpeg')
        ->and($variance->created_by)->toBe(ImportStockSpkToItemVariance::ACTOR);

    $varianceStone = MsItemVarianceStone::query()
        ->notDeleted()
        ->where('item_variance_id', $variance->row_id)
        ->first();

    expect($varianceStone)->not->toBeNull()
        ->and($varianceStone->shape_id)->toBe($shape->row_id)
        ->and($varianceStone->pcs)->toBe(4)
        ->and((string) $varianceStone->carat_per_pcs)->toBe('0.250')
        ->and((string) $varianceStone->total_carat)->toBe('1.000')
        ->and((string) $varianceStone->size)->toBe('1.50');

    $varianceStone->delete();
    $variance->delete();
    $stone->delete();
    $spk->delete();
    $item->delete();
});

test('import stock spk strips item type prefix from variance name', function () {
    $typeName = 'Earring'.Str::upper(Str::random(5));
    $item = MsItem::factory()->create([
        'name' => $typeName,
    ]);

    $suffix = 'Cuff '.Str::upper(Str::random(8));
    $description = $typeName.' '.$suffix;

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => null,
        'description' => $description,
        'order_date' => '2026-03-20',
        'created_date' => '2026-03-20 10:00:00',
        'gold_weight' => '2.10',
        'gold_color' => 'White Gold',
        'is_deleted' => 0,
    ]);

    app(ImportStockSpkToItemVariance::class)->handle(
        onlyRowIds: [$spk->row_id],
    );

    $spk->refresh();
    $variance = MsItemVariance::query()->find($spk->item_variance_id);

    expect($variance)->not->toBeNull()
        ->and($variance->name)->toBe($suffix)
        ->and($variance->description)->toBe($description);

    $variance->delete();
    $spk->delete();
    $item->delete();
});

test('varianceNameFromDescription strips prefix case-insensitively', function () {
    $importer = app(ImportStockSpkToItemVariance::class);

    expect($importer->varianceNameFromDescription('Earring Cuff xxxx', 'Earring'))
        ->toBe('Cuff xxxx')
        ->and($importer->varianceNameFromDescription('earring Cuff xxxx', 'Earring'))
        ->toBe('Cuff xxxx')
        ->and($importer->varianceNameFromDescription('Pendant Netizen RD', 'Pendant'))
        ->toBe('Netizen RD')
        ->and($importer->varianceNameFromDescription('Cuff xxxx', 'Earring'))
        ->toBe('Cuff xxxx')
        ->and($importer->varianceNameFromDescription('Earring', 'Earring'))
        ->toBe('Earring');
});

test('import stock spk puts free-text ukuran into dimensi', function () {
    $item = MsItem::factory()->create([
        'name' => 'ImportUkuran '.Str::upper(Str::random(6)),
    ]);

    $description = 'Import Ukuran Free '.Str::upper(Str::random(8));

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => null,
        'description' => $description,
        'order_date' => '2026-04-01',
        'created_date' => '2026-04-01 09:00:00',
        'diameter_length_ringsize' => 'Panjang 16.5 cm',
        'gold_weight' => '3.20',
        'gold_color' => 'Rose Gold',
        'is_deleted' => 0,
    ]);

    app(ImportStockSpkToItemVariance::class)->handle(
        onlyRowIds: [$spk->row_id],
    );

    $spk->refresh();
    $variance = MsItemVariance::query()->find($spk->item_variance_id);

    expect($variance)->not->toBeNull()
        ->and($variance->diameter)->toBeNull()
        ->and($variance->dimensi)->toBe('Panjang 16.5 cm')
        ->and($variance->ring_size)->toBeNull()
        ->and($variance->diameter_length_ringsize)->toBe('Panjang 16.5 cm');

    $variance->delete();
    $spk->delete();
    $item->delete();
});

test('import stock spk recreates variance when linked variance is missing', function () {
    $item = MsItem::factory()->create([
        'name' => 'ImportOrphan '.Str::upper(Str::random(6)),
    ]);

    $description = 'Orphan Link '.Str::upper(Str::random(8));

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => 999999,
        'description' => $description,
        'order_date' => '2026-07-01',
        'created_date' => '2026-07-01 09:00:00',
        'file_name' => 'orphan_file.jpeg',
        'is_deleted' => 0,
    ]);

    expect(
        MsItemVariance::query()->where('row_id', 999999)->exists(),
    )->toBeFalse();

    $result = app(ImportStockSpkToItemVariance::class)->handle(
        onlyRowIds: [$spk->row_id],
    );

    expect($result['created'])->toBe(1);

    $spk->refresh();
    $variance = MsItemVariance::query()->find($spk->item_variance_id);

    expect($spk->item_variance_id)->not->toBe(999999)
        ->and($variance)->not->toBeNull()
        ->and($variance->name)->toBe($description)
        ->and($variance->image)->toBe('orphan_file.jpeg');

    $variance->delete();
    $spk->delete();
    $item->delete();
});

test('import stock spk skips already linked spk', function () {
    $item = MsItem::factory()->create([
        'name' => 'ImportSkip '.Str::upper(Str::random(6)),
    ]);

    $existing = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'Existing '.Str::upper(Str::random(6)),
    ]);

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => $existing->row_id,
        'description' => 'Already linked '.Str::upper(Str::random(8)),
        'order_date' => '2026-05-01',
        'created_date' => '2026-05-01 09:00:00',
        'is_deleted' => 0,
    ]);

    $before = MsItemVariance::query()->notDeleted()->count();

    $result = app(ImportStockSpkToItemVariance::class)->handle(
        onlyRowIds: [$spk->row_id],
    );

    expect($result['created'])->toBe(0)
        ->and(MsItemVariance::query()->notDeleted()->count())->toBe($before);

    $spk->refresh();
    expect($spk->item_variance_id)->toBe($existing->row_id);

    $spk->delete();
    $existing->delete();
    $item->delete();
});

test('import stock spk dry-run does not write data', function () {
    $item = MsItem::factory()->create([
        'name' => 'ImportDry '.Str::upper(Str::random(6)),
    ]);

    $description = 'Dry Run Import '.Str::upper(Str::random(8));

    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/'.fake()->unique()->numberBetween(80000, 89999),
        'spk_type' => 'Stock',
        'item_id' => $item->row_id,
        'item_name' => $item->name,
        'item_type_id' => $item->row_id,
        'item_variance_id' => null,
        'description' => $description,
        'order_date' => '2026-06-01',
        'created_date' => '2026-06-01 09:00:00',
        'is_deleted' => 0,
    ]);

    $beforeVariance = MsItemVariance::query()->notDeleted()->count();

    $result = app(ImportStockSpkToItemVariance::class)->handle(
        dryRun: true,
        onlyRowIds: [$spk->row_id],
    );

    expect($result['created'])->toBe(1)
        ->and($result['linked'])->toBe(1)
        ->and(MsItemVariance::query()->notDeleted()->count())->toBe($beforeVariance);

    $spk->refresh();
    expect($spk->item_variance_id)->toBeNull();

    expect(
        MsItemVariance::query()->notDeleted()->where('name', $description)->exists(),
    )->toBeFalse();

    $spk->delete();
    $item->delete();
});

test('spk import-stock-variances artisan command runs dry-run', function () {
    $this->artisan('spk:import-stock-variances', ['--dry-run' => true, '--limit' => 1])
        ->assertSuccessful();
});
