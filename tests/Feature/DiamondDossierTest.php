<?php

use App\Support\DiamondDossierImportTemplateExport;
use App\Support\DiamondDossierInventory;
use App\Support\SpkStoneAllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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

function dossierShapeLabelForTest(int $shapeId = 1): string
{
    $shape = DB::connection('third')
        ->table('msshape')
        ->where('row_id', $shapeId)
        ->first(['name', 'code']);

    return DiamondDossierInventory::formatShapeLabel(
        $shape->name ?? null,
        $shape->code ?? null,
    );
}

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
            ->where('filters.per_page', 50)
            ->has('diamonds.data', 2)
            ->where('diamonds.data.0.status', 'used')
            ->where('diamonds.data.0.crt', '1.200')
            ->where('diamonds.data.0.shape', dossierShapeLabelForTest(1))
            ->where('diamonds.data.0.modifiedBy', 'tester')
            ->where('diamonds.data.1.status', 'available')
            ->where('summary.count', 2)
            ->where('summary.crt', '1.700')
            ->where('statusSummaries.available.pcs', 1)
            ->where('statusSummaries.available.crt', '0.500')
            ->where('statusSummaries.used.pcs', 1)
            ->where('statusSummaries.used.crt', '1.200')
            ->whereNotNull('lastUpdatedAt')
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

test('diamond dossier index rejects invalid diamond type filter', function () {
    $this->get(route('inventory.diamond-dossiers.index', ['diamond_type' => 'InvalidType']))
        ->assertSessionHasErrors('diamond_type');
});

test('diamond dossier index rejects invalid sort', function () {
    $this->get(route('inventory.diamond-dossiers.index', ['sort' => 'invalid']))
        ->assertSessionHasErrors('sort');
});

