<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkDashboardAnalytics
{
    /**
     * @var array<string, string>
     */
    public const BACKLOG_STATUS_LABELS = [
        'draft' => 'Draft',
        'pendingManager' => 'Menunggu Approval',
        'confirmed' => 'Approved',
        'inProgress' => 'In Progress',
        'done' => 'Done',
    ];

    /**
     * Poles Chrome (polishfinishedgood) statuses that mean production is done:
     * PFGDONE = Poles BJ completed, PFG040 = Serahkan ke JB.
     *
     * @var list<string>
     */
    public const POLES_CHROME_DONE_STATUSES = ['PFGDONE', 'PFG040'];

    /**
     * Poles Barang Jadi (polishfinishedgood) statuses that mean production is done
     * for SPK tipe Exchange / Refund / Reparasi:
     * RPFDONE = Poles Barang Jadi completed.
     *
     * @var list<string>
     */
    public const POLES_BARANG_JADI_REF_DONE_STATUSES = ['RPFDONE'];

    /**
     * Finishing (finishinghandmade) statuses that mean production is done
     * for SPK tipe Exchange / Refund / Reparasi:
     * RFHDONE = Finishing reparasi completed.
     *
     * @var list<string>
     */
    public const FINISHING_REF_DONE_STATUSES = ['RFHDONE'];

    /**
     * Poles Rangka (polishframe) statuses that mean production is done:
     * PRKDONE = Completed, PRK040 = Serahkan ke JB.
     *
     * @var list<string>
     */
    public const POLES_RANGKA_DONE_STATUSES = ['PRKDONE', 'PRK040'];

    private CarbonInterface $periodStart;

    private CarbonInterface $periodEnd;

    public function __construct(?CarbonInterface $month = null)
    {
        $anchor = Carbon::parse(($month ?? now())->toDateTimeString())->startOfMonth();
        $this->periodStart = $anchor->copy()->startOfDay();
        $this->periodEnd = $anchor->copy()->endOfMonth()->endOfDay();
    }

    /**
     * Exclusive backlog group used by dashboard cards.
     *
     * @return 'draft'|'confirmed'|'inProgress'|'done'
     */
    public static function backlogStatusKey(Production $production, ?bool $hasCompletedPolesChrome = null): string
    {
        $isDone = $hasCompletedPolesChrome ?? self::hasCompletedProduction((int) $production->row_id);

        if ($isDone) {
            return 'done';
        }

        $status = strtoupper(trim((string) ($production->status ?? '')));
        $isInProcess = (int) ($production->is_inprocess ?? 0) !== 0;
        $hasLastProcess = $production->last_process !== null;
        $isConfirmed = $status === SpkApprovalService::STATUS_DONE
            && ! $isInProcess
            && ! $hasLastProcess;

        if ($isConfirmed) {
            return 'confirmed';
        }

        if ($hasLastProcess || $isInProcess) {
            return 'inProgress';
        }

        if ($status === SpkApprovalService::STATUS_PENDING) {
            return 'pendingManager';
        }

        return 'draft';
    }

    public static function backlogStatusLabel(Production $production, ?bool $hasCompletedPolesChrome = null): string
    {
        return self::BACKLOG_STATUS_LABELS[self::backlogStatusKey($production, $hasCompletedPolesChrome)];
    }

    public static function hasCompletedPolesChrome(int $spkId): bool
    {
        return self::completedPolesChromeSpkIds([$spkId]) !== [];
    }

    public static function hasCompletedProduction(int $spkId): bool
    {
        return self::completedProductionSpkIds([$spkId]) !== [];
    }

    /**
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    public static function completedPolesChromeSpkIds(array $spkIds): array
    {
        return self::completedSpkIdsFromTable('polishfinishedgood', self::POLES_CHROME_DONE_STATUSES, $spkIds);
    }

    /**
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    public static function completedProductionSpkIds(array $spkIds): array
    {
        return collect([
            ...self::completedSpkIdsFromTable('polishfinishedgood', self::POLES_CHROME_DONE_STATUSES, $spkIds),
            ...self::completedSpkIdsFromTable('polishframe', self::POLES_RANGKA_DONE_STATUSES, $spkIds),
            ...self::completedReferencePolesBarangJadiSpkIds($spkIds),
            ...self::completedReferenceFinishingSpkIds($spkIds),
        ])->unique()->values()->all();
    }

    /**
     * Done untuk Exchange / Refund / Reparasi via Poles Barang Jadi (RPFDONE).
     *
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    public static function completedReferencePolesBarangJadiSpkIds(array $spkIds): array
    {
        return self::completedReferenceSpkIdsFromTable(
            'polishfinishedgood',
            self::POLES_BARANG_JADI_REF_DONE_STATUSES,
            $spkIds,
        );
    }

    /**
     * Done untuk Exchange / Refund / Reparasi via Finishing (RFHDONE).
     *
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    public static function completedReferenceFinishingSpkIds(array $spkIds): array
    {
        return self::completedReferenceSpkIdsFromTable(
            'finishinghandmade',
            self::FINISHING_REF_DONE_STATUSES,
            $spkIds,
        );
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    private static function completedReferenceSpkIdsFromTable(string $table, array $statuses, array $spkIds): array
    {
        $spkIds = collect($spkIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (
            $spkIds === []
            || ! Schema::connection('third')->hasTable($table)
            || ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'spk_type')
        ) {
            return [];
        }

        $query = DB::connection('third')
            ->table("{$table} as process")
            ->join('spk', 'spk.row_id', '=', 'process.spk_id')
            ->whereIn('process.spk_id', $spkIds)
            ->whereIn('process.status', $statuses)
            ->whereIn('spk.spk_type', SpkService::REFERENCE_TYPES);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('process.is_deleted')
                    ->orWhere('process.is_deleted', 0);
            });
        }

        return $query
            ->distinct()
            ->pluck('process.spk_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<int|string|null>  $spkIds
     * @return list<int>
     */
    private static function completedSpkIdsFromTable(string $table, array $statuses, array $spkIds): array
    {
        $spkIds = collect($spkIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($spkIds === [] || ! Schema::connection('third')->hasTable($table)) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->whereIn('spk_id', $spkIds)
            ->whereIn('status', $statuses);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        return $query
            ->distinct()
            ->pluck('spk_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  iterable<int, Production|object>  $productions
     * @return array<int, string>
     */
    public static function lastProcessDatesFor(iterable $productions, string $format = 'd-M-Y'): array
    {
        $rows = collect($productions)->map(fn (object $production): object => (object) [
            'row_id' => $production->row_id,
            'last_process' => $production->last_process ?? null,
        ]);

        return (new self)->resolveLastProcessDates($rows, $format);
    }

    /**
     * Aggregate SPK analytics for the current (or given) month.
     *
     * @return array<string, mixed>
     */
    public function summarize(): array
    {
        $spkStats = $this->resolveSpkStats();
        $productionTypes = $this->resolveDistribution('spk_type', 10);
        $itemDistribution = $this->resolveItemDistribution(12);
        $shrink = $this->resolveShrinkAnalytics();
        $gold = $this->resolveGoldAnalytics();
        $stone = $this->resolveStoneAnalytics();
        $control = $this->resolveControlAnalytics($spkStats, $gold);
        $craftsmen = $this->resolveCraftsmanRanking();
        $forecast = $this->resolveProductionForecast();
        $planningDaily = $this->resolvePlanningDaily();
        $today = $this->resolveTodayMetrics();
        $statusLists = $this->resolveStatusLists();
        $todayLists = $this->resolveTodayLists();
        $inProgressByProcess = $this->resolveInProgressByProcess();

        return [
            'period' => [
                'label' => $this->formatPeriodLabel(),
                'start' => $this->periodStart->toDateString(),
                'end' => $this->periodEnd->toDateString(),
            ],
            'backlogYear' => (int) now()->year,
            'summary' => [
                'totalSpk' => $spkStats['total'],
                'draftSpk' => $spkStats['draft'],
                'confirmedSpk' => $spkStats['confirmed'],
                'inProgressSpk' => $spkStats['inProgress'],
                'doneSpk' => $spkStats['done'],
                'overdueSpk' => $spkStats['overdue'],
                'totalShrink' => $shrink['totalShrink'],
                'shrinkOkCount' => $shrink['okCount'],
                'shrinkNokCount' => $shrink['nokCount'],
                'avgLeadTimeDays' => $control['avgLeadTimeDays'],
                'avgYieldPercent' => $control['avgYieldPercent'],
                'goldRequirement' => $spkStats['goldRequirement'],
                'goldUsed' => $gold['used'],
                'stoneDifference' => $stone['difference'],
                'forecastSpk' => $forecast['spkCount'],
                'forecastQty' => $forecast['qtyTotal'],
                'planningDoneSpk' => $planningDaily['doneTotal'],
                'planningPendingSpk' => $planningDaily['pendingTotal'],
                'todayTargetSpk' => $today['targetSpk'],
                'todayTargetDoneSpk' => $today['targetDoneSpk'],
                'todayTargetPendingSpk' => $today['targetPendingSpk'],
                'todayCreatedSpk' => $today['createdSpk'],
                'todayInProcessSpk' => $today['inProcessSpk'],
                'monthOverdueSpk' => $today['overdueSpk'],
            ],
            'today' => $today,
            'statusLists' => $statusLists,
            'todayLists' => $todayLists,
            'productionTypes' => $productionTypes,
            'itemDistribution' => $itemDistribution,
            'inProgressByProcess' => $inProgressByProcess,
            'shrink' => $shrink,
            'control' => $control,
            'craftsmen' => $craftsmen,
            'gold' => $gold,
            'stone' => $stone,
            'forecast' => $forecast,
            'planningDaily' => $planningDaily,
        ];
    }

    /**
     * Aggregate material & yield analytics for the current (or given) month.
     *
     * @return array<string, mixed>
     */
    public function summarizeMaterialYield(): array
    {
        $spkStats = $this->resolveSpkStats();
        $shrink = $this->resolveShrinkAnalytics();
        $gold = $this->resolveGoldAnalytics();
        $stone = $this->resolveStoneAnalytics();
        $control = $this->resolveControlAnalytics($spkStats, $gold);
        $craftsmen = $this->resolveCraftsmanRanking();

        $goldRequirement = $this->nullableFloat($spkStats['goldRequirement']) ?? 0.0;
        $goldUsed = $this->nullableFloat($gold['used']) ?? 0.0;
        $goldVariance = round($goldUsed - $goldRequirement, 3);
        $stoneStart = (float) $stone['startCrt'];
        $stoneDifference = (float) $stone['difference'];
        $stoneLossPercent = abs($stoneStart) >= 0.00005
            ? round(($stoneDifference / $stoneStart) * 100, 2)
            : null;

        return [
            'period' => [
                'label' => $this->formatPeriodLabel(),
                'start' => $this->periodStart->toDateString(),
                'end' => $this->periodEnd->toDateString(),
            ],
            'summary' => [
                'totalSpk' => $spkStats['total'],
                'totalShrink' => $shrink['totalShrink'],
                'shrinkOkCount' => $shrink['okCount'],
                'shrinkNokCount' => $shrink['nokCount'],
                'goldRequirement' => $spkStats['goldRequirement'],
                'goldIssued' => $gold['issued'],
                'goldReturned' => $gold['returned'],
                'goldUsed' => $gold['used'],
                'goldVariance' => $this->formatWeight($goldVariance),
                'stoneStartCrt' => $stone['startCrt'],
                'stoneEndCrt' => $stone['endCrt'],
                'stoneDifference' => $stone['difference'],
                'stoneLossPercent' => $stoneLossPercent !== null
                    ? number_format($stoneLossPercent, 2, '.', '')
                    : null,
                'avgYieldPercent' => $control['avgYieldPercent'],
                'avgGoldYieldPercent' => $control['avgGoldYieldPercent'],
            ],
            'shrink' => $shrink,
            'gold' => $gold,
            'stone' => $stone,
            'control' => $control,
            'craftsmen' => $craftsmen,
        ];
    }

    /**
     * Aggregate shop-floor / process health analytics.
     *
     * WIP & aging memakai backlog tahun berjalan (posisi lantai saat ini).
     * Throughput selesai memakai scope bulan terpilih.
     *
     * @return array<string, mixed>
     */
    public function summarizeShopFloor(): array
    {
        $wipByProcess = $this->resolveShopFloorWipByProcess();
        $aging = $this->resolveShopFloorAging();
        $craftsmen = $this->resolveCraftsmanRanking();
        $completedThisMonth = $this->resolveShopFloorCompletedThisMonth();
        $overdueWip = $this->resolveShopFloorOverdueWipCount();

        $wipTotal = (int) collect($wipByProcess)->sum('count');
        $bottleneck = $wipByProcess[0] ?? null;

        return [
            'period' => [
                'label' => $this->formatPeriodLabel(),
                'start' => $this->periodStart->toDateString(),
                'end' => $this->periodEnd->toDateString(),
            ],
            'backlogYear' => (int) now()->year,
            'summary' => [
                'wipSpk' => $wipTotal,
                'overdueWipSpk' => $overdueWip,
                'completedThisMonth' => $completedThisMonth,
                'bottleneckProcess' => $bottleneck['label'] ?? null,
                'bottleneckCount' => $bottleneck['count'] ?? 0,
                'avgAgeDays' => $aging['avgAgeDays'],
                'agedOver7Days' => $aging['agedOver7Days'],
                'activeProcesses' => count($wipByProcess),
            ],
            'wipByProcess' => $wipByProcess,
            'agingBuckets' => $aging['buckets'],
            'agingByProcess' => $aging['byProcess'],
            'craftsmen' => $craftsmen,
        ];
    }

    /**
     * Aggregate craftsman performance analytics for the current (or given) month.
     *
     * @return array<string, mixed>
     */
    public function summarizeCraftsmanPerformance(): array
    {
        $ranking = $this->resolveCraftsmanPerformanceRanking(20);
        $byProcess = $this->resolveCraftsmanJobsByProcess();
        $totalJobs = (int) collect($ranking)->sum('jobCount');
        $totalShrink = round((float) collect($ranking)->sum(
            fn (array $row): float => (float) $row['totalShrink'],
        ), 3);
        $top = $ranking[0] ?? null;
        $heaviestShrink = collect($ranking)
            ->sortByDesc(fn (array $row): float => (float) $row['totalShrink'])
            ->values()
            ->first();

        return [
            'period' => [
                'label' => $this->formatPeriodLabel(),
                'start' => $this->periodStart->toDateString(),
                'end' => $this->periodEnd->toDateString(),
            ],
            'summary' => [
                'activeCraftsmen' => count($ranking),
                'totalJobs' => $totalJobs,
                'totalShrink' => $this->formatWeight($totalShrink),
                'avgJobsPerCraftsman' => count($ranking) > 0
                    ? number_format($totalJobs / count($ranking), 1, '.', '')
                    : null,
                'topCraftsman' => $top['name'] ?? null,
                'topCraftsmanJobs' => $top['jobCount'] ?? 0,
                'heaviestShrinkCraftsman' => $heaviestShrink['name'] ?? null,
                'heaviestShrink' => $heaviestShrink['totalShrink'] ?? '0.000',
            ],
            'ranking' => $ranking,
            'byProcess' => $byProcess,
        ];
    }

    /**
     * Aggregate SKU / item output analytics for the current (or given) month.
     *
     * @return array<string, mixed>
     */
    public function summarizeSkuOutput(): array
    {
        $bySku = $this->resolveSkuOutputRows(20);
        $byItem = $this->resolveItemOutputRows(12);
        $spkCount = (int) collect($bySku)->sum('spkCount');
        $qtyTotal = (int) collect($bySku)->sum('qty');
        $doneSpk = (int) collect($bySku)->sum('doneSpk');
        $doneQty = (int) collect($bySku)->sum('doneQty');
        $uniqueSku = count($bySku);

        // If sku grouping returned empty (no sku column), fall back to item totals.
        if ($uniqueSku === 0 && $byItem !== []) {
            $spkCount = (int) collect($byItem)->sum('spkCount');
            $qtyTotal = (int) collect($byItem)->sum('qty');
            $doneSpk = (int) collect($byItem)->sum('doneSpk');
            $doneQty = (int) collect($byItem)->sum('doneQty');
            $uniqueSku = count($byItem);
        }

        $completionPercent = $spkCount > 0
            ? number_format(($doneSpk / $spkCount) * 100, 1, '.', '')
            : null;

        return [
            'period' => [
                'label' => $this->formatPeriodLabel(),
                'start' => $this->periodStart->toDateString(),
                'end' => $this->periodEnd->toDateString(),
            ],
            'summary' => [
                'totalSpk' => $spkCount,
                'totalQty' => $qtyTotal,
                'doneSpk' => $doneSpk,
                'doneQty' => $doneQty,
                'uniqueSku' => $uniqueSku,
                'completionPercent' => $completionPercent,
            ],
            'bySku' => $bySku,
            'byItem' => $byItem,
        ];
    }

    /**
     * Snapshot operasional untuk hari kalender berjalan,
     * terbatas pada SPK scope bulan (dibuat ATAU estimasi delivery).
     *
     * @return array{
     *     date: string,
     *     label: string,
     *     targetSpk: int,
     *     targetDoneSpk: int,
     *     targetPendingSpk: int,
     *     targetQty: int,
     *     createdSpk: int,
     *     inProcessSpk: int,
     *     overdueSpk: int
     * }
     */
    private function resolveTodayMetrics(): array
    {
        $today = now();
        $dayStart = $today->copy()->startOfDay();
        $dayEnd = $today->copy()->endOfDay();

        $targetSpk = 0;
        $targetDoneSpk = 0;
        $targetPendingSpk = 0;
        $targetQty = 0;
        $createdSpk = 0;
        $inProcessSpk = 0;
        $overdueSpk = 0;

        if (! Schema::connection('third')->hasTable('spk')) {
            return [
                'date' => $dayStart->toDateString(),
                'label' => $this->formatDayLabel($dayStart),
                'targetSpk' => 0,
                'targetDoneSpk' => 0,
                'targetPendingSpk' => 0,
                'targetQty' => 0,
                'createdSpk' => 0,
                'inProcessSpk' => 0,
                'overdueSpk' => 0,
            ];
        }

        if (Schema::connection('third')->hasColumn('spk', 'estimated_delivery_time')) {
            $doneExpr = $this->doneExpression();
            $target = $this->monthScopedSpkBase()
                ->whereNotNull('estimated_delivery_time')
                ->whereBetween('estimated_delivery_time', [
                    $dayStart->toDateTimeString(),
                    $dayEnd->toDateTimeString(),
                ])
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as done_count,
                    SUM(CASE WHEN ({$doneExpr}) THEN 0 ELSE 1 END) as pending_count,
                    SUM(COALESCE(qty, 1)) as qty_total
                ")
                ->first();

            $targetSpk = (int) ($target->total_count ?? 0);
            $targetDoneSpk = (int) ($target->done_count ?? 0);
            $targetPendingSpk = (int) ($target->pending_count ?? 0);
            $targetQty = (int) round((float) ($target->qty_total ?? 0));
        }

        if (Schema::connection('third')->hasColumn('spk', 'created_date')) {
            $createdSpk = $this->monthScopedSpkBase()
                ->whereNotNull('created_date')
                ->whereBetween('created_date', [
                    $dayStart->toDateTimeString(),
                    $dayEnd->toDateTimeString(),
                ])
                ->count();
        }

        $inProcessSpk = $this->applyTodayInProcessFilter(
            $this->monthScopedSpkBase(),
            $dayStart,
            $dayEnd,
        )->count();

        $overdueSpk = $this->resolveOverdueCount($this->monthScopedSpkBase());

        return [
            'date' => $dayStart->toDateString(),
            'label' => $this->formatDayLabel($dayStart),
            'targetSpk' => $targetSpk,
            'targetDoneSpk' => $targetDoneSpk,
            'targetPendingSpk' => $targetPendingSpk,
            'targetQty' => $targetQty,
            'createdSpk' => $createdSpk,
            'inProcessSpk' => $inProcessSpk,
            'overdueSpk' => $overdueSpk,
        ];
    }

    private function formatDayLabel(CarbonInterface $day): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $day->day.' '.($months[$day->month] ?? $day->format('F')).' '.$day->year;
    }

    /**
     * @return array{
     *     total: int,
     *     draft: int,
     *     confirmed: int,
     *     inProgress: int,
     *     done: int,
     *     overdue: int,
     *     goldRequirement: string,
     *     avgLeadDays: float|null,
     *     avgEstimatedDays: float|null,
     *     avgYieldPercent: float|null
     * }
     */
    private function resolveSpkStats(): array
    {
        if (! Schema::connection('third')->hasTable('spk')) {
            return [
                'total' => 0,
                'draft' => 0,
                'confirmed' => 0,
                'inProgress' => 0,
                'done' => 0,
                'overdue' => 0,
                'goldRequirement' => '0.000',
                'avgLeadDays' => null,
                'avgEstimatedDays' => null,
                'avgYieldPercent' => null,
            ];
        }

        // Backlog status: tahun berjalan (created_date).
        $backlogBase = $this->yearScopedSpkBase();
        $statusCounts = $this->resolveStatusCounts(clone $backlogBase);
        $overdue = $this->resolveOverdueCount(clone $backlogBase);

        // Metrik bulan: dibuat ATAU estimasi delivery di periode terpilih.
        $monthScoped = $this->monthScopedSpkBase();
        $total = (clone $monthScoped)->count();

        $goldRequirement = 0.0;

        if (Schema::connection('third')->hasColumn('spk', 'gold_weight')) {
            $goldRequirement = round(
                (float) (clone $monthScoped)
                    ->whereNotNull('gold_weight')
                    ->sum('gold_weight'),
                3,
            );
        }

        $avgEstimatedDays = $this->nullableFloat(
            (clone $monthScoped)
                ->whereNotNull('work_estimated')
                ->where('work_estimated', '>', 0)
                ->avg('work_estimated'),
        );

        $avgLeadDays = null;

        if (
            Schema::connection('third')->hasColumn('spk', 'created_date')
            && Schema::connection('third')->hasColumn('spk', 'modified_date')
        ) {
            $avgLeadDays = $this->nullableFloat(
                (clone $monthScoped)
                    ->where('status', 'SPKDONE')
                    ->whereNotNull('created_date')
                    ->whereNotNull('modified_date')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_date, modified_date) / 24) as avg_days')
                    ->value('avg_days'),
            );
        }

        $avgYieldPercent = null;

        if (
            Schema::connection('third')->hasColumn('spk', 'gold_weight')
            && Schema::connection('third')->hasColumn('spk', 'last_weight')
        ) {
            $avgYieldPercent = $this->nullableFloat(
                (clone $monthScoped)
                    ->where('status', 'SPKDONE')
                    ->whereNotNull('gold_weight')
                    ->where('gold_weight', '>', 0)
                    ->whereNotNull('last_weight')
                    ->where('last_weight', '>', 0)
                    ->selectRaw('AVG((last_weight / gold_weight) * 100) as avg_yield')
                    ->value('avg_yield'),
            );
        }

        return [
            'total' => $total,
            'draft' => $statusCounts['draft'],
            'confirmed' => $statusCounts['confirmed'],
            'inProgress' => $statusCounts['inProgress'],
            'done' => $statusCounts['done'],
            'overdue' => $overdue,
            'goldRequirement' => $this->formatWeight($goldRequirement),
            'avgLeadDays' => $avgLeadDays,
            'avgEstimatedDays' => $avgEstimatedDays,
            'avgYieldPercent' => $avgYieldPercent,
        ];
    }

    /**
     * Overdue: Approved atau In Progress yang estimasi delivery-nya sudah lewat.
     */
    private function resolveOverdueCount(Builder $query): int
    {
        if (! Schema::connection('third')->hasColumn('spk', 'estimated_delivery_time')) {
            return 0;
        }

        return $this->applyOverdueFilter(clone $query)->count();
    }

    /**
     * Scope SPK overdue: subset Approved + In Progress dengan estimasi lewat hari ini.
     */
    private function applyOverdueFilter(Builder $query): Builder
    {
        [
            'inProgress' => $inProgressExpr,
            'confirmed' => $confirmedExpr,
            'managerApproved' => $managerApprovedExpr,
        ] = $this->statusExpressions();
        $doneExpr = $this->doneExpression();

        return $query
            ->whereRaw("NOT ({$doneExpr})")
            ->where(function (Builder $builder) use ($confirmedExpr, $managerApprovedExpr, $inProgressExpr): void {
                $builder->where(function (Builder $approved) use ($confirmedExpr, $managerApprovedExpr): void {
                    $approved->whereRaw("({$confirmedExpr})")
                        ->whereRaw("({$managerApprovedExpr})");
                })->orWhere(function (Builder $inProgress) use ($confirmedExpr, $inProgressExpr): void {
                    $inProgress->whereRaw("NOT ({$confirmedExpr})")
                        ->whereRaw("({$inProgressExpr})");
                });
            })
            ->whereNotNull('estimated_delivery_time')
            ->where('estimated_delivery_time', '<', now()->startOfDay()->toDateTimeString());
    }

    /**
     * Classify SPK into waiting-approval (sudah dikirim ke produksi) / Confirmed / In Progress / Done.
     * Draft yang belum dikirim ke produksi tidak dihitung di waiting-approval.
     *
     * @return array{draft: int, confirmed: int, inProgress: int, done: int}
     */
    private function resolveStatusCounts(Builder $query): array
    {
        [
            'inProgress' => $inProgressExpr,
            'confirmed' => $confirmedExpr,
            'managerApproved' => $managerApprovedExpr,
        ] = $this->statusExpressions();
        $doneExpr = $this->doneExpression();

        $counts = $query
            ->selectRaw("
                SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as done_count,
                SUM(CASE
                    WHEN ({$doneExpr}) THEN 0
                    WHEN ({$confirmedExpr}) AND ({$managerApprovedExpr}) THEN 1
                    ELSE 0
                END) as confirmed_count,
                SUM(CASE
                    WHEN ({$doneExpr}) THEN 0
                    WHEN ({$confirmedExpr}) THEN 0
                    WHEN ({$inProgressExpr}) THEN 1
                    ELSE 0
                END) as in_progress_count,
                SUM(CASE
                    WHEN ({$doneExpr}) THEN 0
                    WHEN ({$confirmedExpr}) AND NOT ({$managerApprovedExpr}) THEN 1
                    ELSE 0
                END) as draft_count
            ")
            ->first();

        return [
            'draft' => (int) ($counts->draft_count ?? 0),
            'confirmed' => (int) ($counts->confirmed_count ?? 0),
            'inProgress' => (int) ($counts->in_progress_count ?? 0),
            'done' => (int) ($counts->done_count ?? 0),
        ];
    }

    /**
     * @return array{
     *     draft: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     confirmed: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     inProgress: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     overdue: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     done: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>
     * }
     */
    private function resolveStatusLists(): array
    {
        $empty = [
            'draft' => [],
            'confirmed' => [],
            'inProgress' => [],
            'overdue' => [],
            'done' => [],
        ];

        if (! Schema::connection('third')->hasTable('spk')) {
            return $empty;
        }

        return [
            'draft' => $this->statusListFor('draft'),
            'confirmed' => $this->statusListFor('confirmed'),
            'inProgress' => $this->statusListFor('inProgress'),
            'overdue' => $this->statusListFor('overdue'),
            'done' => $this->statusListFor('done'),
        ];
    }

    /**
     * @return list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>
     */
    private function statusListFor(string $status): array
    {
        [
            'inProgress' => $inProgressExpr,
            'confirmed' => $confirmedExpr,
            'managerApproved' => $managerApprovedExpr,
        ] = $this->statusExpressions();

        $query = $this->yearScopedSpkBase();

        $query = match ($status) {
            'done' => $query->whereRaw($this->doneExpression()),
            'overdue' => $this->applyOverdueFilter($query),
            'confirmed' => $query
                ->whereRaw('NOT ('.$this->doneExpression().')')
                ->whereRaw("({$confirmedExpr})")
                ->whereRaw("({$managerApprovedExpr})"),
            'inProgress' => $query
                ->whereRaw('NOT ('.$this->doneExpression().')')
                ->whereRaw("NOT ({$confirmedExpr})")
                ->whereRaw("({$inProgressExpr})"),
            default => $query
                ->whereRaw('NOT ('.$this->doneExpression().')')
                ->whereRaw("({$confirmedExpr})")
                ->whereRaw("NOT ({$managerApprovedExpr})"),
        };

        $rows = $query
            ->orderByDesc('row_id')
            ->limit(200)
            ->get($this->spkListSelectColumns());

        return $this->mapSpkListRows($rows);
    }

    /**
     * @return array{
     *     todayCreated: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     todayInProcess: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     todayTarget: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     monthTarget: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>,
     *     monthOverdue: list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>
     * }
     */
    private function resolveTodayLists(): array
    {
        $empty = [
            'todayCreated' => [],
            'todayInProcess' => [],
            'todayTarget' => [],
            'monthTarget' => [],
            'monthOverdue' => [],
        ];

        if (! Schema::connection('third')->hasTable('spk')) {
            return $empty;
        }

        return [
            'todayCreated' => $this->todayListFor('todayCreated'),
            'todayInProcess' => $this->todayListFor('todayInProcess'),
            'todayTarget' => $this->todayListFor('todayTarget'),
            'monthTarget' => $this->todayListFor('monthTarget'),
            'monthOverdue' => $this->todayListFor('monthOverdue'),
        ];
    }

    /**
     * @return list<array{spkNo: string, type: string, customer: string, item: string, orderDate: string|null, estimatedDelivery: string|null, status: string, lastProcess: string|null, lastProcessDate: string|null}>
     */
    private function todayListFor(string $key): array
    {
        $dayStart = now()->startOfDay();
        $dayEnd = now()->endOfDay();
        $query = $this->monthScopedSpkBase();

        $query = match ($key) {
            'monthTarget' => $this->spkBase()
                ->whereNotNull('estimated_delivery_time')
                ->whereBetween('estimated_delivery_time', [
                    $this->periodStart->toDateTimeString(),
                    $this->periodEnd->toDateTimeString(),
                ]),
            'todayTarget' => $query
                ->whereNotNull('estimated_delivery_time')
                ->whereBetween('estimated_delivery_time', [
                    $dayStart->toDateTimeString(),
                    $dayEnd->toDateTimeString(),
                ]),
            'todayCreated' => Schema::connection('third')->hasColumn('spk', 'created_date')
                ? $query
                    ->whereNotNull('created_date')
                    ->whereBetween('created_date', [
                        $dayStart->toDateTimeString(),
                        $dayEnd->toDateTimeString(),
                    ])
                : $query->whereRaw('0'),
            'monthOverdue' => $this->applyOverdueFilter($query),
            default => $this->applyTodayInProcessFilter($query, $dayStart, $dayEnd),
        };

        $rows = $query
            ->orderByDesc('row_id')
            ->limit(200)
            ->get($this->spkListSelectColumns());

        return $this->mapSpkListRows($rows);
    }

    private function applyTodayInProcessFilter(
        Builder $query,
        CarbonInterface $dayStart,
        CarbonInterface $dayEnd,
    ): Builder {
        ['inProgress' => $inProgressExpr, 'confirmed' => $confirmedExpr] = $this->statusExpressions();

        $query
            ->whereRaw('NOT ('.$this->doneExpression().')')
            ->whereRaw("NOT ({$confirmedExpr})")
            ->whereRaw("({$inProgressExpr})");

        if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
            $query
                ->whereNotNull('modified_date')
                ->whereBetween('modified_date', [
                    $dayStart->toDateTimeString(),
                    $dayEnd->toDateTimeString(),
                ]);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function spkListSelectColumns(): array
    {
        $columns = [
            'row_id',
            'spk_no',
            'spk_type',
            'customer_name',
            'item_name',
            'order_date',
            'estimated_delivery_time',
            'last_process',
        ];

        if (Schema::connection('third')->hasColumn('spk', 'status')) {
            $columns[] = 'status';
        }

        if (Schema::connection('third')->hasColumn('spk', 'is_inprocess')) {
            $columns[] = 'is_inprocess';
        }

        if (Schema::connection('third')->hasColumn('spk', 'request_order_no')) {
            $columns[] = 'request_order_no';
        }

        if (Schema::connection('third')->hasColumn('spk', 'created_date')) {
            $columns[] = 'created_date';
        }

        if (Schema::connection('third')->hasColumn('spk', 'description')) {
            $columns[] = 'description';
        }

        if (Schema::connection('third')->hasColumn('spk', 'sku_id')) {
            $columns[] = 'sku_id';
        }

        if (Schema::connection('third')->hasColumn('spk', 'category_prefix_id')) {
            $columns[] = 'category_prefix_id';
        }

        return $columns;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{
     *     spkNo: string,
     *     type: string,
     *     customer: string,
     *     item: string,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     skuAssigned: bool,
     *     description: string,
     *     createdDate: string|null,
     *     orderDate: string|null,
     *     estimatedDelivery: string|null,
     *     status: string,
     *     lastProcess: string|null,
     *     lastProcessDate: string|null
     * }>
     */
    private function mapSpkListRows($rows): array
    {
        $lastProcessDates = $this->resolveLastProcessDates($rows, 'd-M-Y');
        $orderTypeLabels = $this->resolveOrderTypeLabels($rows);
        $typeSkuMeta = $this->resolveTypeSkuMeta($rows);
        $doneIds = array_flip(self::completedProductionSpkIds(
            $rows
                ->map(fn (object $row): int => (int) ($row->row_id ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all(),
        ));

        return $rows->map(function (object $row) use ($lastProcessDates, $orderTypeLabels, $typeSkuMeta, $doneIds): array {
            $spkId = (int) ($row->row_id ?? 0);
            $production = new Production([
                'status' => (string) ($row->status ?? ''),
                'is_inprocess' => (int) ($row->is_inprocess ?? 0),
                'last_process' => $row->last_process ?? null,
            ]);
            $production->row_id = $spkId;

            $meta = $typeSkuMeta[$spkId] ?? [
                'typeSkuLabel' => null,
                'itemDescription' => null,
                'skuAssigned' => false,
                'description' => '-',
            ];

            return [
                'spkNo' => filled($row->spk_no ?? null) ? (string) $row->spk_no : '-',
                'type' => filled($row->spk_type ?? null) ? (string) $row->spk_type : '-',
                'customer' => $orderTypeLabels[$spkId] ?? '—',
                'item' => filled($row->item_name ?? null) ? (string) $row->item_name : '-',
                'typeSkuLabel' => $meta['typeSkuLabel'],
                'itemDescription' => $meta['itemDescription'],
                'skuAssigned' => $meta['skuAssigned'],
                'description' => $meta['description'],
                'createdDate' => filled($row->created_date ?? null)
                    ? Carbon::parse((string) $row->created_date)->format('d-M-Y')
                    : null,
                'orderDate' => filled($row->order_date ?? null)
                    ? Carbon::parse((string) $row->order_date)->format('d-M-Y')
                    : null,
                'estimatedDelivery' => filled($row->estimated_delivery_time ?? null)
                    ? Carbon::parse((string) $row->estimated_delivery_time)->format('d-M-Y')
                    : null,
                'status' => self::backlogStatusLabel($production, isset($doneIds[$spkId])),
                'lastProcess' => filled($row->last_process ?? null)
                    ? (string) $row->last_process
                    : null,
                'lastProcessDate' => $lastProcessDates[$spkId] ?? null,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, description: string}>
     */
    private function resolveTypeSkuMeta(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $skuIds = $rows
            ->map(fn (object $row): int => (int) ($row->sku_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $prefixIds = $rows
            ->map(fn (object $row): int => (int) ($row->category_prefix_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $skus = $skuIds === []
            ? collect()
            : SkuMaster::query()
                ->whereIn('id', $skuIds)
                ->get(['id', 'sku_code'])
                ->keyBy('id');

        $prefixes = $prefixIds === []
            ? collect()
            : SkuPrefixCategory::query()
                ->whereIn('id', $prefixIds)
                ->get(['id', 'prefix'])
                ->keyBy('id');

        /** @var array<int, array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, description: string}> $meta */
        $meta = [];

        foreach ($rows as $row) {
            $spkId = (int) ($row->row_id ?? 0);

            if ($spkId <= 0) {
                continue;
            }

            $skuId = (int) ($row->sku_id ?? 0);
            $sku = $skuId > 0 ? $skus->get($skuId) : null;
            $skuAssigned = $sku !== null;

            $typeSkuLabel = null;

            if ($skuAssigned) {
                $prefixId = (int) ($row->category_prefix_id ?? 0);
                $typeCode = trim((string) ($prefixes->get($prefixId)?->prefix ?? ''));
                $skuCode = trim((string) ($sku->sku_code ?? ''));
                $parts = array_values(array_filter(
                    [$typeCode, $skuCode],
                    fn (string $part): bool => $part !== '',
                ));
                $typeSkuLabel = $parts !== [] ? implode(' | ', $parts) : null;
            }

            $itemDescription = null;

            if (filled($row->description ?? null)) {
                $description = trim((string) $row->description);
                $itemDescription = $description !== '' ? $description : null;
            }

            $searchParts = array_values(array_filter([
                $typeSkuLabel,
                $itemDescription,
                $skuAssigned ? null : 'belum assign SKU',
            ], fn (?string $part): bool => filled($part)));

            $meta[$spkId] = [
                'typeSkuLabel' => $typeSkuLabel,
                'itemDescription' => $itemDescription,
                'skuAssigned' => $skuAssigned,
                'description' => $searchParts !== [] ? implode(' ', $searchParts) : '-',
            ];
        }

        return $meta;
    }

    /**
     * Label sekunder kolom Tipe Produksi (no request/nama), sama seperti list SPK.
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, string>
     */
    private function resolveOrderTypeLabels(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $requestOrders = app(RequestOrderRepository::class);
        $requestRows = $requestOrders->rowsByDocNos(
            $rows
                ->filter(fn (object $row): bool => ($row->spk_type ?? null) === 'Pesanan'
                    && filled($row->request_order_no ?? null))
                ->map(fn (object $row): string => (string) $row->request_order_no)
                ->unique()
                ->values()
                ->all(),
        );

        /** @var array<int, string> $labels */
        $labels = [];

        foreach ($rows as $row) {
            $spkId = (int) ($row->row_id ?? 0);

            if ($spkId <= 0) {
                continue;
            }

            $customerName = filled($row->customer_name ?? null)
                ? (string) $row->customer_name
                : '';

            if (($row->spk_type ?? null) !== 'Pesanan') {
                $labels[$spkId] = $customerName !== '' ? $customerName : '—';

                continue;
            }

            $orderNo = filled($row->request_order_no ?? null)
                ? (string) $row->request_order_no
                : '';

            if ($orderNo === '') {
                $labels[$spkId] = $customerName !== '' ? $customerName : '—';

                continue;
            }

            $requestRow = $requestRows[$orderNo] ?? null;

            if ($requestRow === null) {
                $labels[$spkId] = $requestOrders->pesananDisplayLabel(
                    $orderNo,
                    $customerName !== '' ? $customerName : '-',
                );

                continue;
            }

            $labels[$spkId] = $requestOrders->pesananDisplayLabel(
                (string) $requestRow->doc_no,
                $customerName !== ''
                    ? $customerName
                    : (filled($requestRow->customer_name) ? (string) $requestRow->customer_name : '-'),
                $requestRow->is_fully_paid,
            );
        }

        return $labels;
    }

    /**
     * Ambil tanggal proses terakhir dari tabel proses yang sesuai last_process (bukan modified_date SPK).
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, string>
     */
    private function resolveLastProcessDates(Collection $rows, string $format = 'd-M-Y H:i'): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $mapper = new SpkProcessMapper;
        /** @var array<int, string> $datesBySpkId */
        $datesBySpkId = [];

        $groups = $rows
            ->filter(fn (object $row): bool => filled($row->last_process ?? null) && filled($row->row_id ?? null))
            ->groupBy(fn (object $row): string => trim((string) $row->last_process));

        foreach ($groups as $lastProcess => $groupRows) {
            $tables = $mapper->tablesForLastProcess((string) $lastProcess);

            if ($tables === []) {
                continue;
            }

            /** @var list<int> $spkIds */
            $spkIds = $groupRows
                ->map(fn (object $row): int => (int) $row->row_id)
                ->unique()
                ->values()
                ->all();

            foreach ($tables as $table) {
                foreach ($this->fetchLatestProcessDates($table, $spkIds) as $spkId => $processDate) {
                    $existing = $datesBySpkId[$spkId] ?? null;

                    if ($existing === null || Carbon::parse($processDate)->gt(Carbon::parse($existing))) {
                        $datesBySpkId[$spkId] = (string) $processDate;
                    }
                }
            }
        }

        return collect($datesBySpkId)
            ->map(fn (string $date): string => Carbon::parse($date)->format($format))
            ->all();
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, string>
     */
    private function fetchLatestProcessDates(string $table, array $spkIds): array
    {
        if (
            $spkIds === []
            || ! Schema::connection('third')->hasTable($table)
            || ! Schema::connection('third')->hasColumn($table, 'spk_id')
        ) {
            return [];
        }

        $dateExpression = $this->processDateExpression($table);

        if ($dateExpression === null) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->whereIn('spk_id', $spkIds)
            ->selectRaw("spk_id, MAX({$dateExpression}) as process_date")
            ->groupBy('spk_id');

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        return $query
            ->pluck('process_date', 'spk_id')
            ->filter(fn (mixed $date): bool => filled($date))
            ->map(fn (mixed $date): string => (string) $date)
            ->all();
    }

    private function processDateExpression(string $table): ?string
    {
        $candidates = [
            'send_craftsman_date',
            'date_to',
            'trans_date',
            'received_craftsman_date',
            'created_date',
        ];

        $columns = array_values(array_filter(
            $candidates,
            fn (string $column): bool => Schema::connection('third')->hasColumn($table, $column),
        ));

        if ($columns === []) {
            return null;
        }

        if (count($columns) === 1) {
            return $columns[0];
        }

        return 'COALESCE('.implode(', ', $columns).')';
    }

    /**
     * Done: Poles Chrome (PFGDONE/PFG040), Poles Rangka (PRKDONE/PRK040),
     * or RPFDONE / RFHDONE for Exchange/Refund/Reparasi.
     */
    private function doneExpression(): string
    {
        $expressions = [];
        $refTypes = collect(SpkService::REFERENCE_TYPES)
            ->map(fn (string $type): string => "'{$type}'")
            ->implode(', ');

        if (Schema::connection('third')->hasTable('polishfinishedgood')) {
            $statuses = collect(self::POLES_CHROME_DONE_STATUSES)
                ->map(fn (string $status): string => "'{$status}'")
                ->implode(', ');

            $deleted = Schema::connection('third')->hasColumn('polishfinishedgood', 'is_deleted')
                ? 'AND COALESCE(pfg.is_deleted, 0) = 0'
                : '';

            $expressions[] = "EXISTS (
                SELECT 1
                FROM polishfinishedgood pfg
                WHERE pfg.spk_id = spk.row_id
                  AND pfg.status IN ({$statuses})
                  {$deleted}
            )";

            $refStatuses = collect(self::POLES_BARANG_JADI_REF_DONE_STATUSES)
                ->map(fn (string $status): string => "'{$status}'")
                ->implode(', ');

            $expressions[] = "EXISTS (
                SELECT 1
                FROM polishfinishedgood pfg
                WHERE pfg.spk_id = spk.row_id
                  AND pfg.status IN ({$refStatuses})
                  AND spk.spk_type IN ({$refTypes})
                  {$deleted}
            )";
        }

        if (Schema::connection('third')->hasTable('finishinghandmade')) {
            $refStatuses = collect(self::FINISHING_REF_DONE_STATUSES)
                ->map(fn (string $status): string => "'{$status}'")
                ->implode(', ');

            $deleted = Schema::connection('third')->hasColumn('finishinghandmade', 'is_deleted')
                ? 'AND COALESCE(fhm.is_deleted, 0) = 0'
                : '';

            $expressions[] = "EXISTS (
                SELECT 1
                FROM finishinghandmade fhm
                WHERE fhm.spk_id = spk.row_id
                  AND fhm.status IN ({$refStatuses})
                  AND spk.spk_type IN ({$refTypes})
                  {$deleted}
            )";
        }

        if (Schema::connection('third')->hasTable('polishframe')) {
            $statuses = collect(self::POLES_RANGKA_DONE_STATUSES)
                ->map(fn (string $status): string => "'{$status}'")
                ->implode(', ');

            $deleted = Schema::connection('third')->hasColumn('polishframe', 'is_deleted')
                ? 'AND COALESCE(pf.is_deleted, 0) = 0'
                : '';

            $expressions[] = "EXISTS (
                SELECT 1
                FROM polishframe pf
                WHERE pf.spk_id = spk.row_id
                  AND pf.status IN ({$statuses})
                  {$deleted}
            )";
        }

        if ($expressions === []) {
            return '0';
        }

        return '('.implode(' OR ', $expressions).')';
    }

    /**
     * @return array{inProgress: string, confirmed: string, unsentDraft: string, managerApproved: string}
     */
    private function statusExpressions(): array
    {
        $hasLastProcess = Schema::connection('third')->hasColumn('spk', 'last_process');
        $hasInProcess = Schema::connection('third')->hasColumn('spk', 'is_inprocess');
        $hasStatus = Schema::connection('third')->hasColumn('spk', 'status');

        $confirmedParts = [];

        if ($hasStatus) {
            $approved = SpkApprovalService::STATUS_DONE;
            $confirmedParts[] = "UPPER(TRIM(COALESCE(status, ''))) = '{$approved}'";
        }

        if ($hasInProcess) {
            $confirmedParts[] = 'COALESCE(is_inprocess, 0) = 0';
        }

        if ($hasLastProcess) {
            $confirmedParts[] = 'last_process IS NULL';
        }

        $confirmed = ($hasStatus && $confirmedParts !== [])
            ? implode(' AND ', $confirmedParts)
            : '0';

        // In Progress: sudah ada jejak proses atau flag in-process aktif.
        $inProgressParts = [];

        if ($hasLastProcess) {
            $inProgressParts[] = 'last_process IS NOT NULL';
        }

        if ($hasInProcess) {
            $inProgressParts[] = 'COALESCE(is_inprocess, 0) != 0';
        }

        $inProgress = $inProgressParts !== []
            ? implode(' OR ', $inProgressParts)
            : '0';

        $unsentDraft = $hasStatus
            ? "UPPER(TRIM(COALESCE(status, ''))) IN ('', '0', 'DRAFT')"
            : '0';

        return [
            'inProgress' => $inProgress,
            'confirmed' => $confirmed,
            'unsentDraft' => $unsentDraft,
            'managerApproved' => $this->managerApprovedExpression(),
        ];
    }

    private function managerApprovedExpression(): string
    {
        if (
            ! Schema::connection('third')->hasTable('sysapproval')
            || ! Schema::connection('third')->hasColumn('sysapproval', 'doc_id')
            || ! Schema::connection('third')->hasColumn('sysapproval', 'doc_name')
            || ! Schema::connection('third')->hasColumn('sysapproval', 'approve')
            || ! Schema::connection('third')->hasColumn('sysapproval', 'status')
        ) {
            return '0';
        }

        $ok = SpkApprovalService::APPROVE_OK;
        $done = SpkApprovalService::STATUS_DONE;
        $docName = SpkApprovalService::DOC_NAME;

        $deleted = '';

        if (Schema::connection('third')->hasColumn('sysapproval', 'is_deleted')) {
            $deleted = 'AND (a.is_deleted IS NULL OR a.is_deleted = 0)';
        }

        return "EXISTS (
            SELECT 1
            FROM sysapproval a
            WHERE a.doc_name = '{$docName}'
                AND a.doc_id = spk.row_id
                AND UPPER(TRIM(COALESCE(a.approve, ''))) = '{$ok}'
                AND UPPER(TRIM(COALESCE(a.status, ''))) = '{$done}'
                {$deleted}
        )";
    }

    /**
     * Planning produksi harian (berdasarkan estimated delivery di bulan terpilih,
     * dalam scope dibuat ATAU estimasi delivery di periode):
     * bandingkan SPK yang sudah selesai vs belum selesai.
     *
     * @return array{
     *     doneTotal: int,
     *     pendingTotal: int,
     *     days: list<array{date: string, label: string, done: int, pending: int}>
     * }
     */
    private function resolvePlanningDaily(): array
    {
        $emptyDays = $this->buildEmptyPlanningDays();

        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'estimated_delivery_time')
        ) {
            return [
                'doneTotal' => 0,
                'pendingTotal' => 0,
                'days' => $emptyDays,
            ];
        }

        $doneExpr = $this->doneExpression();
        $rows = $this->monthScopedSpkBase()
            ->whereNotNull('estimated_delivery_time')
            ->whereBetween('estimated_delivery_time', [
                $this->periodStart->toDateTimeString(),
                $this->periodEnd->toDateTimeString(),
            ])
            ->selectRaw("
                DATE(estimated_delivery_time) as planning_date,
                SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN ({$doneExpr}) THEN 0 ELSE 1 END) as pending_count
            ")
            ->groupByRaw('DATE(estimated_delivery_time)')
            ->orderBy('planning_date')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->planning_date);

        $days = [];
        $doneTotal = 0;
        $pendingTotal = 0;

        foreach ($emptyDays as $day) {
            $row = $rows->get($day['date']);
            $done = (int) ($row->done_count ?? 0);
            $pending = (int) ($row->pending_count ?? 0);
            $doneTotal += $done;
            $pendingTotal += $pending;

            $days[] = [
                'date' => $day['date'],
                'label' => $day['label'],
                'done' => $done,
                'pending' => $pending,
            ];
        }

        return [
            'doneTotal' => $doneTotal,
            'pendingTotal' => $pendingTotal,
            'days' => $days,
        ];
    }

    /**
     * @return list<array{date: string, label: string, done: int, pending: int}>
     */
    private function buildEmptyPlanningDays(): array
    {
        $days = [];
        $cursor = $this->periodStart->copy()->startOfDay();
        $end = $this->periodEnd->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('j'),
                'done' => 0,
                'pending' => 0,
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Planning estimasi vs realisasi produksi (cluster per kategori item).
     * Scope: hanya SPK dengan estimated delivery di bulan terpilih.
     * Estimasi = total SPK, Realisasi = SPK dengan proses produksi terminal.
     *
     * @return array{
     *     spkCount: int,
     *     qtyTotal: int,
     *     byItem: list<array{label: string, count: int, qty: int, percent: string}>,
     *     byType: list<array{label: string, count: int, qty: int, percent: string}>,
     *     types: list<string>,
     *     byItemType: list<array{item: string, total: int, values: array<string, int>}>
     * }
     */
    private function resolveProductionForecast(): array
    {
        $empty = [
            'spkCount' => 0,
            'qtyTotal' => 0,
            'byItem' => [],
            'byType' => [],
            'types' => [],
            'byItemType' => [],
        ];

        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'estimated_delivery_time')
        ) {
            return $empty;
        }

        $base = $this->planningBaseQuery();

        $spkCount = (clone $base)->count();
        $qtyTotal = (int) round((float) (clone $base)->sum(DB::raw('COALESCE(qty, 1)')));
        $clustered = $this->planningItemMatrix(clone $base, 12);

        return [
            'spkCount' => $spkCount,
            'qtyTotal' => $qtyTotal,
            'byItem' => $this->forecastDistribution((clone $base), 'item_name', 12),
            'byType' => $this->forecastDistribution((clone $base), 'spk_type', 10),
            'types' => $clustered['types'],
            'byItemType' => $clustered['rows'],
        ];
    }

    /**
     * Matriks planning: item (baris) × Estimasi/Realisasi (kolom).
     *
     * @return array{
     *     types: list<string>,
     *     rows: list<array{item: string, total: int, values: array<string, int>}>
     * }
     */
    private function planningItemMatrix(Builder $base, int $itemLimit): array
    {
        if (! Schema::connection('third')->hasColumn('spk', 'item_name')) {
            return [
                'types' => [],
                'rows' => [],
            ];
        }

        $itemExpr = "CASE
            WHEN item_name IS NULL OR TRIM(item_name) = '' THEN 'Custom'
            ELSE TRIM(item_name)
        END";
        $doneExpr = $this->doneExpression();

        $rows = $base
            ->selectRaw("
                {$itemExpr} as item_label,
                COUNT(*) as estimasi_count,
                SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as realisasi_count
            ")
            ->groupByRaw($itemExpr)
            ->orderByDesc('estimasi_count')
            ->limit($itemLimit)
            ->get();

        $types = ['Estimasi', 'Realisasi'];

        $normalized = $rows->map(function (object $row) use ($types): array {
            $item = trim((string) $row->item_label);
            $estimasi = (int) $row->estimasi_count;
            $realisasi = (int) $row->realisasi_count;

            if ($item === '') {
                $item = 'Custom';
            }

            return [
                'item' => $item,
                'total' => $estimasi,
                'values' => [
                    $types[0] => $estimasi,
                    $types[1] => $realisasi,
                ],
            ];
        })->values()->all();

        return [
            'types' => $types,
            'rows' => $normalized,
        ];
    }

    /**
     * Basis query planning: SPK dengan estimated delivery di periode terpilih.
     */
    private function planningBaseQuery(): Builder
    {
        $query = $this->spkBase();
        $this->applyPeriodFilter($query, 'estimated_delivery_time');

        return $query;
    }

    /**
     * @return list<array{label: string, count: int, qty: int, percent: string}>
     */
    private function forecastDistribution(Builder $base, string $column, int $limit): array
    {
        $allowed = ['spk_type', 'item_name'];

        if (
            ! in_array($column, $allowed, true)
            || ! Schema::connection('third')->hasColumn('spk', $column)
        ) {
            return [];
        }

        $rows = $base
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("TRIM(`{$column}`) as label, COUNT(*) as aggregate_count, SUM(COALESCE(qty, 1)) as aggregate_qty")
            ->groupByRaw("TRIM(`{$column}`)")
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get();

        $total = (int) $rows->sum('aggregate_count');

        return $rows->map(function (object $row) use ($total): array {
            $count = (int) $row->aggregate_count;
            $label = trim((string) $row->label);

            return [
                'label' => $label !== '' ? $label : 'Lainnya',
                'count' => $count,
                'qty' => (int) round((float) $row->aggregate_qty),
                'percent' => $total > 0
                    ? number_format(($count / $total) * 100, 1, '.', '')
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * Distribusi hasil item (scope: dibuat ATAU estimasi delivery di periode).
     * item_id / item_name kosong digabung sebagai "Custom".
     *
     * @return list<array{label: string, count: int, qty: int, percent: string}>
     */
    private function resolveItemDistribution(int $limit): array
    {
        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'item_name')
        ) {
            return [];
        }

        $hasItemId = Schema::connection('third')->hasColumn('spk', 'item_id');

        $labelExpression = $hasItemId
            ? "CASE
                WHEN item_id IS NULL AND (item_name IS NULL OR TRIM(item_name) = '') THEN 'Custom'
                WHEN item_name IS NULL OR TRIM(item_name) = '' THEN CONCAT('Item #', item_id)
                ELSE TRIM(item_name)
            END"
            : "CASE
                WHEN item_name IS NULL OR TRIM(item_name) = '' THEN 'Custom'
                ELSE TRIM(item_name)
            END";

        $rows = $this->monthScopedSpkBase()
            ->selectRaw("{$labelExpression} as label, COUNT(*) as aggregate_count, SUM(COALESCE(qty, 1)) as aggregate_qty")
            ->groupByRaw($labelExpression)
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get();

        $total = (int) $rows->sum('aggregate_count');

        return $rows->map(function (object $row) use ($total): array {
            $count = (int) $row->aggregate_count;
            $label = trim((string) $row->label);

            return [
                'label' => $label !== '' ? $label : 'Custom',
                'count' => $count,
                'qty' => (int) round((float) $row->aggregate_qty),
                'percent' => $total > 0
                    ? number_format(($count / $total) * 100, 1, '.', '')
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * Jumlah SPK in progress per proses terakhir
     * (scope: dibuat ATAU estimasi delivery di bulan terpilih).
     *
     * @return list<array{label: string, count: int}>
     */
    private function resolveInProgressByProcess(): array
    {
        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'last_process')
        ) {
            return [];
        }

        ['inProgress' => $inProgressExpr, 'confirmed' => $confirmedExpr] = $this->statusExpressions();

        $processExpr = "CASE
            WHEN last_process IS NULL OR TRIM(last_process) = '' THEN 'Tanpa Proses'
            ELSE TRIM(last_process)
        END";

        $rows = $this->monthScopedSpkBase()
            ->whereRaw('NOT ('.$this->doneExpression().')')
            ->whereRaw("NOT ({$confirmedExpr})")
            ->whereRaw("({$inProgressExpr})")
            ->selectRaw("{$processExpr} as process_label, COUNT(*) as aggregate_count")
            ->groupByRaw($processExpr)
            ->orderByDesc('aggregate_count')
            ->get();

        return $rows->map(function (object $row): array {
            $label = trim((string) $row->process_label);

            return [
                'label' => $label !== '' ? $label : 'Tanpa Proses',
                'count' => (int) $row->aggregate_count,
            ];
        })->values()->all();
    }

    /**
     * WIP lantai produksi saat ini (backlog tahun berjalan) per proses terakhir.
     *
     * @return list<array{label: string, count: int}>
     */
    private function resolveShopFloorWipByProcess(): array
    {
        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'last_process')
        ) {
            return [];
        }

        ['inProgress' => $inProgressExpr, 'confirmed' => $confirmedExpr] = $this->statusExpressions();

        $processExpr = "CASE
            WHEN last_process IS NULL OR TRIM(last_process) = '' THEN 'Tanpa Proses'
            ELSE TRIM(last_process)
        END";

        $rows = $this->yearScopedSpkBase()
            ->whereRaw('NOT ('.$this->doneExpression().')')
            ->whereRaw("NOT ({$confirmedExpr})")
            ->whereRaw("({$inProgressExpr})")
            ->selectRaw("{$processExpr} as process_label, COUNT(*) as aggregate_count")
            ->groupByRaw($processExpr)
            ->orderByDesc('aggregate_count')
            ->get();

        return $rows->map(function (object $row): array {
            $label = trim((string) $row->process_label);

            return [
                'label' => $label !== '' ? $label : 'Tanpa Proses',
                'count' => (int) $row->aggregate_count,
            ];
        })->values()->all();
    }

    private function resolveShopFloorOverdueWipCount(): int
    {
        if (! Schema::connection('third')->hasTable('spk')) {
            return 0;
        }

        return $this->applyOverdueFilter($this->yearScopedSpkBase())->count();
    }

    private function resolveShopFloorCompletedThisMonth(): int
    {
        if (! Schema::connection('third')->hasTable('spk')) {
            return 0;
        }

        return $this->monthScopedSpkBase()
            ->whereRaw($this->doneExpression())
            ->count();
    }

    /**
     * @return array{
     *     avgAgeDays: string|null,
     *     agedOver7Days: int,
     *     buckets: list<array{label: string, count: int}>,
     *     byProcess: list<array{process: string, count: int, avgAgeDays: string|null, maxAgeDays: string|null}>
     * }
     */
    private function resolveShopFloorAging(): array
    {
        $emptyBuckets = [
            ['label' => '0–2 hari', 'count' => 0],
            ['label' => '3–7 hari', 'count' => 0],
            ['label' => '8–14 hari', 'count' => 0],
            ['label' => '>14 hari', 'count' => 0],
        ];

        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'last_process')
        ) {
            return [
                'avgAgeDays' => null,
                'agedOver7Days' => 0,
                'buckets' => $emptyBuckets,
                'byProcess' => [],
            ];
        }

        ['inProgress' => $inProgressExpr, 'confirmed' => $confirmedExpr] = $this->statusExpressions();

        $rows = $this->yearScopedSpkBase()
            ->whereRaw('NOT ('.$this->doneExpression().')')
            ->whereRaw("NOT ({$confirmedExpr})")
            ->whereRaw("({$inProgressExpr})")
            ->orderByDesc('row_id')
            ->limit(500)
            ->get([
                'row_id',
                'last_process',
                'modified_date',
                'created_date',
            ]);

        if ($rows->isEmpty()) {
            return [
                'avgAgeDays' => null,
                'agedOver7Days' => 0,
                'buckets' => $emptyBuckets,
                'byProcess' => [],
            ];
        }

        $lastProcessDates = $this->resolveLastProcessDates($rows, 'Y-m-d H:i:s');
        $now = now();
        $ages = [];
        /** @var array<string, array{ages: list<float>, count: int}> $byProcess */
        $byProcess = [];
        $buckets = [
            '0-2' => 0,
            '3-7' => 0,
            '8-14' => 0,
            '14+' => 0,
        ];
        $agedOver7Days = 0;

        foreach ($rows as $row) {
            $spkId = (int) ($row->row_id ?? 0);
            $process = filled($row->last_process ?? null)
                ? trim((string) $row->last_process)
                : 'Tanpa Proses';

            $anchor = $lastProcessDates[$spkId]
                ?? (filled($row->modified_date ?? null) ? (string) $row->modified_date : null)
                ?? (filled($row->created_date ?? null) ? (string) $row->created_date : null);

            if ($anchor === null) {
                continue;
            }

            $ageDays = max(
                0.0,
                round((float) Carbon::parse($anchor)->diffInDays($now, absolute: true), 1),
            );
            $ages[] = $ageDays;

            if ($ageDays > 7) {
                $agedOver7Days++;
            }

            if ($ageDays <= 2) {
                $buckets['0-2']++;
            } elseif ($ageDays <= 7) {
                $buckets['3-7']++;
            } elseif ($ageDays <= 14) {
                $buckets['8-14']++;
            } else {
                $buckets['14+']++;
            }

            if (! isset($byProcess[$process])) {
                $byProcess[$process] = [
                    'ages' => [],
                    'count' => 0,
                ];
            }

            $byProcess[$process]['ages'][] = $ageDays;
            $byProcess[$process]['count']++;
        }

        $agingByProcess = collect($byProcess)
            ->map(function (array $group, string $process): array {
                $avg = $group['ages'] !== []
                    ? round(array_sum($group['ages']) / count($group['ages']), 1)
                    : null;
                $max = $group['ages'] !== []
                    ? round(max($group['ages']), 1)
                    : null;

                return [
                    'process' => $process,
                    'count' => $group['count'],
                    'avgAgeDays' => $avg !== null ? number_format($avg, 1, '.', '') : null,
                    'maxAgeDays' => $max !== null ? number_format($max, 1, '.', '') : null,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $avgAge = $ages !== []
            ? round(array_sum($ages) / count($ages), 1)
            : null;

        return [
            'avgAgeDays' => $avgAge !== null ? number_format($avgAge, 1, '.', '') : null,
            'agedOver7Days' => $agedOver7Days,
            'buckets' => [
                ['label' => '0–2 hari', 'count' => $buckets['0-2']],
                ['label' => '3–7 hari', 'count' => $buckets['3-7']],
                ['label' => '8–14 hari', 'count' => $buckets['8-14']],
                ['label' => '>14 hari', 'count' => $buckets['14+']],
            ],
            'byProcess' => $agingByProcess,
        ];
    }

    /**
     * Distribusi tipe produksi untuk periode:
     * SPK dibuat di bulan terpilih ATAU estimasi delivery di bulan terpilih
     * (tanpa double-count jika keduanya masuk periode).
     *
     * @return list<array{label: string, count: int, qty: int, percent: string}>
     */
    private function resolveDistribution(string $column, int $limit): array
    {
        $allowed = ['spk_type', 'item_name'];

        if (
            ! in_array($column, $allowed, true)
            || ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', $column)
        ) {
            return [];
        }

        $rows = $this->monthScopedSpkBase()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("TRIM(`{$column}`) as label, COUNT(*) as aggregate_count, SUM(COALESCE(qty, 1)) as aggregate_qty")
            ->groupByRaw("TRIM(`{$column}`)")
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get();

        $total = (int) $rows->sum('aggregate_count');

        return $rows->map(function (object $row) use ($total): array {
            $count = (int) $row->aggregate_count;
            $label = trim((string) $row->label);

            return [
                'label' => $label !== '' ? $label : 'Lainnya',
                'count' => $count,
                'qty' => (int) round((float) $row->aggregate_qty),
                'percent' => $total > 0
                    ? number_format(($count / $total) * 100, 1, '.', '')
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * @return array{
     *     byProcess: list<array{process: string, totalShrink: string, recordCount: int, avgPercent: string|null, nokCount: int|null}>,
     *     totalShrink: string,
     *     okCount: int,
     *     nokCount: int
     * }
     */
    private function resolveShrinkAnalytics(): array
    {
        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);
        $byProcess = [];
        $totalShrink = 0.0;
        $okCount = 0;
        $nokCount = 0;

        foreach ($sources as $source) {
            $table = $source['table'];

            if (! Schema::connection('third')->hasTable($table)) {
                continue;
            }

            $query = DB::connection('third')->table($table);

            if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $this->applyProcessPeriodFilter($query, $table, $source['date_column']);

            if ($source['shrink_column'] === 'computed_mounting') {
                $stats = $query
                    ->selectRaw('COUNT(*) as record_count, SUM(COALESCE(total_weigth_frame_diamond, 0) - COALESCE(weight_finish_goods, 0)) as total_shrink, AVG(CASE WHEN COALESCE(total_weigth_frame_diamond, 0) > 0 THEN ((COALESCE(total_weigth_frame_diamond, 0) - COALESCE(weight_finish_goods, 0)) / total_weigth_frame_diamond) * 100 ELSE NULL END) as avg_percent')
                    ->first();
            } else {
                $column = $source['shrink_column'];

                if (! Schema::connection('third')->hasColumn($table, $column)) {
                    continue;
                }

                $hasStart = Schema::connection('third')->hasColumn($table, 'start_weight');
                $avgExpr = $hasStart
                    ? "AVG(CASE WHEN COALESCE(start_weight, 0) > 0 THEN ({$column} / start_weight) * 100 ELSE NULL END)"
                    : 'NULL';

                $stats = $query
                    ->whereNotNull($column)
                    ->selectRaw("COUNT(*) as record_count, SUM({$column}) as total_shrink, {$avgExpr} as avg_percent")
                    ->first();
            }

            $processShrink = round((float) ($stats->total_shrink ?? 0), 3);
            $recordCount = (int) ($stats->record_count ?? 0);
            $avgPercent = $this->nullableFloat($stats->avg_percent ?? null);
            $processNok = null;

            if (
                $table === 'finishinghandmade'
                && Schema::connection('third')->hasColumn($table, 'shrink_tolerance')
                && Schema::connection('third')->hasColumn($table, 'start_weight')
            ) {
                $toleranceQuery = DB::connection('third')
                    ->table($table)
                    ->where('is_deleted', 0)
                    ->whereNotNull('shrink')
                    ->whereNotNull('shrink_tolerance')
                    ->whereNotNull('start_weight')
                    ->where('start_weight', '>', 0);

                $this->applyProcessPeriodFilter($toleranceQuery, $table, $source['date_column']);

                $toleranceStats = $toleranceQuery
                    ->selectRaw('SUM(CASE WHEN ABS((shrink / start_weight) * 100) <= ABS(shrink_tolerance) + 0.005 THEN 1 ELSE 0 END) as ok_count, SUM(CASE WHEN ABS((shrink / start_weight) * 100) > ABS(shrink_tolerance) + 0.005 THEN 1 ELSE 0 END) as nok_count')
                    ->first();

                $okCount += (int) ($toleranceStats->ok_count ?? 0);
                $nokCount += (int) ($toleranceStats->nok_count ?? 0);
                $processNok = (int) ($toleranceStats->nok_count ?? 0);
            }

            if ($recordCount === 0 && abs($processShrink) < 0.0005) {
                continue;
            }

            $totalShrink += $processShrink;
            $byProcess[] = [
                'process' => $source['label'],
                'totalShrink' => $this->formatWeight($processShrink),
                'recordCount' => $recordCount,
                'avgPercent' => $avgPercent !== null
                    ? number_format($avgPercent, 2, '.', '')
                    : null,
                'nokCount' => $processNok,
            ];
        }

        return [
            'byProcess' => $byProcess,
            'totalShrink' => $this->formatWeight($totalShrink),
            'okCount' => $okCount,
            'nokCount' => $nokCount,
        ];
    }

    /**
     * @param  array{avgLeadDays: float|null, avgEstimatedDays: float|null, avgYieldPercent: float|null}  $spkStats
     * @param  array{used: string}  $gold
     * @return array{
     *     avgLeadTimeDays: string|null,
     *     avgEstimatedDays: string|null,
     *     avgVarianceDays: string|null,
     *     avgYieldPercent: string|null,
     *     avgGoldYieldPercent: string|null
     * }
     */
    private function resolveControlAnalytics(array $spkStats, array $gold): array
    {
        $avgLead = $spkStats['avgLeadDays'];
        $avgEstimated = $spkStats['avgEstimatedDays'];
        $variance = $avgLead !== null && $avgEstimated !== null
            ? round($avgLead - $avgEstimated, 2)
            : null;

        $avgGoldYield = null;
        $goldUsed = $this->nullableFloat($gold['used']);

        if (
            Schema::connection('third')->hasTable('spk')
            && Schema::connection('third')->hasColumn('spk', 'gold_weight')
            && $goldUsed !== null
        ) {
            $planningSum = $this->nullableFloat(
                $this->monthScopedSpkBase()
                    ->whereNotNull('gold_weight')
                    ->where('gold_weight', '>', 0)
                    ->sum('gold_weight'),
            );

            if ($planningSum !== null && abs($planningSum) >= 0.0005) {
                $avgGoldYield = round(($goldUsed / $planningSum) * 100, 2);
            }
        }

        return [
            'avgLeadTimeDays' => $avgLead !== null ? number_format($avgLead, 2, '.', '') : null,
            'avgEstimatedDays' => $avgEstimated !== null ? number_format($avgEstimated, 2, '.', '') : null,
            'avgVarianceDays' => $variance !== null ? number_format($variance, 2, '.', '') : null,
            'avgYieldPercent' => $spkStats['avgYieldPercent'] !== null
                ? number_format($spkStats['avgYieldPercent'], 2, '.', '')
                : null,
            'avgGoldYieldPercent' => $avgGoldYield !== null
                ? number_format($avgGoldYield, 2, '.', '')
                : null,
        ];
    }

    /**
     * @return list<array{name: string, jobCount: int, totalShrink: string}>
     */
    private function resolveCraftsmanRanking(): array
    {
        return array_map(function (array $row): array {
            return [
                'name' => $row['name'],
                'jobCount' => $row['jobCount'],
                'totalShrink' => $row['totalShrink'],
            ];
        }, $this->resolveCraftsmanPerformanceRanking(10));
    }

    /**
     * @return list<array{name: string, jobCount: int, totalShrink: string, avgShrink: string|null}>
     */
    private function resolveCraftsmanPerformanceRanking(int $limit): array
    {
        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);
        /** @var array<int, array{id: int, jobCount: int, totalShrink: float}> $byCraftsman */
        $byCraftsman = [];

        foreach ($sources as $source) {
            $table = $source['table'];

            if (! Schema::connection('third')->hasTable($table)) {
                continue;
            }

            $craftsmanColumn = Schema::connection('third')->hasColumn($table, 'craftsman_id')
                ? 'craftsman_id'
                : (Schema::connection('third')->hasColumn($table, 'craftman_id') ? 'craftman_id' : null);

            if ($craftsmanColumn === null) {
                continue;
            }

            $query = DB::connection('third')->table($table)
                ->whereNotNull($craftsmanColumn)
                ->where($craftsmanColumn, '>', 0);

            if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $this->applyProcessPeriodFilter($query, $table, $source['date_column']);

            if ($source['shrink_column'] === 'computed_mounting') {
                $rows = $query
                    ->selectRaw("{$craftsmanColumn} as craftsman_id, COUNT(*) as job_count, SUM(COALESCE(total_weigth_frame_diamond, 0) - COALESCE(weight_finish_goods, 0)) as total_shrink")
                    ->groupBy($craftsmanColumn)
                    ->get();
            } else {
                $column = $source['shrink_column'];

                if (! Schema::connection('third')->hasColumn($table, $column)) {
                    continue;
                }

                $rows = $query
                    ->selectRaw("{$craftsmanColumn} as craftsman_id, COUNT(*) as job_count, SUM(COALESCE({$column}, 0)) as total_shrink")
                    ->groupBy($craftsmanColumn)
                    ->get();
            }

            foreach ($rows as $row) {
                $id = (int) $row->craftsman_id;

                if (! isset($byCraftsman[$id])) {
                    $byCraftsman[$id] = [
                        'id' => $id,
                        'jobCount' => 0,
                        'totalShrink' => 0.0,
                    ];
                }

                $byCraftsman[$id]['jobCount'] += (int) $row->job_count;
                $byCraftsman[$id]['totalShrink'] += (float) $row->total_shrink;
            }
        }

        uasort($byCraftsman, fn (array $left, array $right): int => $right['jobCount'] <=> $left['jobCount']);
        $top = array_slice(array_values($byCraftsman), 0, $limit);
        $names = $this->resolveCraftsmanNames(array_column($top, 'id'));

        return array_map(function (array $row) use ($names): array {
            $avgShrink = $row['jobCount'] > 0
                ? round($row['totalShrink'] / $row['jobCount'], 3)
                : null;

            return [
                'name' => $names[$row['id']] ?? "Pengrajin {$row['id']}",
                'jobCount' => $row['jobCount'],
                'totalShrink' => $this->formatWeight(round($row['totalShrink'], 3)),
                'avgShrink' => $avgShrink !== null ? $this->formatWeight($avgShrink) : null,
            ];
        }, $top);
    }

    /**
     * @return list<array{process: string, jobCount: int, craftsmanCount: int, totalShrink: string}>
     */
    private function resolveCraftsmanJobsByProcess(): array
    {
        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);
        $byProcess = [];

        foreach ($sources as $source) {
            $table = $source['table'];

            if (! Schema::connection('third')->hasTable($table)) {
                continue;
            }

            $craftsmanColumn = Schema::connection('third')->hasColumn($table, 'craftsman_id')
                ? 'craftsman_id'
                : (Schema::connection('third')->hasColumn($table, 'craftman_id') ? 'craftman_id' : null);

            if ($craftsmanColumn === null) {
                continue;
            }

            $query = DB::connection('third')->table($table)
                ->whereNotNull($craftsmanColumn)
                ->where($craftsmanColumn, '>', 0);

            if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $this->applyProcessPeriodFilter($query, $table, $source['date_column']);

            if ($source['shrink_column'] === 'computed_mounting') {
                $stats = $query
                    ->selectRaw("COUNT(*) as job_count, COUNT(DISTINCT {$craftsmanColumn}) as craftsman_count, SUM(COALESCE(total_weigth_frame_diamond, 0) - COALESCE(weight_finish_goods, 0)) as total_shrink")
                    ->first();
            } else {
                $column = $source['shrink_column'];

                if (! Schema::connection('third')->hasColumn($table, $column)) {
                    continue;
                }

                $stats = $query
                    ->selectRaw("COUNT(*) as job_count, COUNT(DISTINCT {$craftsmanColumn}) as craftsman_count, SUM(COALESCE({$column}, 0)) as total_shrink")
                    ->first();
            }

            $jobCount = (int) ($stats->job_count ?? 0);

            if ($jobCount === 0) {
                continue;
            }

            $byProcess[] = [
                'process' => $source['label'],
                'jobCount' => $jobCount,
                'craftsmanCount' => (int) ($stats->craftsman_count ?? 0),
                'totalShrink' => $this->formatWeight(round((float) ($stats->total_shrink ?? 0), 3)),
            ];
        }

        usort($byProcess, fn (array $left, array $right): int => $right['jobCount'] <=> $left['jobCount']);

        return $byProcess;
    }

    /**
     * @return list<array{sku: string, item: string, spkCount: int, qty: int, doneSpk: int, doneQty: int, completionPercent: string}>
     */
    private function resolveSkuOutputRows(int $limit): array
    {
        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'sku_id')
        ) {
            return [];
        }

        $doneExpr = $this->doneExpression();

        $rows = $this->monthScopedSpkBase()
            ->whereNotNull('sku_id')
            ->where('sku_id', '>', 0)
            ->selectRaw("
                sku_id,
                MAX(TRIM(COALESCE(item_name, ''))) as item_label,
                COUNT(*) as spk_count,
                SUM(COALESCE(qty, 1)) as qty_total,
                SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as done_spk,
                SUM(CASE WHEN ({$doneExpr}) THEN COALESCE(qty, 1) ELSE 0 END) as done_qty
            ")
            ->groupBy('sku_id')
            ->orderByDesc('qty_total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $skuIds = $rows->map(fn (object $row): int => (int) $row->sku_id)->all();
        $skuCodes = $this->resolveSkuCodes($skuIds);

        return $rows->map(function (object $row) use ($skuCodes): array {
            $skuId = (int) $row->sku_id;
            $spkCount = (int) $row->spk_count;
            $doneSpk = (int) $row->done_spk;
            $item = trim((string) ($row->item_label ?? ''));

            return [
                'sku' => $skuCodes[$skuId] ?? "SKU #{$skuId}",
                'item' => $item !== '' ? $item : '—',
                'spkCount' => $spkCount,
                'qty' => (int) round((float) $row->qty_total),
                'doneSpk' => $doneSpk,
                'doneQty' => (int) round((float) $row->done_qty),
                'completionPercent' => $spkCount > 0
                    ? number_format(($doneSpk / $spkCount) * 100, 1, '.', '')
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * @return list<array{item: string, spkCount: int, qty: int, doneSpk: int, doneQty: int, completionPercent: string}>
     */
    private function resolveItemOutputRows(int $limit): array
    {
        if (
            ! Schema::connection('third')->hasTable('spk')
            || ! Schema::connection('third')->hasColumn('spk', 'item_name')
        ) {
            return [];
        }

        $doneExpr = $this->doneExpression();
        $itemExpr = "CASE
            WHEN item_name IS NULL OR TRIM(item_name) = '' THEN 'Custom'
            ELSE TRIM(item_name)
        END";

        $rows = $this->monthScopedSpkBase()
            ->selectRaw("
                {$itemExpr} as item_label,
                COUNT(*) as spk_count,
                SUM(COALESCE(qty, 1)) as qty_total,
                SUM(CASE WHEN ({$doneExpr}) THEN 1 ELSE 0 END) as done_spk,
                SUM(CASE WHEN ({$doneExpr}) THEN COALESCE(qty, 1) ELSE 0 END) as done_qty
            ")
            ->groupByRaw($itemExpr)
            ->orderByDesc('qty_total')
            ->limit($limit)
            ->get();

        return $rows->map(function (object $row): array {
            $spkCount = (int) $row->spk_count;
            $doneSpk = (int) $row->done_spk;
            $item = trim((string) $row->item_label);

            return [
                'item' => $item !== '' ? $item : 'Custom',
                'spkCount' => $spkCount,
                'qty' => (int) round((float) $row->qty_total),
                'doneSpk' => $doneSpk,
                'doneQty' => (int) round((float) $row->done_qty),
                'completionPercent' => $spkCount > 0
                    ? number_format(($doneSpk / $spkCount) * 100, 1, '.', '')
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function resolveSkuCodes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            if (! Schema::connection('second')->hasTable('sku_master')) {
                return [];
            }

            return DB::connection('second')
                ->table('sku_master')
                ->whereIn('id', $ids)
                ->pluck('sku_code', 'id')
                ->map(fn (mixed $code): string => (string) $code)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function resolveCraftsmanNames(array $ids): array
    {
        if ($ids === [] || ! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        return DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $ids)
            ->pluck('name', 'row_id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @return array{issued: string, returned: string, used: string, difference: string}
     */
    private function resolveGoldAnalytics(): array
    {
        $empty = [
            'issued' => '0.000',
            'returned' => '0.000',
            'used' => '0.000',
            'difference' => '0.000',
        ];

        if (! Schema::connection('third')->hasTable('trmaterialgold')) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('trmaterialgold')
            ->whereIn('transtype_id', [5, 6])
            ->whereNotNull('spk_id')
            ->where('spk_id', '>', 0)
            ->whereIn('spk_id', $this->monthScopedSpkBase()->select('row_id'));

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $issued = round((float) (clone $query)->where('transtype_id', 5)->sum('weight'), 3);
        $returned = round((float) (clone $query)->where('transtype_id', 6)->sum('weight'), 3);
        $used = round($issued - $returned, 3);
        $difference = round($issued - $used - $returned, 3);

        return [
            'issued' => $this->formatWeight($issued),
            'returned' => $this->formatWeight($returned),
            'used' => $this->formatWeight($used),
            'difference' => $this->formatWeight($difference),
        ];
    }

    /**
     * @return array{startCrt: string, endCrt: string, difference: string}
     */
    private function resolveStoneAnalytics(): array
    {
        $empty = [
            'startCrt' => '0.0000',
            'endCrt' => '0.0000',
            'difference' => '0.0000',
        ];

        if (! Schema::connection('third')->hasTable('trstone')) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('trstone')
            ->whereIn('transtype_id', [7, 8])
            ->whereNotNull('spk_id')
            ->where('spk_id', '>', 0)
            ->whereIn('spk_id', $this->monthScopedSpkBase()->select('row_id'));

        if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $startCrt = round((float) (clone $query)->where('transtype_id', 7)->sum('crt'), 4);
        $endCrt = round((float) (clone $query)->where('transtype_id', 8)->sum('crt'), 4);
        $difference = round($startCrt - $endCrt, 4);

        return [
            'startCrt' => number_format($startCrt, 4, '.', ''),
            'endCrt' => number_format($endCrt, 4, '.', ''),
            'difference' => number_format($difference, 4, '.', ''),
        ];
    }

    private function spkBase(): Builder
    {
        return DB::connection('third')->table('spk')->where('is_deleted', 0);
    }

    /**
     * Scope backlog: SPK dibuat di tahun kalender berjalan.
     */
    private function yearScopedSpkBase(): Builder
    {
        $query = $this->spkBase();
        $this->applyCurrentYearFilter($query);

        return $query;
    }

    private function applyCurrentYearFilter(Builder $query): void
    {
        $yearStart = now()->copy()->startOfYear()->startOfDay();
        $yearEnd = now()->copy()->endOfYear()->endOfDay();

        if (Schema::connection('third')->hasColumn('spk', 'created_date')) {
            $query->whereNotNull('created_date')
                ->whereBetween('created_date', [
                    $yearStart->toDateTimeString(),
                    $yearEnd->toDateTimeString(),
                ]);

            return;
        }

        $query->where('spk_no', 'like', now()->format('Y').'/%');
    }

    private function applyPeriodFilter(Builder $query, string $column): void
    {
        $query->whereNotNull($column)
            ->whereBetween($column, [
                $this->periodStart->toDateTimeString(),
                $this->periodEnd->toDateTimeString(),
            ]);
    }

    /**
     * Scope dashboard (selain backlog): SPK dibuat di bulan terpilih
     * ATAU estimasi delivery di bulan terpilih.
     * Backlog memakai yearScopedSpkBase() (tahun berjalan).
     */
    private function applyMonthScopeFilter(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder->where(function (Builder $created): void {
                $this->applyPeriodFilter($created, 'created_date');
            });

            if (Schema::connection('third')->hasColumn('spk', 'estimated_delivery_time')) {
                $builder->orWhere(function (Builder $estimated): void {
                    $this->applyPeriodFilter($estimated, 'estimated_delivery_time');
                });
            }
        });
    }

    private function monthScopedSpkBase(): Builder
    {
        $query = $this->spkBase();
        $this->applyMonthScopeFilter($query);

        return $query;
    }

    private function applyProcessPeriodFilter(Builder $query, string $table, string $preferredDateColumn): void
    {
        if (
            Schema::connection('third')->hasTable('spk')
            && Schema::connection('third')->hasColumn($table, 'spk_id')
        ) {
            $query->whereIn('spk_id', $this->monthScopedSpkBase()->select('row_id'));

            return;
        }

        $dateColumn = Schema::connection('third')->hasColumn($table, $preferredDateColumn)
            ? $preferredDateColumn
            : (Schema::connection('third')->hasColumn($table, 'created_date') ? 'created_date' : null);

        if ($dateColumn === null) {
            return;
        }

        $this->applyPeriodFilter($query, $dateColumn);
    }

    private function formatPeriodLabel(): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return ($months[$this->periodStart->month] ?? $this->periodStart->format('F'))
            .' '.$this->periodStart->year;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 3);
    }

    private function formatWeight(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
