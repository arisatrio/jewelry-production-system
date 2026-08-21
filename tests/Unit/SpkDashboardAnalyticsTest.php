<?php

use App\Models\Production;
use App\Support\SpkDashboardAnalytics;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        ->and($report['backlogYear'])->toBe((int) now()->year)
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

test('dashboard backlog status grouping matches dashboard card labels', function (array $attributes, string $key, string $label, bool $hasCompletedPolesChrome) {
    $production = new Production($attributes);
    $production->row_id = 1;

    expect(SpkDashboardAnalytics::backlogStatusKey($production, $hasCompletedPolesChrome))->toBe($key)
        ->and(SpkDashboardAnalytics::backlogStatusLabel($production, $hasCompletedPolesChrome))->toBe($label)
        ->and(SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$key])->toBe($label);
})->with([
    'done poles chrome completed' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => 'Poles Chrome',
            'is_inprocess' => 1,
        ],
        'done',
        'Done',
        true,
    ],
    'done poles rangka completed' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => 'Poles Rangka',
            'is_inprocess' => 1,
        ],
        'done',
        'Done',
        true,
    ],
    'done poles barang jadi rpfdone for reference type' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => 'Poles Barang Jadi',
            'is_inprocess' => 1,
            'spk_type' => 'Reparasi',
        ],
        'done',
        'Done',
        true,
    ],
    'spkdone without process is confirmed' => [
        [
            'status' => 'SPKDONE',
            'status_order' => 'NO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'confirmed',
        'Approved',
        false,
    ],
    'confirmed manager approved' => [
        [
            'status' => 'SPKDONE',
            'status_order' => 'RO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'confirmed',
        'Approved',
        false,
    ],
    'in progress last process' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => 'Coran',
            'is_inprocess' => 0,
        ],
        'inProgress',
        'In Progress',
        false,
    ],
    'draft pending approval' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'pendingManager',
        'Menunggu Approval',
        false,
    ],
    'empty status is draft' => [
        [
            'status' => '',
            'status_order' => 'NO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'draft',
        'Draft',
        false,
    ],
    'repeat order still waiting manager is pending approval' => [
        [
            'status' => 'SPK010',
            'status_order' => 'RO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'pendingManager',
        'Menunggu Approval',
        false,
    ],
]);

test('dashboard waiting approval backlog is sent to production not draft', function () {
    $draft = Production::factory()->create([
        'status' => '',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(89000, 89999)),
        'estimated_delivery_time' => now()->subDays(5)->toDateString(),
    ]);
    $pending = Production::factory()->create([
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(88000, 88999)),
    ]);
    $sent = Production::factory()->create([
        'status' => 'SPKDONE',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(87000, 87999)),
    ]);

    $report = (new SpkDashboardAnalytics)->summarize();
    $waitingNos = collect($report['statusLists']['draft'])->pluck('spkNo');
    $approvedNos = collect($report['statusLists']['confirmed'])->pluck('spkNo');
    $overdueNos = collect($report['statusLists']['overdue'])->pluck('spkNo');

    expect($waitingNos)->not->toContain($draft->spk_no)
        ->and($waitingNos)->not->toContain($pending->spk_no)
        ->and($waitingNos)->toContain($sent->spk_no)
        ->and($approvedNos)->not->toContain($sent->spk_no)
        ->and($overdueNos)->not->toContain($draft->spk_no);

    $draft->delete();
    $pending->delete();
    $sent->delete();
});

test('dashboard backlog status lists only include current year spk', function () {
    $currentYear = Production::factory()->create([
        'status' => 'SPKDONE',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(86000, 86999)),
        'created_date' => now(),
    ]);
    $previousYear = Production::factory()->create([
        'status' => 'SPKDONE',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->subYear()->format('Y'), random_int(85000, 85999)),
        'created_date' => now()->subYear(),
    ]);

    $report = (new SpkDashboardAnalytics)->summarize();
    $allListedNos = collect($report['statusLists'])
        ->flatMap(fn (array $rows) => collect($rows)->pluck('spkNo'))
        ->values();

    expect($report['backlogYear'])->toBe((int) now()->year)
        ->and($allListedNos)->toContain($currentYear->spk_no)
        ->and($allListedNos)->not->toContain($previousYear->spk_no);

    $currentYear->delete();
    $previousYear->delete();
});

