<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['trdiamond', 'msshape', 'diamondmounting'] as $table) {
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

/**
 * @param  array<string, mixed>  $overrides
 */
function createDossierDiamondForTest(array $overrides = []): int
{
    return (int) DB::connection('third')
        ->table('trdiamond')
        ->insertGetId([
            'doc_no' => 'T'.Str::upper(Str::random(12)),
            'diamond_type' => 'Dossier',
            'entry_date' => '2026-09-09',
            'out_date' => null,
            'supplier' => 'RJ',
            'crt' => '0.500',
            'shape_id' => 1,
            'color' => 'D',
            'certificate' => null,
            'rapp' => null,
            'disc' => null,
            'hpp' => null,
            'diamondmounting_id' => 0,
            'is_used' => 0,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
            'deleted_date' => null,
            'deleted_by' => null,
            ...$overrides,
        ]);
}

test('diamond dossier index lists active diamonds with summary', function () {
    $certificate = 'CERT-'.Str::upper(Str::random(8));

    createDossierDiamondForTest(['certificate' => $certificate, 'crt' => '0.500']);
    createDossierDiamondForTest([
        'certificate' => $certificate,
        'crt' => '1.200',
        'is_used' => 1,
        'out_date' => '2026-09-22',
    ]);
    createDossierDiamondForTest(['certificate' => $certificate, 'is_deleted' => 1]);

    $this->get(route('inventory.diamond-dossiers.index', [
        'search' => $certificate,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-dossier/index')
            ->has('diamonds.data', 2)
            ->where('diamonds.data.0.status', 'used')
            ->where('diamonds.data.0.crt', '1.200')
            ->where('diamonds.data.0.shape', 'Round')
            ->where('diamonds.data.1.status', 'available')
            ->where('summary.count', 2)
            ->where('summary.crt', '1.700')
        );
});

test('diamond dossier index filters by status', function (string $status, string $expectedCrt) {
    $certificate = 'CERT-'.Str::upper(Str::random(8));

    createDossierDiamondForTest(['certificate' => $certificate, 'crt' => '0.500']);
    createDossierDiamondForTest([
        'certificate' => $certificate,
        'crt' => '1.200',
        'is_used' => 1,
    ]);

    $this->get(route('inventory.diamond-dossiers.index', [
        'search' => $certificate,
        'status' => $status,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('diamonds.data', 1)
            ->where('diamonds.data.0.status', $status)
            ->where('summary.crt', $expectedCrt)
            ->where('filters.status', $status)
        );
})->with([
    'available' => ['available', '0.500'],
    'used' => ['used', '1.200'],
]);

test('diamond dossier index rejects unknown status', function () {
    $this->get(route('inventory.diamond-dossiers.index', ['status' => 'unknown']))
        ->assertSessionHasErrors('status');
});
