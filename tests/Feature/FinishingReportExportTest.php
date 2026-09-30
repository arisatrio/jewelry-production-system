<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\FinishingReportExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * @return list<object{row_id: int, name: string}>
 */
function finishingExportCraftsmen(): array
{
    return DB::connection('third')
        ->table('mscraftsman')
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('name')
        ->limit(2)
        ->get(['row_id', 'name'])
        ->all();
}

test('finishing export requires a date range', function () {
    $this->get(route('finishing.export'))
        ->assertSessionHasErrors(['date_from', 'date_to']);

    $this->get(route('finishing.export', [
        'date_from' => '1999-01-10',
        'date_to' => '1999-01-01',
    ]))->assertSessionHasErrors(['date_to']);
});

test('finishing export downloads approved rows in the date range ordered by craftsman name', function () {
    $craftsmen = finishingExportCraftsmen();

    if (count($craftsmen) < 2) {
        $this->markTestSkipped('Butuh minimal dua pengrajin di mscraftsman.');
    }

    [$firstCraftsman, $secondCraftsman] = $craftsmen;

    Excel::fake();

    $production = Production::factory()->create(['spk_no' => '1999/PRD/FINEXP']);

    $laterCraftsmanDocument = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FINEXP0001',
        'spk_id' => $production->row_id,
        'craftsman_id' => $secondCraftsman->row_id,
        'send_craftsman_date' => '1999-01-02 08:00:00',
        'received_craftsman_date' => '1999-01-03 10:30:00',
        'start_weight' => '10.00',
        'submit_materialgold' => '0.00',
        'finish_weight' => '9.00',
        'result_materialgold' => '0.50',
        'shrink' => '0.50',
        'item_category' => 'Cincin',
        'koreksi_qc' => 1,
        'keterangan_qc' => 'Batu kurang rapi',
        'notes' => 'Catatan export',
    ]);
    $earlierCraftsmanDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FINEXP0002',
        'status' => FinishingHandmade::STATUS_TO_PPIC,
        'craftsman_id' => $firstCraftsman->row_id,
        'send_craftsman_date' => '1999-01-05 08:00:00',
        'received_craftsman_date' => '1999-01-05 08:45:00',
        'koreksi_qc' => 0,
    ]);
    $outOfRangeDocument = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FINEXP0003',
        'craftsman_id' => $firstCraftsman->row_id,
        'send_craftsman_date' => '1999-02-01 08:00:00',
    ]);
    $deletedDocument = FinishingHandmade::factory()->done()->deleted()->create([
        'doc_no' => 'FINEXP0004',
        'craftsman_id' => $firstCraftsman->row_id,
        'send_craftsman_date' => '1999-01-04 08:00:00',
    ]);
    $openDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FINEXP0006',
        'status' => FinishingHandmade::STATUS_OPEN,
        'craftsman_id' => $firstCraftsman->row_id,
        'send_craftsman_date' => '1999-01-06 08:00:00',
    ]);
    $unsetStatusDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FINEXP0007',
        'status' => null,
        'craftsman_id' => $firstCraftsman->row_id,
        'send_craftsman_date' => '1999-01-07 08:00:00',
    ]);

    $this->get(route('finishing.export', [
        'date_from' => '1999-01-01',
        'date_to' => '1999-01-31',
    ]))->assertOk();

    Excel::assertDownloaded(
        'laporan-finishing-1999-01-01_1999-01-31.xlsx',
        function (FinishingReportExport $export) use ($firstCraftsman, $secondCraftsman): bool {
            $rows = $export->array();

            expect(array_column($rows, 'docNo'))->toBe(['FINEXP0002', 'FINEXP0001'])
                ->and($rows[0]['craftsmanName'])->toBe((string) $firstCraftsman->name)
                ->and($rows[0]['qcStatus'])->toBe('OK')
                ->and($rows[0]['workDuration'])->toBe('45 menit')
                ->and($rows[1]['craftsmanName'])->toBe((string) $secondCraftsman->name)
                ->and($rows[1]['spkNo'])->toBe('1999/PRD/FINEXP')
                ->and($rows[1]['itemCategory'])->toBe('Cincin')
                ->and($rows[1]['startWeight'])->toBe(10.0)
                ->and($rows[1]['finishWeight'])->toBe(9.0)
                ->and($rows[1]['resultMaterial'])->toBe(0.5)
                ->and($rows[1]['shrink'])->toBe(0.5)
                ->and($rows[1]['shrinkRatio'])->toBe(0.05)
                ->and($rows[1]['qcStatus'])->toBe('NOT OK')
                ->and($rows[1]['qcNotes'])->toBe('Batu kurang rapi')
                ->and($rows[1]['workDuration'])->toBe('1 hari 2 jam 30 menit')
                ->and($rows[1]['notes'])->toBe('Catatan export');

            expect($export->map($rows[1]))->toHaveCount(count($export->headings()));

            return true;
        },
    );

    $this->get(route('finishing.export', [
        'craftsman' => $secondCraftsman->row_id,
        'date_from' => '1999-01-01',
        'date_to' => '1999-01-31',
    ]))->assertOk();

    Excel::assertDownloaded(
        'laporan-finishing-'.str((string) $secondCraftsman->name)->slug().'-1999-01-01_1999-01-31.xlsx',
        fn (FinishingReportExport $export): bool => array_column($export->array(), 'docNo') === ['FINEXP0001'],
    );

    collect([$laterCraftsmanDocument, $earlierCraftsmanDocument, $outOfRangeDocument, $deletedDocument, $openDocument, $unsetStatusDocument])
        ->each->delete();
    $production->delete();
});

