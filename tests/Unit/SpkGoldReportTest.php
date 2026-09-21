<?php

use App\Models\Production;
use App\Support\SpkGoldReport;
use Tests\TestCase;

uses(TestCase::class);

test('spk gold report summarizes serah kembali and terpakai', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $report = (new SpkGoldReport)->forProduction($production);

    expect($report)->toHaveKeys([
        'issued',
        'returned',
        'used',
        'difference',
        'materials',
        'totalLabel',
    ])
        ->and($report['materials'])->toBeArray();

    if ($production->spk_no === '2024/PRD/00012') {
        expect($report['issued'])->toBe('1.79')
            ->and($report['returned'])->toBe('0.51')
            ->and($report['used'])->toBe('1.28')
            ->and($report['difference'])->toBe('0.00')
            ->and($report['materials'])->toHaveCount(6)
            ->and($report['materials'][0]['type'])->toBe('Serah')
            ->and($report['totalLabel'])->toBe('1.28 g terpakai');
    }
});
