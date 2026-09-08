<?php

use App\Support\SpkMaterialYieldAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('material yield analytics scopes to month and includes material metrics', function () {
    $report = (new SpkMaterialYieldAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys([
        'period',
        'summary',
        'shrink',
        'gold',
        'stone',
        'control',
        'craftsmen',
    ])
        ->and($report['period']['start'])->toBe('2026-03-01')
        ->and($report['period']['end'])->toStartWith('2026-03-')
        ->and($report['summary'])->toHaveKeys([
            'totalSpk',
            'totalShrink',
            'shrinkOkCount',
            'shrinkNokCount',
            'goldRequirement',
            'goldIssued',
            'goldReturned',
            'goldUsed',
            'goldVariance',
            'stoneStartCrt',
            'stoneEndCrt',
            'stoneDifference',
            'stoneLossPercent',
            'avgYieldPercent',
            'avgGoldYieldPercent',
        ])
        ->and($report['summary']['totalShrink'])->toMatch('/^-?\d+\.\d{3}$/')
        ->and($report['summary']['goldRequirement'])->toMatch('/^\d+\.\d{3}$/')
        ->and($report['summary']['goldUsed'])->toMatch('/^-?\d+\.\d{3}$/')
        ->and($report['summary']['goldVariance'])->toMatch('/^-?\d+\.\d{3}$/')
        ->and($report['summary']['stoneDifference'])->toMatch('/^-?\d+\.\d{4}$/')
        ->and($report['shrink'])->toHaveKeys(['byProcess', 'totalShrink', 'okCount', 'nokCount'])
        ->and($report['gold'])->toHaveKeys(['issued', 'returned', 'used', 'difference'])
        ->and($report['stone'])->toHaveKeys(['startCrt', 'endCrt', 'difference'])
        ->and($report['control'])->toHaveKeys([
            'avgYieldPercent',
            'avgGoldYieldPercent',
        ])
        ->and($report['craftsmen'])->toBeArray();
});

test('material yield analytics for current month returns structured payload', function () {
    $report = (new SpkMaterialYieldAnalytics)->summarize();

    expect($report['period']['start'])->toBe(now()->startOfMonth()->toDateString())
        ->and($report['summary']['totalSpk'])->toBeInt()
        ->and($report['shrink']['byProcess'])->toBeArray();
});
