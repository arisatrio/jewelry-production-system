<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\SpkShrinkSummary;
use Tests\TestCase;

uses(TestCase::class);

test('spk shrink summary builds ordered rows for a complete production', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $report = (new SpkShrinkSummary)->forProduction($production);

    expect($report)->toHaveKeys([
        'rows',
        'planningWeight',
        'startWeight',
        'endWeight',
        'finishedGoodsWithStone',
        'goldIssued',
        'goldReturned',
        'goldUsed',
        'goldMaterials',
        'totalShrink',
        'totalShrinkPercent',
        'totalLost',
        'totalLostPercent',
        'totalLabel',
    ])
        ->and($report['rows'])->toBeArray();

    if ($production->spk_no === '2024/PRD/00012') {
        expect($report['rows'])->toHaveCount(6)
            ->and($report['rows'][0]['process'])->toBe('Cor')
            ->and($report['rows'][0]['endWeight'])->toBe('1.41')
            ->and($report['rows'][0]['setorDate'])->toBe('07-Aug-2024')
            ->and($report['rows'][0]['shrink'])->toBeNull()
            ->and($report['rows'][1]['process'])->toBe('Finishing / Handmade')
            ->and($report['rows'][1]['shrink'])->toBe('0.21')
            ->and($report['rows'][1]['startWeight'])->toBe('1.41')
            ->and($report['rows'][1]['endWeight'])->toBe('1.20')
            ->and($report['rows'][1]['shrinkPercent'])->toBe('10.94')
            ->and($report['rows'][1]['tolerance'])->toBe('10.94')
            ->and($report['rows'][1]['toleranceStatus'])->toBe('OK')
            ->and($report['rows'][1]['setorDate'])->toMatch('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}$/')
            ->and($report['rows'][3]['process'])->toBe('Pasang Batu')
            ->and($report['rows'][3]['shrink'])->toBe('0.16')
            ->and($report['rows'][3]['startWeight'])->toBe('1.23')
            ->and($report['rows'][3]['endWeight'])->toBe('1.07')
            ->and($report['rows'][3])->toHaveKeys(['shrinkPercent', 'tolerance', 'toleranceStatus'])
            ->and($report['planningWeight'])->toBe('2.80')
            ->and($report['startWeight'])->toBe('1.41')
            ->and($report['endWeight'])->toBe('2.23')
            ->and($report['finishedGoodsWithStone'])->toBe('2.32')
            ->and($report['goldIssued'])->toBe('1.79')
            ->and($report['goldReturned'])->toBe('0.51')
            ->and($report['goldUsed'])->toBe('1.28')
            ->and($report['goldMaterials'])->toHaveCount(6)
            ->and($report['goldMaterials'][0]['name'])->toBe('Patri (JB)')
            ->and($report['goldMaterials'][0]['type'])->toBe('Serah')
            ->and($report['goldMaterials'][0]['weight'])->toBe('0.13')
            ->and($report['totalShrink'])->toBe('0.55')
            ->and($report['totalShrinkPercent'])->toBe('19.64')
            ->and($report['totalLost'])->toBe('0.57')
            ->and($report['totalLostPercent'])->toBe('20.36')
            ->and($report['totalLabel'])->toBe('0.55 g');
    }
});

test('spk shrink summary adds a cor row with the total cast weight', function () {
    $production = Production::factory()->create();
    $coran = Coran::factory()->create(['trans_date' => '2026-09-01']);
    $deletedCoran = Coran::factory()->create(['is_deleted' => 1]);

    $colorLine = CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => null,
        'weight_rosegold' => '1.25',
        'weight_yellowgold' => '0.50',
    ]);
    $deletedLine = CoranSpk::factory()->deleted()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);
    $deletedCoranLine = CoranSpk::factory()->create([
        'row_id' => $deletedCoran->row_id,
        'spk_id' => $production->row_id,
    ]);

    try {
        $report = (new SpkShrinkSummary)->forProduction($production);

        expect($report['rows'])->toHaveCount(1)
            ->and($report['rows'][0])->toMatchArray([
                'no' => 1,
                'process' => 'Cor',
                'setorDate' => '01-Sep-2026',
                'startWeight' => null,
                'endWeight' => '1.75',
                'shrink' => null,
                'shrinkPercent' => null,
            ])
            ->and($report['totalShrink'])->toBe('0.00');
    } finally {
        collect([$colorLine, $deletedLine, $deletedCoranLine])->each->delete();
        $coran->delete();
        $deletedCoran->delete();
        $production->delete();
    }
});