test('dashboard overdue is subset of approved and in progress past delivery', function () {
    $approvedOverdue = Production::factory()->create([
        'status' => 'SPKDONE',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(84000, 84999)),
        'created_date' => now(),
        'estimated_delivery_time' => now()->subDays(2)->toDateString(),
    ]);
    $approvalId = DB::connection('third')->table('sysapproval')->insertGetId([
        'doc_id' => $approvedOverdue->row_id,
        'doc_no' => $approvedOverdue->spk_no,
        'doc_name' => 'spk',
        'status' => 'SPKDONE',
        'approve' => 'OK',
        'notes' => null,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');
    $pendingOverdue = Production::factory()->create([
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(83000, 83999)),
        'created_date' => now(),
        'estimated_delivery_time' => now()->subDays(2)->toDateString(),
    ]);
    $inProgressOverdue = Production::factory()->create([
        'status' => 'SPKDONE',
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(82000, 82999)),
        'created_date' => now(),
        'estimated_delivery_time' => now()->subDays(2)->toDateString(),
    ]);

    $report = (new SpkDashboardAnalytics)->summarize();
    $overdueNos = collect($report['statusLists']['overdue'])->pluck('spkNo');

    expect($report['summary']['overdueSpk'])
        ->toBeLessThanOrEqual($report['summary']['confirmedSpk'] + $report['summary']['inProgressSpk'])
        ->and($overdueNos)->toContain($approvedOverdue->spk_no)
        ->and($overdueNos)->toContain($inProgressOverdue->spk_no)
        ->and($overdueNos)->not->toContain($pendingOverdue->spk_no);

    DB::connection('third')->table('sysapproval')->where('row_id', $approvalId)->delete();
    $approvedOverdue->delete();
    $pendingOverdue->delete();
    $inProgressOverdue->delete();
});

test('completed production includes rpfdone for reference types only', function (string $spkType) {
    $production = Production::factory()->create([
        'spk_type' => $spkType,
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-RPF-UNIT-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    expect(SpkDashboardAnalytics::hasCompletedProduction((int) $production->row_id))->toBeTrue()
        ->and(SpkDashboardAnalytics::completedReferencePolesBarangJadiSpkIds([(int) $production->row_id]))
        ->toContain((int) $production->row_id)
        ->and(SpkDashboardAnalytics::backlogStatusKey($production))->toBe('done');

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'exchange' => ['Exchange'],
    'refund' => ['Refund'],
    'reparasi' => ['Reparasi'],
]);

test('completed production includes rfhdone for reference types only', function (string $spkType) {
    $production = Production::factory()->create([
        'spk_type' => $spkType,
        'status' => 'SPK010',
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('finishinghandmade')->insertGetId([
        'doc_no' => 'TEST-RFH-UNIT-'.$production->row_id,
        'process_name' => 'Finishing',
        'spk_id' => $production->row_id,
        'status' => 'RFHDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    expect(SpkDashboardAnalytics::hasCompletedProduction((int) $production->row_id))->toBeTrue()
        ->and(SpkDashboardAnalytics::completedReferenceFinishingSpkIds([(int) $production->row_id]))
        ->toContain((int) $production->row_id)
        ->and(SpkDashboardAnalytics::backlogStatusKey($production))->toBe('done');

    DB::connection('third')->table('finishinghandmade')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'exchange' => ['Exchange'],
    'refund' => ['Refund'],
    'reparasi' => ['Reparasi'],
]);

test('completed production ignores rpfdone for non-reference types', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-RPF-STOCK-UNIT-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    expect(SpkDashboardAnalytics::hasCompletedProduction((int) $production->row_id))->toBeFalse()
        ->and(SpkDashboardAnalytics::completedReferencePolesBarangJadiSpkIds([(int) $production->row_id]))
        ->toBe([])
        ->and(SpkDashboardAnalytics::backlogStatusKey($production))->toBe('inProgress');

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
});

test('completed production ignores rfhdone for non-reference types', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('finishinghandmade')->insertGetId([
        'doc_no' => 'TEST-RFH-STOCK-UNIT-'.$production->row_id,
        'process_name' => 'Finishing',
        'spk_id' => $production->row_id,
        'status' => 'RFHDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    expect(SpkDashboardAnalytics::hasCompletedProduction((int) $production->row_id))->toBeFalse()
        ->and(SpkDashboardAnalytics::completedReferenceFinishingSpkIds([(int) $production->row_id]))
        ->toBe([])
        ->and(SpkDashboardAnalytics::backlogStatusKey($production))->toBe('inProgress');

    DB::connection('third')->table('finishinghandmade')->where('row_id', $processId)->delete();
    $production->delete();
});
