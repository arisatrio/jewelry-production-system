<?php

use App\Models\DiamondCrtMatrix;
use App\Models\MsShape;
use Database\Seeders\DiamondCrtMatrixSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['msshape', 'diamond_crt_matrix'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    DB::connection('third')->beginTransaction();
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

test('seeder stores the crt matrix for every dossier shape', function () {
    $this->seed(DiamondCrtMatrixSeeder::class);

    $roundShapeId = (int) MsShape::query()->notDeleted()->where('code', 'R')->value('row_id');

    $roundRanges = DiamondCrtMatrix::query()
        ->where('shape_id', $roundShapeId)
        ->orderBy('sort_order')
        ->get()
        ->map(fn (DiamondCrtMatrix $range): array => [$range->crt_min, $range->crt_max])
        ->all();

    $shapeIds = MsShape::query()
        ->notDeleted()
        ->whereIn('code', DiamondCrtMatrixSeeder::SHAPE_CODES)
        ->pluck('row_id');

    expect($roundRanges)->toBe(DiamondCrtMatrixSeeder::CRT_RANGES)
        ->and(DiamondCrtMatrix::query()->whereIn('shape_id', $shapeIds)->count())
        ->toBe(count(DiamondCrtMatrixSeeder::SHAPE_CODES) * count(DiamondCrtMatrixSeeder::CRT_RANGES));
});

test('seeder is idempotent', function () {
    $this->seed(DiamondCrtMatrixSeeder::class);
    $firstCount = DiamondCrtMatrix::query()->count();

    $this->seed(DiamondCrtMatrixSeeder::class);

    expect(DiamondCrtMatrix::query()->count())->toBe($firstCount);
});

test('matching scope resolves the crt range for a shape', function () {
    $this->seed(DiamondCrtMatrixSeeder::class);

    $ovalShapeId = (int) MsShape::query()->notDeleted()->where('code', 'OV')->value('row_id');

    $range = DiamondCrtMatrix::query()->matching($ovalShapeId, 0.45)->first();
    $largeRange = DiamondCrtMatrix::query()->matching($ovalShapeId, 2.5)->first();

    expect($range?->crt_min)->toBe('0.400')
        ->and($range?->crt_max)->toBe('0.490')
        ->and($largeRange?->crt_min)->toBe('1.000')
        ->and($largeRange?->crt_max)->toBe('10.000');
});
