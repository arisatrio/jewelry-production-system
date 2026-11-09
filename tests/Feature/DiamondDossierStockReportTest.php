<?php

use App\Models\DiamondCrtMatrix;
use App\Support\DiamondDossierInventory;
use App\Support\DiamondDossierStockReportWorkbookExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    foreach (['trdiamond', 'msshape', 'diamond_crt_matrix'] as $table) {
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

test('diamond dossier stock report page renders matrix summary', function () {
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.500',
            'crt_max' => '0.590',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    $inventory = app(DiamondDossierInventory::class);
    $beforePcs = $inventory->matrixStockReport(null, 'Safir')['grandTotals']['available']['pcs'];

    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => 'T'.Str::upper(Str::random(10)),
        'diamond_type' => 'Safir',
        'entry_date' => '2026-09-09',
        'supplier' => 'RJ',
        'crt' => '0.520',
        'shape_id' => $shapeId,
        'color' => 'D',
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => '2026-09-09 10:00:00',
        'created_by' => 'tester',
        'modified_date' => '2026-09-09 10:00:00',
        'modified_by' => 'tester',
    ]);

    $this->get(route('inventory.diamond-dossiers.stock-report', [
        'diamond_type' => 'Safir',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-dossier/stock-report')
            ->where('filters.diamond_type', 'Safir')
            ->where('grandTotals.available.pcs', $beforePcs + 1)
        );
});

test('matrix stock report counts only available diamonds in range', function () {
    $inventory = app(DiamondDossierInventory::class);
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.400',
            'crt_max' => '0.490',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    $before = $inventory->matrixStockReport(
        DiamondDossierInventory::STATUS_AVAILABLE,
        'Safir',
    );

    DB::connection('third')->table('trdiamond')->insert([
        [
            'doc_no' => 'T'.Str::upper(Str::random(10)),
            'diamond_type' => 'Safir',
            'entry_date' => '2026-09-09',
            'crt' => '0.450',
            'shape_id' => $shapeId,
            'diamondmounting_id' => 0,
            'is_used' => 0,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
        ],
        [
            'doc_no' => 'T'.Str::upper(Str::random(10)),
            'diamond_type' => 'Safir',
            'entry_date' => '2026-09-09',
            'crt' => '0.460',
            'shape_id' => $shapeId,
            'diamondmounting_id' => 0,
            'is_used' => 1,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
        ],
    ]);

    $report = $inventory->matrixStockReport(
        DiamondDossierInventory::STATUS_AVAILABLE,
        'Safir',
    );

    expect($report['grandTotals']['total']['pcs'])->toBe($before['grandTotals']['total']['pcs'] + 1);
});

test('matrix stock report exposes actual available as ready plus reserved', function () {
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.400',
            'crt_max' => '0.490',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    $inventory = app(DiamondDossierInventory::class);
    $before = $inventory->matrixStockReport(null, 'Safir');

    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => 'T'.Str::upper(Str::random(10)),
        'diamond_type' => 'Safir',
        'entry_date' => '2026-09-09',
        'crt' => '0.450',
        'shape_id' => $shapeId,
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => '2026-09-09 10:00:00',
        'created_by' => 'tester',
        'modified_date' => '2026-09-09 10:00:00',
        'modified_by' => 'tester',
    ]);

    $report = $inventory->matrixStockReport(null, 'Safir');

    expect($report['grandTotals']['actualAvailable']['pcs'])->toBe(
        $report['grandTotals']['available']['pcs']
            + $report['grandTotals']['reserved']['pcs'],
    );

    expect($report['grandTotals']['actualAvailable']['pcs'])->toBe(
        $before['grandTotals']['actualAvailable']['pcs'] + 1,
    );
});

test('stock report diamonds endpoint returns matching rows for matrix cell', function () {
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.400',
            'crt_max' => '0.490',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    $docNo = 'T'.Str::upper(Str::random(10));

    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => $docNo,
        'diamond_type' => 'Safir',
        'entry_date' => '2026-09-09',
        'crt' => '0.450',
        'shape_id' => $shapeId,
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => '2026-09-09 10:00:00',
        'created_by' => 'tester',
        'modified_date' => '2026-09-09 10:00:00',
        'modified_by' => 'tester',
    ]);

    $this->getJson(route('inventory.diamond-dossiers.stock-report-diamonds', [
        'shape_id' => $shapeId,
        'crt_min' => '0.400',
        'crt_max' => '0.490',
        'status' => DiamondDossierInventory::STATUS_AVAILABLE,
        'diamond_type' => 'Safir',
    ]))
        ->assertOk()
        ->assertJsonPath('diamonds.0.code', $docNo);
});

test('stock report diamonds endpoint returns ready and reserved for actual available', function () {
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.400',
            'crt_max' => '0.490',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    $availableDoc = 'T'.Str::upper(Str::random(10));
    $usedDoc = 'T'.Str::upper(Str::random(10));

    DB::connection('third')->table('trdiamond')->insert([
        [
            'doc_no' => $availableDoc,
            'diamond_type' => 'Safir',
            'entry_date' => '2026-09-09',
            'crt' => '0.450',
            'shape_id' => $shapeId,
            'diamondmounting_id' => 0,
            'is_used' => 0,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
        ],
        [
            'doc_no' => $usedDoc,
            'diamond_type' => 'Safir',
            'entry_date' => '2026-09-09',
            'crt' => '0.460',
            'shape_id' => $shapeId,
            'diamondmounting_id' => 0,
            'is_used' => 1,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
        ],
    ]);

    $response = $this->getJson(route('inventory.diamond-dossiers.stock-report-diamonds', [
        'shape_id' => $shapeId,
        'crt_min' => '0.400',
        'crt_max' => '0.490',
        'status' => DiamondDossierInventory::STATUS_ACTUAL_AVAILABLE,
        'diamond_type' => 'Safir',
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'diamonds')
        ->assertJsonPath('diamonds.0.code', $availableDoc);
});

test('stock report export downloads excel workbook', function () {
    $shapeId = 1;

    DiamondCrtMatrix::query()->updateOrCreate(
        [
            'shape_id' => $shapeId,
            'crt_min' => '0.400',
            'crt_max' => '0.490',
        ],
        [
            'sort_order' => 1,
            'updated_by' => 'test',
        ],
    );

    Excel::fake();
    Excel::matchByRegex();

    $this->get(route('inventory.diamond-dossiers.stock-report-export', [
        'diamond_type' => 'Safir',
    ]))
        ->assertOk();

    Excel::assertDownloaded(
        '/^laporan-stok-batu-dossier-.*\\.xlsx$/',
        function (DiamondDossierStockReportWorkbookExport $export): bool {
            return count($export->sheets()) >= 1;
        },
    );
});
