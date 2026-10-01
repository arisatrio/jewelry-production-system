<?php

use App\Models\MsShape;
use App\Models\MsStone;
use App\Models\SpkStone;
use App\Support\SpkStoneStockChecker;
use App\Support\StoneLedger;
use Database\Seeders\DiamondCrtMatrixSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['msshape', 'msstone', 'trstone', 'trdiamond', 'diamond_crt_matrix'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    DB::connection('third')->beginTransaction();
    $this->seed(DiamondCrtMatrixSeeder::class);
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

function stockTestShapeId(string $code): int
{
    return (int) MsShape::query()->notDeleted()->where('code', $code)->value('row_id');
}

function stockTestStone(int $lineId, int $shapeId, int $pcs, string $totalCarat, ?string $size = null): SpkStone
{
    $stone = new SpkStone([
        'shape_id' => $shapeId,
        'pcs' => $pcs,
        'carat' => $totalCarat,
        'size' => $size,
    ]);
    $stone->line_id = $lineId;

    return $stone;
}

function createAvailableDossierDiamond(int $shapeId, string $crt): void
{
    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => 'T'.Str::upper(Str::random(12)),
        'diamond_type' => 'Dossier',
        'entry_date' => '2026-09-30',
        'supplier' => 'RJ',
        'crt' => $crt,
        'shape_id' => $shapeId,
        'color' => 'D',
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
    ]);
}

test('stone with carat per pcs at least 0.18 uses dossier stock within the matrix range', function () {
    $checker = app(SpkStoneStockChecker::class);
    $shapeId = stockTestShapeId('HS');

    $baseline = $checker->forStones(collect([stockTestStone(1, $shapeId, 1, '0.450')]))[1]['availablePcs'];

    createAvailableDossierDiamond($shapeId, '0.400');
    createAvailableDossierDiamond($shapeId, '0.490');
    createAvailableDossierDiamond($shapeId, '0.500');

    $results = $checker->forStones(collect([
        stockTestStone(1, $shapeId, $baseline + 2, (string) (0.45 * ($baseline + 2))),
        stockTestStone(2, $shapeId, $baseline + 3, (string) (0.45 * ($baseline + 3))),
    ]));

    expect($results[1])->toMatchArray([
        'source' => SpkStoneStockChecker::SOURCE_DOSSIER,
        'status' => SpkStoneStockChecker::STATUS_AVAILABLE,
        'availablePcs' => $baseline + 2,
        'note' => MsShape::query()->find($shapeId)?->name.', range 0.400 – 0.490 ct',
    ])
        ->and($results[2]['status'])->toBe(SpkStoneStockChecker::STATUS_UNAVAILABLE);
});

test('dossier stock only counts diamonds with the same shape', function () {
    $checker = app(SpkStoneStockChecker::class);
    $heartShapeId = stockTestShapeId('HS');
    $ovalShapeId = stockTestShapeId('OV');
    $heartStone = fn (): SpkStone => stockTestStone(1, $heartShapeId, 1, '0.650');

    $baseline = $checker->forStones(collect([$heartStone()]))[1]['availablePcs'];

    createAvailableDossierDiamond($ovalShapeId, '0.650');
    createAvailableDossierDiamond($ovalShapeId, '0.660');

    expect($checker->forStones(collect([$heartStone()]))[1]['availablePcs'])->toBe($baseline);

    createAvailableDossierDiamond($heartShapeId, '0.650');

    expect($checker->forStones(collect([$heartStone()]))[1]['availablePcs'])->toBe($baseline + 1);
});

test('dossier stone outside the matrix is unavailable', function () {
    $results = app(SpkStoneStockChecker::class)->forStones(collect([
        stockTestStone(1, stockTestShapeId('R'), 1, '0.185'),
    ]));

    expect($results[1])->toMatchArray([
        'source' => SpkStoneStockChecker::SOURCE_DOSSIER,
        'status' => SpkStoneStockChecker::STATUS_UNAVAILABLE,
        'availablePcs' => 0,
        'note' => 'Bentuk dan CRT ini tidak ada di Matrix CRT Dossier.',
    ]);
});

test('stone below 0.18 carat per pcs uses micro stone stock by shape and size', function () {
    $ledger = app(StoneLedger::class);
    $activePeriod = $ledger->activePeriod();

    if ($activePeriod === null) {
        $this->markTestSkipped('No active stone period.');
    }

    $checker = app(SpkStoneStockChecker::class);
    $shapeId = stockTestShapeId('MQ');
    $singleSizeStone = fn (int $pcs): SpkStone => stockTestStone(1, $shapeId, $pcs, (string) (0.01 * $pcs), '9,87');
    $rangeSizeStone = fn (int $pcs): SpkStone => stockTestStone(2, $shapeId, $pcs, (string) (0.01 * $pcs), '9.2');

    $baseline = $checker->forStones(collect([$singleSizeStone(1), $rangeSizeStone(1)]));

    $singleSize = MsStone::factory()->create(['shape_id' => $shapeId, 'stone_size' => '9.87 ', 'mounting_rate' => 1]);
    $rangeSize = MsStone::factory()->create(['shape_id' => $shapeId, 'stone_size' => '9.10 - 9.30', 'mounting_rate' => 1]);
    $ledger->store($activePeriod['id'], 'addition', (int) $singleSize->row_id, '50', null, 'test', 'tester');
    $ledger->store($activePeriod['id'], 'addition', (int) $rangeSize->row_id, '20', null, 'test', 'tester');

    $singleSizeAvailable = $baseline[1]['availablePcs'] + 50;
    $rangeSizeAvailable = $baseline[2]['availablePcs'] + 20;

    $results = $checker->forStones(collect([
        $singleSizeStone($singleSizeAvailable),
        $rangeSizeStone($rangeSizeAvailable + 1),
    ]));

    expect($results[1])->toMatchArray([
        'source' => SpkStoneStockChecker::SOURCE_MICRO,
        'status' => SpkStoneStockChecker::STATUS_AVAILABLE,
        'availablePcs' => $singleSizeAvailable,
    ])
        ->and($results[2])->toMatchArray([
            'source' => SpkStoneStockChecker::SOURCE_MICRO,
            'status' => SpkStoneStockChecker::STATUS_UNAVAILABLE,
            'availablePcs' => $rangeSizeAvailable,
        ]);
});

