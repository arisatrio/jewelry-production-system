<?php

use App\Models\Production;
use App\Support\SpkProcessMapper;
use Tests\TestCase;

uses(TestCase::class);

test('spk process mapper exposes expected process to table mapping', function () {
    $mapper = new SpkProcessMapper;
    $tabs = collect($mapper->tabs())->keyBy('key');

    expect($tabs->get('JewelCAD')['tables'])->toBe(['requestjwcaddetails'])
        ->and($tabs->get('Resin')['tables'])->toBe(['resin'])
        ->and($tabs->get('Coran')['tables'])->toBe(['coranspk'])
        ->and($tabs->get('Finishing')['tables'])->toBe(['finishinghandmade'])
        ->and($tabs->get('Poles Rangka')['tables'])->toBe(['polishframe'])
        ->and($tabs->get('Pasang Batu')['tables'])->toBe(['diamondmounting', 'diamondunload'])
        ->and($tabs->get('Poles Chrome')['tables'])->toBe(['polishfinishedgood'])
        ->and($tabs->get('Pengerjaan Lanjutan')['tables'])->toBe(['grafir'])
        ->and($tabs->get('Pengerjaan Lanjutan')['placement'])->toBe('main')
        ->and($tabs->get('Modifikasi Barang Jadi')['tables'])->toBe([])
        ->and($tabs->get('Modifikasi Barang Jadi')['placement'])->toBe('main')
        ->and($tabs->get('JewelCAD')['placement'])->toBe('proses-produksi');
});

test('spk process mapper resolves tables for last process names', function (string $lastProcess, array $tables) {
    expect((new SpkProcessMapper)->tablesForLastProcess($lastProcess))->toBe($tables);
})->with([
    ['Poles Chrome', ['polishfinishedgood']],
    ['POLES CHROME', ['polishfinishedgood']],
    ['Finishing / Handmade', ['finishinghandmade']],
    ['Coran', ['coranspk']],
    ['Done', []],
    ['', []],
]);

test('spk process mapper resolves default tab from last process', function (string $lastProcess, string $mainSection, string $processKey) {
    $selection = (new SpkProcessMapper)->resolveDefaultSelection($lastProcess);

    expect($selection)->toBe([
        'mainSection' => $mainSection,
        'processKey' => $processKey,
    ]);
})->with([
    ['Coran', 'informasi-produksi', 'Coran'],
    ['Finishing/Handmade', 'informasi-produksi', 'Finishing'],
    ['Finishing / Handmade', 'informasi-produksi', 'Finishing'],
    ['Poles Chrome', 'informasi-produksi', 'Poles Chrome'],
    ['JewelCud', 'informasi-produksi', 'JewelCAD'],
    ['Grafir', 'informasi-produksi', 'JewelCAD'],
    ['Done', 'informasi-produksi', 'JewelCAD'],
    ['', 'informasi-produksi', 'JewelCAD'],
]);

test('spk process mapper loads production process records by spk_id', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);

    expect($processes)->toBeArray()->not->toBeEmpty()
        ->and($processes[0])->toHaveKeys(['key', 'label', 'tables', 'recordCount', 'sources']);

    $coran = collect($processes)->firstWhere('key', 'Coran');

    expect($coran)->not->toBeNull()
        ->and($coran['sources'][0]['table'])->toBe('coranspk');
});

test('spk process mapper enriches jewelcad rows from requestjwcad parent', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);
    $jewelCad = collect($processes)->firstWhere('key', 'JewelCAD');

    expect($jewelCad)->not->toBeNull()
        ->and($jewelCad['sources'][0]['table'])->toBe('requestjwcaddetails');

    if ($production->spk_no === '2024/PRD/00012' && $jewelCad['recordCount'] > 0) {
        $row = $jewelCad['sources'][0]['records'][0];

        expect($row)->toHaveKeys(['doc_no', 'tanggal', 'material'])
            ->and($row['doc_no'])->toBe('JWC0000053')
            ->and($row['tanggal'])->toBe('23-Jul-2024')
            ->and(array_key_first($row))->toBe('doc_no')
            ->and(array_keys($row)[1] ?? null)->toBe('tanggal');
    }
});

