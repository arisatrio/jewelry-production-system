<?php

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
        expect($report['rows'])->toHaveCount(5)
            ->and($report['rows'][0]['process'])->toBe('Finishing / Handmade')
            ->and($report['rows'][0]['shrink'])->toBe('0.210')
            ->and($report['rows'][0]['startWeight'])->toBe('1.410')
            ->and($report['rows'][0]['endWeight'])->toBe('1.200')
            ->and($report['rows'][0]['shrinkPercent'])->toBe('14.89')
            ->and($report['rows'][0]['tolerance'])->toBe('10.94')
            ->and($report['rows'][0]['toleranceStatus'])->toBe('NOK')
            ->and($report['rows'][0]['setorDate'])->toMatch('/^\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}$/')
            ->and($report['rows'][2]['process'])->toBe('Pasang Batu')
            ->and($report['rows'][2]['shrink'])->toBe('0.160')
            ->and($report['rows'][2]['startWeight'])->toBe('1.230')
            ->and($report['rows'][2]['endWeight'])->toBe('1.070')
            ->and($report['rows'][2])->toHaveKeys(['shrinkPercent', 'tolerance', 'toleranceStatus'])
            ->and($report['planningWeight'])->toBe('2.800')
            ->and($report['startWeight'])->toBe('1.410')
            ->and($report['endWeight'])->toBe('2.230')
            ->and($report['goldIssued'])->toBe('1.790')
            ->and($report['goldReturned'])->toBe('0.510')
            ->and($report['goldUsed'])->toBe('1.280')
            ->and($report['goldMaterials'])->toHaveCount(6)
            ->and($report['goldMaterials'][0]['name'])->toBe('Patri (JB)')
            ->and($report['goldMaterials'][0]['type'])->toBe('Serah')
            ->and($report['goldMaterials'][0]['weight'])->toBe('0.130')
            ->and($report['totalShrink'])->toBe('0.550')
            ->and($report['totalShrinkPercent'])->toBe('19.64')
            ->and($report['totalLost'])->toBe('0.570')
            ->and($report['totalLostPercent'])->toBe('20.36')
            ->and($report['totalLabel'])->toBe('0.550 g');
    }
});
