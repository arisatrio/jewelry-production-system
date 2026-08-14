<?php

use App\Support\SpkDashboardAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('spk dashboard analytics scopes to month and includes forecast', function () {
    $report = (new SpkDashboardAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys([
        'period',
        'summary',
        'statusLists',
        'todayLists',
        'productionTypes',
        'itemDistribution',
        'inProgressByProcess',
        'shrink',
        'control',
        'craftsmen',
        'gold',
        'stone',
        'forecast',
        'planningDaily',
        'today',
    ])
        ->and($report['period']['start'])->toBe('2026-03-01')
        ->and($report['period']['end'])->toStartWith('2026-03-')
        ->and($report['summary'])->toHaveKeys([
            'totalSpk',
            'draftSpk',
            'confirmedSpk',
            'inProgressSpk',
            'doneSpk',
            'overdueSpk',
            'totalShrink',
            'goldRequirement',
            'goldUsed',
            'forecastSpk',
            'forecastQty',
            'planningDoneSpk',
            'planningPendingSpk',
            'todayTargetSpk',
            'todayCreatedSpk',
            'todayInProcessSpk',
            'monthOverdueSpk',
        ])
        ->and($report['today'])->toHaveKeys([
            'date',
            'label',
            'targetSpk',
            'targetDoneSpk',
            'targetPendingSpk',
            'targetQty',
            'createdSpk',
            'inProcessSpk',
            'overdueSpk',
        ])
        ->and($report['todayLists'])->toHaveKeys([
            'todayTarget',
            'todayInProcess',
            'todayCreated',
            'monthOverdue',
        ])
        ->and($report['todayLists']['todayTarget'])->toBeArray()
        ->and($report['todayLists']['todayInProcess'])->toBeArray()
        ->and($report['todayLists']['todayCreated'])->toBeArray()
        ->and($report['todayLists']['monthOverdue'])->toBeArray()
        ->and($report['today']['date'])->toBe(now()->toDateString())
        ->and($report['summary']['todayTargetSpk'])->toBe($report['today']['targetSpk'])
        ->and($report['summary']['todayCreatedSpk'])->toBe($report['today']['createdSpk'])
        ->and($report['summary']['todayInProcessSpk'])->toBe($report['today']['inProcessSpk'])
        ->and($report['summary']['monthOverdueSpk'])->toBe($report['today']['overdueSpk'])
        ->and($report['today']['createdSpk'])->toBeInt()
        ->and($report['today']['inProcessSpk'])->toBeInt()
        ->and($report['today']['overdueSpk'])->toBeInt()
        ->and($report['summary']['goldRequirement'])->toMatch('/^\d+\.\d{3}$/')
        ->and((float) $report['summary']['goldRequirement'])->toBeGreaterThan(0)
        ->and($report['summary']['totalSpk'])->toBeGreaterThan(0)
        ->and($report['summary']['draftSpk'])->toBeInt()
        ->and($report['summary']['confirmedSpk'])->toBeInt()
        ->and($report['summary']['inProgressSpk'])->toBeInt()
        ->and($report['summary']['overdueSpk'])->toBeInt()
        ->and(
            $report['summary']['draftSpk']
            + $report['summary']['confirmedSpk']
            + $report['summary']['inProgressSpk']
            + $report['summary']['doneSpk'],
        )->toBeGreaterThan(0)
        ->and($report['planningDaily'])->toHaveKeys(['doneTotal', 'pendingTotal', 'days'])
        ->and($report['planningDaily']['days'])->not->toBeEmpty()
        ->and($report['planningDaily']['days'][0])->toHaveKeys([
            'date',
            'label',
            'done',
            'pending',
        ])
        ->and($report['productionTypes'][0] ?? null)->toHaveKeys([
            'label',
            'count',
            'qty',
            'percent',
        ])
        ->and(collect($report['productionTypes'])->sum('count'))->toBeGreaterThanOrEqual($report['summary']['totalSpk'])
        ->and($report['inProgressByProcess'])->toBeArray()
        ->and(collect($report['inProgressByProcess'])->sum('count'))->toBeInt()
        ->and($report['itemDistribution'][0] ?? null)->toHaveKeys([
            'label',
            'count',
            'qty',
            'percent',
        ])
        ->and(collect($report['itemDistribution'])->sum('count'))->toBeLessThanOrEqual($report['summary']['totalSpk'])
        ->and(
            collect($report['planningDaily']['days'])->sum('done')
            + collect($report['planningDaily']['days'])->sum('pending'),
        )->toBe(
            $report['planningDaily']['doneTotal']
            + $report['planningDaily']['pendingTotal'],
        )
        ->and($report['statusLists'])->toHaveKeys(['draft', 'confirmed', 'inProgress', 'overdue', 'done'])
        ->and($report['summary']['overdueSpk'])->toBeInt()
        ->and($report['summary']['doneSpk'])->toBeInt()
        ->and($report['statusLists']['overdue'])->toBeArray()
        ->and($report['statusLists']['done'])->toBeArray()
        ->and($report['statusLists']['inProgress'][0] ?? $report['statusLists']['draft'][0] ?? null)->not->toBeNull()
        ->and(
            ($report['statusLists']['inProgress'][0] ?? $report['statusLists']['draft'][0] ?? $report['statusLists']['confirmed'][0] ?? $report['statusLists']['overdue'][0])
        )->toHaveKeys([
            'spkNo',
            'type',
            'customer',
            'item',
            'orderDate',
            'estimatedDelivery',
            'lastProcess',
            'lastProcessDate',
        ])
        ->and($report['forecast'])->toHaveKeys([
            'spkCount',
            'qtyTotal',
            'byItem',
            'byType',
            'types',
            'byItemType',
        ])
        ->and($report['forecast']['spkCount'])->toBeGreaterThan(0)
        ->and($report['forecast']['byItem'])->not->toBeEmpty()
        ->and($report['forecast']['types'])->toBe(['Estimasi', 'Realisasi'])
        ->and($report['forecast']['byItemType'])->not->toBeEmpty()
        ->and($report['forecast']['byItemType'][0])->toHaveKeys(['item', 'total', 'values'])
        ->and($report['forecast']['byItemType'][0]['values'])->toHaveKeys(['Estimasi', 'Realisasi']);
});

test('item distribution groups null item_id as custom', function () {
    $report = (new SpkDashboardAnalytics(Carbon::parse('2026-02-01')))->summarize();

    $customItem = collect($report['itemDistribution'])->firstWhere('label', 'Custom');

    expect($customItem)->not->toBeNull()
        ->and($customItem['count'])->toBeGreaterThan(0)
        ->and($customItem['qty'])->toBeGreaterThan(0);
});

test('spk dashboard analytics for current month returns structured payload', function () {
    $report = (new SpkDashboardAnalytics)->summarize();

    expect($report['period']['start'])->toBe(now()->startOfMonth()->toDateString())
        ->and($report['forecast'])->toBeArray()
        ->and($report['summary']['forecastSpk'])->toBeInt()
        ->and($report['summary'])->toHaveKeys([
            'draftSpk',
            'confirmedSpk',
            'inProgressSpk',
            'doneSpk',
        ]);
});