test('diamond dossier index sorts by entry date ascending', function () {
    $certificate = 'CERT-'.Str::upper(Str::random(8));
    $earlierId = createDossierDiamondForTest([
        'certificate' => $certificate,
        'entry_date' => '2026-01-01',
    ]);
    $laterId = createDossierDiamondForTest([
        'certificate' => $certificate,
        'entry_date' => '2026-06-01',
    ]);

    $this->get(route('inventory.diamond-dossiers.index', [
        'search' => $certificate,
        'sort' => 'entry_date',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'entry_date')
            ->where('filters.direction', 'asc')
            ->where('diamonds.data.0.id', $earlierId)
            ->where('diamonds.data.1.id', $laterId)
        );
});

test('diamond dossier index filters by diamond type shape and supplier', function () {
    $certificate = 'CERT-'.Str::upper(Str::random(8));

    createDossierDiamondForTest([
        'certificate' => $certificate,
        'diamond_type' => 'Dossier',
        'supplier' => 'RJ',
        'shape_id' => 1,
    ]);
    createDossierDiamondForTest([
        'certificate' => $certificate,
        'diamond_type' => 'Safir',
        'supplier' => 'OTHER',
        'shape_id' => 1,
    ]);

    $this->get(route('inventory.diamond-dossiers.index', [
        'search' => $certificate,
        'diamond_type' => 'Safir',
        'shape_id' => 1,
        'supplier' => 'OTHER',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('diamonds.data', 1)
            ->where('diamonds.data.0.diamondType', 'Safir')
            ->where('filters.diamond_type', 'Safir')
            ->where('filters.shape_id', 1)
            ->where('filters.supplier', 'OTHER')
            ->where('statusSummaries.available.pcs', 1)
            ->where('statusSummaries.used.pcs', 0)
            ->where('statusSummaries.reserved.pcs', 0)
        );
});

test('diamond dossier show displays detail', function () {
    $docNo = 'DOS-'.Str::upper(Str::random(8));
    $diamondId = createDossierDiamondForTest([
        'doc_no' => $docNo,
        'certificate' => 'CERT-SHOW',
    ]);

    $this->get(route('inventory.diamond-dossiers.show', $diamondId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-dossier/show')
            ->where('diamond.id', $diamondId)
            ->where('diamond.code', $docNo)
            ->where('diamond.certificate', 'CERT-SHOW')
            ->where('diamond.status', 'available')
            ->where('canEdit', true)
            ->where('canRevokeAllocation', false)
            ->has('shapeOptions')
            ->has('form')
        );
});

test('diamond dossier show marks used diamonds as not editable', function () {
    $diamondId = createDossierDiamondForTest(['is_used' => 1]);

    $this->get(route('inventory.diamond-dossiers.show', $diamondId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});

test('diamond dossier show returns not found for missing diamond', function () {
    $this->get(route('inventory.diamond-dossiers.show', 999_999_999))
        ->assertNotFound();
});

test('diamond dossier show returns not found for deleted diamond', function () {
    $diamondId = createDossierDiamondForTest(['is_deleted' => 1]);

    $this->get(route('inventory.diamond-dossiers.show', $diamondId))
        ->assertNotFound();
});

test('diamond dossier index includes form metadata for modals', function () {
    $this->get(route('inventory.diamond-dossiers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-dossier/index')
            ->has('shapeOptions')
            ->has('diamondTypeOptions')
            ->has('supplierOptions')
            ->has('filterOptions.sort')
            ->where('filters.sort', 'entry_date')
            ->where('filters.direction', 'desc')
            ->where('defaults.diamondType', 'Dossier')
        );
});

test('diamond dossier store rejects duplicate dossier certificate', function () {
    $certificate = 'CERT-DUP-'.Str::upper(Str::random(6));

    createDossierDiamondForTest([
        'diamond_type' => 'Dossier',
        'certificate' => $certificate,
    ]);

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.store'), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'color' => 'D',
            'crt' => '0.500',
            'certificate' => $certificate,
            'entry_date' => '2026-10-29',
        ])
        ->assertSessionHasErrors([
            'certificate' => 'Nomor sertifikat sudah digunakan untuk batu tipe Dossier.',
        ]);
});

test('diamond dossier store allows duplicate certificate for non dossier type', function () {
    $certificate = 'CERT-SAFIR-'.Str::upper(Str::random(6));

    createDossierDiamondForTest([
        'diamond_type' => 'Dossier',
        'certificate' => $certificate,
    ]);

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.store'), [
            'diamond_type' => 'Safir',
            'shape_id' => 1,
            'color' => 'D',
            'crt' => '0.500',
            'certificate' => $certificate,
            'entry_date' => '2026-10-29',
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.index'));
});

test('diamond dossier store rejects invalid diamond type', function () {
    $this->post(route('inventory.diamond-dossiers.store'), [
        'diamond_type' => 'InvalidType',
        'shape_id' => 1,
        'color' => 'D',
        'crt' => '0.500',
        'entry_date' => '2026-10-29',
    ])->assertSessionHasErrors('diamond_type');
});

test('diamond dossier store validation messages are in indonesian', function () {
    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.store'), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'crt' => '0.500',
            'entry_date' => '2026-10-29',
        ])
        ->assertSessionHasErrors([
            'color' => 'Warna wajib diisi.',
        ]);
});

test('diamond dossier store accepts crt with comma decimal separator', function () {
    $certificate = 'CERT-COMMA-'.Str::upper(Str::random(6));

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.store'), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'color' => 'D',
            'crt' => '0,750',
            'certificate' => $certificate,
            'entry_date' => '2026-10-29',
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.index'));

    $row = DB::connection('third')
        ->table('trdiamond')
        ->where('certificate', $certificate)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->crt)->toBe('0.750');
});

test('diamond dossier store creates available stock', function () {
    $certificate = 'CERT-NEW-'.Str::upper(Str::random(6));

    $response = $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.store'), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'color' => 'E',
            'crt' => '0.750',
            'certificate' => $certificate,
            'supplier' => 'RJ',
            'entry_date' => '2026-10-29',
        ]);

    $diamondId = (int) DB::connection('third')
        ->table('trdiamond')
        ->where('certificate', $certificate)
        ->value('row_id');

    expect($diamondId)->toBeGreaterThan(0);

    $response->assertRedirect(route('inventory.diamond-dossiers.index'));

    $row = DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->first();

    expect($row->diamond_type)->toBe('Dossier')
        ->and($row->is_used)->toBe(0)
        ->and($row->crt)->toBe('0.750')
        ->and((string) $row->doc_no)->toMatch('/^D\d{7}$/')
        ->and($row->location)->toBe(DiamondDossierInventory::DEFAULT_LOCATION);
});