test('spk process mapper enriches coran rows from coran parent', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);
    $coran = collect($processes)->firstWhere('key', 'Coran');

    expect($coran)->not->toBeNull()
        ->and($coran['sources'][0]['table'])->toBe('coranspk');

    if ($production->spk_no === '2024/PRD/00012' && $coran['recordCount'] > 0) {
        $row = $coran['sources'][0]['records'][0];

        expect($row)->toHaveKeys([
            'doc_no',
            'tanggal',
            'weight',
            'status',
            'total_submit_material',
            'total_result_material',
            'submit_materials',
            'result_materials',
            'coran_breakdown',
            'pengrajin',
            'craftsman_id',
            'shrink',
            'shrink_percent',
            'spk_usage_percent',
            'spk_usage_gold_color',
            'spk_no',
        ])
            ->and($row['doc_no'])->toBe('COR0000015')
            ->and($row['tanggal'])->toBe('07-Aug-2024')
            ->and($row['status'])->toBe('OK')
            ->and($row['pengrajin'])->toBeNull()
            ->and($row['craftsman_id'])->toBeNull()
            ->and($row['spk_no'])->toBe('2024/PRD/00012')
            ->and($row['total_submit_material'])->toBe(448.04)
            ->and($row['total_result_material'])->toBe(294.66)
            ->and($row['submit_materials'])->toBe([
                ['name' => 'Rose Gold', 'weight' => 57.95, 'notes' => null],
                ['name' => 'White Gold', 'weight' => 390.09, 'notes' => null],
            ])
            ->and($row['result_materials'])->toBe([
                ['name' => 'Rose Gold', 'weight' => 47.76, 'notes' => null],
                ['name' => 'White Gold', 'weight' => 246.9, 'notes' => null],
            ])
            ->and($row['coran_breakdown'])->toHaveCount(3)
            ->and($row['coran_breakdown'][0]['color'])->toBe('Rose Gold')
            ->and($row['coran_breakdown'][0]['bahan'])->toBe([
                ['name' => 'Bahan Rose Sisa Finishing', 'weight' => 57.95],
            ])
            ->and($row['coran_breakdown'][0]['sisa'])->toBe([
                ['name' => 'Bahan Rose Sisa Finishing', 'weight' => 47.5],
                ['name' => 'Bahan Emas Serbuk Rose Gold Cor', 'weight' => 0.26],
            ])
            ->and($row['coran_breakdown'][1]['color'])->toBe('White Gold')
            ->and($row['coran_breakdown'][1]['bahan'])->toHaveCount(3)
            ->and($row['coran_breakdown'][1]['sisa'])->toHaveCount(2)
            ->and($row['coran_breakdown'][2]['color'])->toBe('Yellow Gold')
            ->and($row['coran_breakdown'][2]['bahan'])->toBe([])
            ->and($row['coran_breakdown'][2]['sisa'])->toBe([])
            ->and($row['shrink'])->toBe(-0.27)
            ->and($row['shrink_percent'])->toBe(-0.06)
            ->and($row['spk_usage_gold_color'])->toBe('White Gold')
            ->and($row['spk_usage_percent'])->toBe(0.36)
            ->and(array_key_first($row))->toBe('doc_no')
            ->and(array_keys($row)[1] ?? null)->toBe('tanggal');
    }
});

test('spk process mapper enriches craftsman names for finishing rows', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);
    $finishing = collect($processes)->firstWhere('key', 'Finishing');

    expect($finishing)->not->toBeNull();

    if ($production->spk_no === '2024/PRD/00012' && $finishing['recordCount'] > 0) {
        $row = $finishing['sources'][0]['records'][0];

        expect($row)->toHaveKey('craftsman_name')
            ->and($row['craftsman_name'])->toBe('Dimas')
            ->and($row['craftsman_id'])->toBe(18)
            ->and($row)->toHaveKeys([
                'materials_out',
                'materials_in',
                'tanggal',
                'pengrajin',
                'shrink_percent',
            ])
            ->and($row['pengrajin'])->toBe('Dimas');
    }
});

test('spk process mapper resolves craftsman name from craftman_id for diamond mounting', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2025/PRD/02901')
        ->first();

    if ($production === null) {
        expect(true)->toBeTrue();

        return;
    }

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);
    $pasangBatu = collect($processes)->firstWhere('key', 'Pasang Batu');
    $row = collect($pasangBatu['sources'][0]['records'] ?? [])
        ->firstWhere('doc_no', 'DMD0004171');

    expect($row)->not->toBeNull()
        ->and($row['craftman_id'])->toBe(41)
        ->and($row['craftsman_id'])->toBe(41)
        ->and($row['craftsman_name'])->toBe('Deni')
        ->and($row['tanggal'])->toBe('28-Jan-2026')
        ->and($row['spk_no'])->toBe('2025/PRD/02901')
        ->and($row['mounting_shrink'])->toBe(0.08)
        ->and($row['stone_setting'])->toHaveCount(2)
        ->and($row['stone_setting'][0]['batu'])->toBe('Round B01 1.00 MM')
        ->and($row['stone_return'])->toBe([])
        ->and($row['stone_diamonds'])->toBe([])
        ->and($row['stone_mounted'])->toHaveCount(2)
        ->and($row['stone_mounted'][1]['kode'])->toBe('B01')
        ->and($row['stone_mounted'][1]['shape'])->toBe('Round')
        ->and($row['stone_mounted'][1]['pcs'])->toBe(24)
        ->and($row['approvals'])->toHaveCount(4)
        ->and($row['approvals'][0]['status'])->toBe('DMT010')
        ->and($row['approvals'][0]['statusLabel'])->toBe('Serahkan ke PPIC')
        ->and($row['approvals'][0]['approve'])->toBe('OK')
        ->and($row['approvals'][0]['createdBy'])->toBe('Dila')
        ->and($row['approvals'][3]['status'])->toBe('DMTDONE')
        ->and($row['approvals'][3]['statusLabel'])->toBe('Completed');
});

test('spk process mapper attaches finishing material breakdown lines', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2026/PRD/00594')
        ->first();

    if ($production === null) {
        expect(true)->toBeTrue();

        return;
    }

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);
    $finishing = collect($processes)->firstWhere('key', 'Finishing');
    $row = collect($finishing['sources'][0]['records'] ?? [])
        ->firstWhere('doc_no', 'FIN0011206');

    expect($row)->not->toBeNull()
        ->and($row['materials_out'])->toHaveCount(2)
        ->and($row['materials_out'][0]['name'])->toBe('Patri Loket')
        ->and($row['materials_out'][0]['weight'])->toBe(0.08)
        ->and($row['materials_in'])->toHaveCount(4)
        ->and($row['tanggal'])->toBe('16-Mar-2026')
        ->and($row['pengrajin'])->toBe('Jajang')
        ->and($row['shrink_percent'])->toBe(7.41);
});

test('spk process mapper orders process records by created_date ascending', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $processes = (new SpkProcessMapper)->forProduction((int) $production->row_id);

    foreach ($processes as $process) {
        foreach ($process['sources'] as $source) {
            $dates = collect($source['records'])
                ->pluck('created_date')
                ->filter()
                ->values();

            expect($dates->all())->toBe($dates->sort()->values()->all());
        }
    }
});
