<?php

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiImportTemplateExport;
use App\Support\FakturBarangJadiWorkflowStatus;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkProcessMapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * @param  list<list<string|null>>  $rows
 */
function createFakturBarangJadiImportUploadForTest(array $rows): UploadedFile
{
    $fileName = 'faktur-bj-import-'.Str::lower(Str::random(8)).'.xlsx';
    $relativePath = 'framework/testing/'.$fileName;

    Excel::store(
        new FakturBarangJadiImportTemplateExport($rows),
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

function createDoneBarangJadiProductionForImport(string $spkNo): Production
{
    $production = Production::factory()->create([
        'spk_no' => $spkNo,
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-IMP-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    return $production;
}

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }
});

test('faktur barang jadi import template can be downloaded', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('faktur-barang-jadi.import-template'))
        ->assertOk()
        ->assertDownload('template-import-faktur-barang-jadi.xlsx');
});

test('faktur barang jadi import creates receipt and send_to_merch_date from excel', function () {
    $spkNo = 'TEST-FBJ-IMPORT-'.Str::upper(Str::random(6));
    $production = createDoneBarangJadiProductionForImport($spkNo);
    $importPlu = 'IMP-PLU-'.Str::upper(Str::random(4));
    $docNo = 'IMPORT/FBJ/'.Str::upper(Str::random(6));

    $file = createFakturBarangJadiImportUploadForTest([
        [
            $docNo,
            '2026-09-18',
            'Workshop',
            'Store',
            $spkNo,
            $importPlu,
            '',
            '',
            '',
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('faktur-barang-jadi.index'))
        ->post(route('faktur-barang-jadi.import'), [
            'file' => $file,
        ])
        ->assertRedirect(route('faktur-barang-jadi.index'));

    $receipt = SerahTerimaFakturBarangJadi::query()->where('doc_no', $docNo)->first();

    expect($receipt)->not->toBeNull()
        ->and($receipt->tanggal->toDateString())->toBe('2026-09-18')
        ->and($receipt->spk_row_ids)->toBe([(int) $production->row_id]);

    if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
        expect(FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status))
            ->toBe(FakturBarangJadiWorkflowStatus::RECEIVED);
    }

    $production->refresh();

    expect($production->location)->toBe('Store')
        ->and($production->plu)->toBe($importPlu)
        ->and($receipt->items[0]['plu'])->toBe($importPlu);

    if (Schema::connection('third')->hasColumn('spk', 'send_to_merch_date')) {
        expect($production->send_to_merch_date?->toDateString())->toBe('2026-09-18');
    }
});

test('faktur barang jadi import completes in progress production', function () {
    $spkNo = 'TEST-FBJ-INPROG-'.Str::upper(Str::random(6));
    $production = Production::factory()->create([
        'spk_no' => $spkNo,
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'is_deleted' => 0,
    ]);

    $docNo = 'IMPORT/FBJ/INPROG/'.Str::upper(Str::random(6));
    $file = createFakturBarangJadiImportUploadForTest([
        [
            $docNo,
            '2026-09-20',
            'Workshop',
            'Store',
            $spkNo,
            '',
            '',
            '',
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('faktur-barang-jadi.index'))
        ->post(route('faktur-barang-jadi.import'), [
            'file' => $file,
        ])
        ->assertRedirect(route('faktur-barang-jadi.index'));

    $production->refresh();

    expect(SpkDashboardAnalytics::completedProductionKind((int) $production->row_id))
        ->toBe(SpkDashboardAnalytics::KEY_DONE_BARANG_JADI)
        ->and($production->is_inprocess)->toBe(0)
        ->and(SerahTerimaFakturBarangJadi::query()->where('doc_no', $docNo)->exists())
        ->toBeTrue();

    $processTabs = collect(app(SpkProcessMapper::class)->forProduction((int) $production->row_id))
        ->filter(fn (array $tab): bool => ($tab['placement'] ?? 'proses-produksi') === 'proses-produksi');

    foreach ($processTabs as $tab) {
        expect($tab['recordCount'] ?? 0)->toBeGreaterThan(0, "Expected records for {$tab['key']}");

        foreach ($tab['sources'] ?? [] as $source) {
            foreach ($source['records'] ?? [] as $record) {
                expect($record['completed'] ?? false)->toBeTrue("Expected {$tab['key']} record completed");
            }
        }

    }
});

test('faktur barang jadi import rejects invalid spreadsheet headers', function () {
    Storage::disk('local')->makeDirectory('framework/testing');
    $relativePath = 'framework/testing/invalid-faktur-bj-import.xlsx';
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
        'invalid-faktur-bj-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->actingAs(User::factory()->create())
        ->from(route('faktur-barang-jadi.index'))
        ->post(route('faktur-barang-jadi.import'), [
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');
});
