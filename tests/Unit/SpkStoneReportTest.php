<?php

use App\Models\Production;
use App\Support\SpkStoneReport;
use Tests\TestCase;

uses(TestCase::class);

test('spk stone report builds awal vs akhir carat rows', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $report = (new SpkStoneReport)->forProduction($production);

    expect($report)->toHaveKeys([
        'rows',
        'totalStartCrt',
        'totalEndCrt',
        'totalDifference',
        'totalLabel',
    ])
        ->and($report['rows'])->toBeArray();

    if ($production->spk_no === '2024/PRD/00012') {
        expect($report['rows'])->not->toBeEmpty()
            ->and($report['totalStartCrt'])->toBe('0.4450')
            ->and($report['totalEndCrt'])->toBe('0.0000')
            ->and($report['totalDifference'])->toBe('0.4450');
    }
});