test('diamond dossier update changes stock fields', function () {
    $diamondId = createDossierDiamondForTest([
        'color' => 'D',
        'crt' => '0.500',
    ]);

    $this->from(route('inventory.diamond-dossiers.show', $diamondId))
        ->put(route('inventory.diamond-dossiers.update', $diamondId), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'color' => 'F',
            'crt' => '0.510',
            'certificate' => 'CERT-EDIT',
            'supplier' => 'Supplier A',
            'entry_date' => '2026-10-01',
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.show', $diamondId));

    $row = DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->first();

    expect($row->color)->toBe('F')
        ->and($row->crt)->toBe('0.510')
        ->and($row->certificate)->toBe('CERT-EDIT');
});

test('diamond dossier update rejects used stock', function () {
    $diamondId = createDossierDiamondForTest(['is_used' => 1]);

    $this->put(route('inventory.diamond-dossiers.update', $diamondId), [
        'diamond_type' => 'Dossier',
        'shape_id' => 1,
        'color' => 'F',
        'crt' => '0.510',
        'entry_date' => '2026-10-01',
    ])->assertSessionHasErrors('entry_date');
});

test('diamond dossier update allows reserved stock', function () {
    if (! Schema::connection('third')->hasTable('spkstone_allocation')) {
        $this->markTestSkipped('Table spkstone_allocation is not available.');
    }

    $lineId = DB::connection('third')->table('spkstone')->where('is_deleted', 0)->value('line_id');

    if ($lineId === null) {
        $this->markTestSkipped('No SPK stone line available for reservation test.');
    }

    $diamondId = createDossierDiamondForTest([
        'color' => 'D',
        'crt' => '0.500',
    ]);

    DB::connection('third')->table('spkstone_allocation')->insert([
        'line_id' => $lineId,
        'source' => SpkStoneAllocationService::SOURCE_DOSSIER,
        'diamond_id' => $diamondId,
        'msstone_id' => null,
        'period_id' => null,
        'reserved_pcs' => 1,
        'reserved_crt' => '0.500',
        'status' => SpkStoneAllocationService::STATUS_RESERVED,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
        'deleted_date' => null,
        'deleted_by' => null,
    ]);

    $inventory = app(DiamondDossierInventory::class);

    expect($inventory->editableBlockReason($diamondId))->toBeNull()
        ->and($inventory->deletableBlockReason($diamondId))->toBe(
            'Batu yang sedang reserved SPK tidak dapat dihapus.',
        );

    $this->get(route('inventory.diamond-dossiers.show', $diamondId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('diamond.status', DiamondDossierInventory::STATUS_RESERVED)
            ->where('canEdit', true)
            ->has('form')
        );

    $this->from(route('inventory.diamond-dossiers.show', $diamondId))
        ->put(route('inventory.diamond-dossiers.update', $diamondId), [
            'diamond_type' => 'Dossier',
            'shape_id' => 1,
            'color' => 'G',
            'crt' => '0.505',
            'entry_date' => '2026-10-01',
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.show', $diamondId));

    $row = DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->first();

    expect($row->color)->toBe('G')
        ->and($row->crt)->toBe('0.505');
});

test('diamond dossier bulk destroy rejects reserved stock', function () {
    if (! Schema::connection('third')->hasTable('spkstone_allocation')) {
        $this->markTestSkipped('Table spkstone_allocation is not available.');
    }

    $lineId = DB::connection('third')->table('spkstone')->where('is_deleted', 0)->value('line_id');

    if ($lineId === null) {
        $this->markTestSkipped('No SPK stone line available for reservation test.');
    }

    $diamondId = createDossierDiamondForTest();

    DB::connection('third')->table('spkstone_allocation')->insert([
        'line_id' => $lineId,
        'source' => SpkStoneAllocationService::SOURCE_DOSSIER,
        'diamond_id' => $diamondId,
        'msstone_id' => null,
        'period_id' => null,
        'reserved_pcs' => 1,
        'reserved_crt' => '0.500',
        'status' => SpkStoneAllocationService::STATUS_RESERVED,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
        'deleted_date' => null,
        'deleted_by' => null,
    ]);

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.bulk-destroy'), [
            'ids' => [$diamondId],
        ])
        ->assertSessionHasErrors('ids');

    expect(DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->value('is_deleted'))->toBe(0);
});

test('diamond dossier bulk destroy soft deletes available stock', function () {
    $firstId = createDossierDiamondForTest();
    $secondId = createDossierDiamondForTest();

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.bulk-destroy'), [
            'ids' => [$firstId, $secondId],
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.index'));

    expect(DB::connection('third')->table('trdiamond')->where('row_id', $firstId)->value('is_deleted'))->toBe(1)
        ->and(DB::connection('third')->table('trdiamond')->where('row_id', $secondId)->value('is_deleted'))->toBe(1);
});

test('diamond dossier bulk destroy rejects used stock', function () {
    $diamondId = createDossierDiamondForTest(['is_used' => 1]);

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.bulk-destroy'), [
            'ids' => [$diamondId],
        ])
        ->assertSessionHasErrors('ids');

    expect(DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->value('is_deleted'))->toBe(0);
});