test('finishing export writes a zero summary row when no documents match', function () {
    $response = $this->get(route('finishing.export', [
        'date_from' => '1998-01-01',
        'date_to' => '1998-01-31',
    ]));

    $response->assertOk();

    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

    expect($sheet->getCell('A6')->getValue())->toBe('No Document')
        ->and($sheet->getCell('A7')->getValue())->toBe('Total (0 dokumen)')
        ->and($sheet->getCell('H7')->getCalculatedValue())->toEqual(0)
        ->and($sheet->getCell('M7')->getCalculatedValue())->toEqual(0);
});

test('finishing export generates a real xlsx file with report header and summary row', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(8, 50));

    $documents = collect([
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINEXP0005',
            'send_craftsman_date' => '1999-03-02 08:00:00',
            'received_craftsman_date' => '1999-03-02 12:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'finish_weight' => '9.00',
            'result_materialgold' => '0.00',
            'shrink' => '1.00',
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINEXP0008',
            'send_craftsman_date' => '1999-03-03 08:00:00',
            'received_craftsman_date' => '1999-03-03 09:00:00',
            'start_weight' => '5.00',
            'submit_materialgold' => '5.00',
            'finish_weight' => '8.00',
            'result_materialgold' => '1.00',
            'shrink' => '1.00',
        ]),
    ]);

    $response = $this->get(route('finishing.export', [
        'date_from' => '1999-03-01',
        'date_to' => '1999-03-31',
    ]));

    $response->assertOk()
        ->assertDownload('laporan-finishing-1999-03-01_1999-03-31.xlsx');

    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

    expect($sheet->getTitle())->toBe('Laporan Finishing')
        ->and($sheet->getCell('A1')->getValue())->toBe('Laporan Finishing')
        ->and($sheet->getCell('A2')->getValue())->toBe('Pengrajin : All')
        ->and($sheet->getCell('A3')->getValue())->toBe('Tanggal : 01-Mar-1999 s/d 31-Mar-1999')
        ->and($sheet->getCell('A4')->getValue())->toBe('Tanggal Export : 30-Sep-2026 08:50')
        ->and($sheet->getMergeCells())->toHaveKey('A1:Q1')
        ->and($sheet->getCell('A6')->getValue())->toBe('No Document')
        ->and($sheet->getCell('Q6')->getValue())->toBe('Notes')
        ->and($sheet->getCell('A7')->getValue())->toBe('FINEXP0005')
        ->and($sheet->getCell('B7')->getFormattedValue())->toBe('02-Mar-1999 08:00')
        ->and($sheet->getCell('P7')->getValue())->toBe('4 jam')
        ->and($sheet->getCell('A9')->getValue())->toBe('Total (2 dokumen)')
        ->and($sheet->getCell('H9')->getValue())->toBe('=SUM(H7:H8)')
        ->and($sheet->getCell('H9')->getCalculatedValue())->toEqual(15.0)
        ->and($sheet->getCell('I9')->getCalculatedValue())->toEqual(5.0)
        ->and($sheet->getCell('J9')->getCalculatedValue())->toEqual(17.0)
        ->and($sheet->getCell('K9')->getCalculatedValue())->toEqual(1.0)
        ->and($sheet->getCell('L9')->getCalculatedValue())->toEqual(2.0)
        ->and($sheet->getCell('M9')->getFormattedValue())->toBe('10.00%');

    $documents->each->delete();
});
