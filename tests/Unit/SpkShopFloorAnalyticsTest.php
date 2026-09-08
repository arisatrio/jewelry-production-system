<?php

use App\Support\SpkShopFloorAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('shop floor analytics scopes period and includes floor metrics', function () {
    $report = (new SpkShopFloorAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys([
        'period',
        'backlogYear',
        'summary',
        'wipByProcess',
        'agingBuckets',
        'agingByProcess',
        'craftsmen',
    ])
        ->and($report['period']['start'])->toBe('2026-03-01')
        ->and($report['period']['end'])->toStartWith('2026-03-')
        ->and($report['backlogYear'])->toBe((int) now()->year)
        ->and($report['summary'])->toHaveKeys([
            'wipSpk',
            'overdueWipSpk',
            'completedThisMonth',
            'bottleneckProcess',
            'bottleneckCount',
            'avgAgeDays',
            'agedOver7Days',
            'activeProcesses',
        ])
        ->and($report['summary']['wipSpk'])->toBeInt()
        ->and($report['summary']['overdueWipSpk'])->toBeInt()
        ->and($report['summary']['completedThisMonth'])->toBeInt()
        ->and($report['summary']['bottleneckCount'])->toBeInt()
        ->and($report['summary']['agedOver7Days'])->toBeInt()
        ->and($report['summary']['activeProcesses'])->toBe(count($report['wipByProcess']))
        ->and($report['wipByProcess'])->toBeArray()
        ->and($report['agingBuckets'])->toHaveCount(4)
        ->and($report['agingBuckets'][0])->toHaveKeys(['label', 'count'])
        ->and($report['agingByProcess'])->toBeArray()
        ->and($report['craftsmen'])->toBeArray();
});

test('shop floor analytics for current month returns structured payload', function () {
    $report = (new SpkShopFloorAnalytics)->summarize();

    expect($report['period']['start'])->toBe(now()->startOfMonth()->toDateString())
        ->and($report['summary']['wipSpk'])->toBeInt()
        ->and(collect($report['wipByProcess'])->sum('count'))->toBe($report['summary']['wipSpk']);
});
