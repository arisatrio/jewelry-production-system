<?php

use App\Support\SpkSkuOutputAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('sku output analytics returns output payload', function () {
    $report = (new SpkSkuOutputAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys(['period', 'summary', 'bySku', 'byItem'])
        ->and($report['period']['start'])->toBe('2026-03-01')
        ->and($report['summary'])->toHaveKeys([
            'totalSpk',
            'totalQty',
            'doneSpk',
            'doneQty',
            'uniqueSku',
            'completionPercent',
        ])
        ->and($report['summary']['totalSpk'])->toBeInt()
        ->and($report['summary']['totalQty'])->toBeInt()
        ->and($report['bySku'])->toBeArray()
        ->and($report['byItem'])->toBeArray();
});
