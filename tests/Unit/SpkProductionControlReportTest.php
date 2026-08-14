<?php

use App\Models\Production;
use App\Support\SpkProductionControlReport;
use Tests\TestCase;

uses(TestCase::class);

test('spk production control report includes lead idle and yield', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $report = (new SpkProductionControlReport)->forProduction($production);

    expect($report)->toHaveKeys([
        'leadTime',
        'idleTimes',
        'yieldPlanning',
    ])
        ->and($report['leadTime'])->toHaveKeys([
            'startDate',
            'endDate',
            'durationLabel',
            'durationDays',
            'estimatedDays',
            'varianceDays',
            'varianceLabel',
        ])
        ->and($report['idleTimes'])->toBeArray()
        ->and($report['yieldPlanning'])->toHaveKeys([
            'planningWeight',
            'endWeight',
            'yieldPercent',
            'goldUsed',
            'goldYieldPercent',
        ]);

    if ($production->spk_no === '2024/PRD/00012') {
        expect($report['yieldPlanning']['planningWeight'])->toBe('2.800')
            ->and($report['yieldPlanning']['endWeight'])->toBe('2.230')
            ->and($report['yieldPlanning']['yieldPercent'])->toBe('79.64')
            ->and($report['yieldPlanning']['goldUsed'])->toBe('1.280')
            ->and($report['idleTimes'])->not->toBeEmpty()
            ->and($report['leadTime']['estimatedDays'])->toBe(121.0);
    }
});