test('diamond dossier bulk destroy requires ids', function () {
    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.bulk-destroy'), [])
        ->assertSessionHasErrors('ids');
});

/**
 * @param  list<list<string|null>>  $rows
 */
function createDossierImportUploadForTest(array $rows): UploadedFile
{
    $fileName = 'dossier-import-'.Str::lower(Str::random(8)).'.xlsx';
    $relativePath = 'framework/testing/'.$fileName;

    Excel::store(
        new DiamondDossierImportTemplateExport($rows),
        $relativePath,
        'local',
    );

    $fullPath = Storage::disk('local')->path($relativePath);

    return new UploadedFile(
        $fullPath,
        $fileName,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

function dossierShapeImportValueForTest(int $shapeId = 1): string
{
    $shape = DB::connection('third')
        ->table('msshape')
        ->where('row_id', $shapeId)
        ->first(['name', 'code']);

    $code = trim((string) ($shape->code ?? ''));

    if ($code !== '') {
        return $code;
    }

    return trim((string) ($shape->name ?? 'RD'));
}

test('diamond dossier import template can be downloaded', function () {
    $this->get(route('inventory.diamond-dossiers.import-template'))
        ->assertOk()
        ->assertDownload('template-import-batu-dossier.xlsx');
});

test('diamond dossier import creates stock from excel rows', function () {
    $certificate = 'GIA-'.Str::upper(Str::random(6));
    $shapeValue = dossierShapeImportValueForTest(1);

    $beforeCount = DB::connection('third')
        ->table('trdiamond')
        ->where('is_deleted', 0)
        ->count();

    $file = createDossierImportUploadForTest([
        [
            '2026-10-15',
            $shapeValue,
            '0.750',
            'E',
            $certificate,
            'Import Supplier',
        ],
    ]);

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.import'), [
            'file' => $file,
        ])
        ->assertRedirect(route('inventory.diamond-dossiers.index'));

    expect(DB::connection('third')->table('trdiamond')->where('is_deleted', 0)->count())
        ->toBe($beforeCount + 1)
        ->and(DB::connection('third')->table('trdiamond')->where('certificate', $certificate)->exists())
        ->toBeTrue();
});

test('diamond dossier import rejects invalid spreadsheet headers', function () {
    Storage::disk('local')->makeDirectory('framework/testing');
    $relativePath = 'framework/testing/invalid-dossier-import.xlsx';
    $path = Storage::disk('local')->path($relativePath);
    IOFactory::createWriter(
        tap(new Spreadsheet, function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(['Salah', 'Header'], null, 'A1');
        }),
        'Xlsx',
    )->save($path);

    $file = new UploadedFile(
        $path,
        'invalid-dossier-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->from(route('inventory.diamond-dossiers.index'))
        ->post(route('inventory.diamond-dossiers.import'), [
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');
});