test('matching stones lists available dossier diamonds of the same shape within the range', function () {
    $checker = app(SpkStoneStockChecker::class);
    $heartShapeId = stockTestShapeId('HS');
    $heartStone = stockTestStone(1, $heartShapeId, 1, '0.450');

    createAvailableDossierDiamond($heartShapeId, '0.444');
    createAvailableDossierDiamond(stockTestShapeId('OV'), '0.444');
    createAvailableDossierDiamond($heartShapeId, '0.520');

    $result = $checker->matchingStones($heartStone);
    $crts = array_column($result['rows'], 'crt');
    $shapes = array_unique(array_column($result['rows'], 'shape'));

    expect($result['stock']['availablePcs'])->toBe(count($result['rows']))
        ->and($result['stock']['availablePcs'])->toBe($checker->forStones(collect([$heartStone]))[1]['availablePcs'])
        ->and($crts)->toContain('0.444')
        ->and($crts)->not->toContain('0.520')
        ->and($shapes)->toBe([MsShape::query()->find($heartShapeId)?->name]);
});

test('matching stones lists micro stones with their balance', function () {
    $ledger = app(StoneLedger::class);
    $activePeriod = $ledger->activePeriod();

    if ($activePeriod === null) {
        $this->markTestSkipped('No active stone period.');
    }

    $shapeId = stockTestShapeId('MQ');
    $microStone = MsStone::factory()->create(['shape_id' => $shapeId, 'stone_size' => '9.66', 'mounting_rate' => 1]);
    $ledger->store($activePeriod['id'], 'addition', (int) $microStone->row_id, '15', '0.3000', 'test', 'tester');

    $result = app(SpkStoneStockChecker::class)->matchingStones(
        stockTestStone(1, $shapeId, 10, '0.100', '9,66'),
    );
    $row = collect($result['rows'])->firstWhere('id', (int) $microStone->row_id);

    expect($row)->toMatchArray([
        'size' => '9.66',
        'balancePcs' => '15',
        'balanceCrt' => '0.3000',
    ])
        ->and($result['stock']['source'])->toBe(SpkStoneStockChecker::SOURCE_MICRO)
        ->and($result['stock']['availablePcs'])->toBeGreaterThanOrEqual(15);
});

test('spk stone check stock endpoint returns stock from form attributes', function () {
    $shapeId = stockTestShapeId('HS');

    $expected = app(SpkStoneStockChecker::class)->matchingFromAttributes($shapeId, 1, 0.45);

    $this->postJson(route('spk.stones.check-stock'), [
        'shape_id' => $shapeId,
        'pcs' => 1,
        'carat_per_pcs' => 0.45,
    ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.stock.source', $expected['stock']['source'])
        ->assertJsonPath('data.stock.availablePcs', $expected['stock']['availablePcs'])
        ->assertJsonStructure(['data' => ['stock' => ['source', 'status', 'requiredPcs', 'availablePcs', 'note'], 'rows']]);
});

test('spk stone check stock endpoint validates required fields', function () {
    $this->postJson(route('spk.stones.check-stock'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shape_id', 'pcs']);
});

test('spk stone stock endpoint returns stock and matching rows', function () {
    $stone = SpkStone::query()->notDeleted()->whereNotNull('shape_id')->where('pcs', '>', 0)->first();

    if ($stone === null) {
        $this->markTestSkipped('No spk stone available.');
    }

    $expected = app(SpkStoneStockChecker::class)->forStones(collect([$stone->load('shape')]))[(int) $stone->line_id];

    $this->getJson(route('spk.stones.stock', $stone))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.stock.source', $expected['source'])
        ->assertJsonPath('data.stock.availablePcs', $expected['availablePcs'])
        ->assertJsonStructure(['data' => ['stock' => ['source', 'status', 'requiredPcs', 'availablePcs', 'note'], 'rows']]);
});

test('micro stone without size is unavailable', function () {
    $results = app(SpkStoneStockChecker::class)->forStones(collect([
        stockTestStone(1, stockTestShapeId('R'), 4, '0.080'),
    ]));

    expect($results[1])->toMatchArray([
        'source' => SpkStoneStockChecker::SOURCE_MICRO,
        'status' => SpkStoneStockChecker::STATUS_UNAVAILABLE,
        'note' => 'Bentuk atau ukuran batu belum diisi.',
    ]);
});
