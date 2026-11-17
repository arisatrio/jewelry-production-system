<?php

namespace App\Support;

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoranReportAnalytics
{
    public function __construct(
        private readonly CoranSpkEligibility $spkEligibility = new CoranSpkEligibility,
    ) {}

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     monthlyShrink: array{year: int, months: list<array<string, mixed>>},
     *     monthlyActivity: array{year: int, months: list<array<string, mixed>>},
     *     byCraftsman: list<array<string, mixed>>,
     *     byStatus: list<array<string, mixed>>,
     *     materialBenchmark: array<string, mixed>,
     *     spkStatusCounts: array{pending: int, inProgress: int, completed: int},
     *     rows: list<array<string, mixed>>
     * }
     */
    public function build(?int $craftsmanId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->reportRows($craftsmanId, $dateFrom, $dateTo);

        return [
            'summary' => $this->reportSummary($rows),
            'materialBenchmark' => $this->reportMaterialBenchmark($rows),
            'monthlyShrink' => $this->reportMonthlyShrink($craftsmanId),
            'monthlyActivity' => $this->reportMonthlyActivity($craftsmanId),
            'byCraftsman' => $this->reportByCraftsman($rows),
            'byStatus' => $this->reportByStatus($craftsmanId, $dateFrom, $dateTo),
            'spkStatusCounts' => $this->spkStatusCounts(),
            'rows' => array_map(fn (array $row): array => $this->toPageRow($row), $rows),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function craftsmanOptions(): array
    {
        if (! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        return DB::connection('third')
            ->table('mscraftsman')
            ->where('is_deleted', 0)
            ->where('is_active', 'YES')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->get(['row_id', 'name'])
            ->map(fn (object $row): array => [
                'value' => (string) $row->row_id,
                'label' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     craftsmanName: string|null,
     *     spkCount: int,
     *     spkNos: list<string>,
     *     submitMaterial: float,
     *     spkWeight: float,
     *     resultMaterial: float,
     *     shrink: float,
     *     shrinkRatio: float|null,
     *     hasilRatio: float|null,
     *     sisaRatio: float|null,
     *     qcNotOkCount: int,
     *     qcDetailCount: int
     * }>
     */
    private function reportRows(?int $craftsmanId, string $dateFrom, string $dateTo): array
    {
        $documents = Coran::query()
            ->notDeleted()
            ->with([
                'details' => fn ($query) => $query
                    ->notDeleted()
                    ->with([
                        'production' => fn ($productionQuery) => $productionQuery
                            ->notDeleted()
                            ->select(['row_id', 'spk_no']),
                    ]),
            ])
            ->whereIn('status', $this->approvedReportStatuses())
            ->whereDate('trans_date', '>=', $dateFrom)
            ->whereDate('trans_date', '<=', $dateTo)
            ->when($craftsmanId !== null, fn ($query) => $query->where('craftsman_id', $craftsmanId))
            ->orderBy('trans_date')
            ->orderBy('row_id')
            ->get();

        $craftsmanNames = $this->resolveCraftsmanNames(
            $documents->pluck('craftsman_id')->filter()->map(fn (mixed $id): int => (int) $id)->all(),
        );

        return $documents
            ->map(fn (Coran $coran): array => $this->toReportRow($coran, $craftsmanNames))
            ->sortBy(fn (array $row): array => [
                $row['craftsmanName'] === null ? 1 : 0,
                mb_strtolower($row['craftsmanName'] ?? ''),
                $row['transDate'] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $craftsmanNames
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     craftsmanName: string|null,
     *     spkCount: int,
     *     spkNos: list<string>,
     *     submitMaterial: float,
     *     spkWeight: float,
     *     resultMaterial: float,
     *     shrink: float,
     *     shrinkRatio: float|null,
     *     hasilRatio: float|null,
     *     sisaRatio: float|null,
     *     qcNotOkCount: int,
     *     qcDetailCount: int
     * }
     */
    private function toReportRow(Coran $coran, array $craftsmanNames): array
    {
        $details = $coran->details->filter(fn (CoranSpk $detail): bool => $detail->is_deleted === 0);
        $submitMaterial = $this->totalSubmitMaterial($coran);
        $resultMaterial = $this->totalResultMaterial($coran);
        $spkWeight = $this->resolveSpkWeight($coran, $details);
        $shrink = $this->resolveShrink(
            $submitMaterial,
            $resultMaterial,
            $spkWeight,
            (float) ($this->toFloat($coran->shrink) ?? 0.0),
            $coran,
        );

        $qcNotOkCount = 0;
        $qcDetailCount = $details->count();

        foreach ($details as $detail) {
            if ($this->detailHasNok($detail)) {
                $qcNotOkCount++;
            }
        }

        $spkNos = $details
            ->map(fn (CoranSpk $detail): ?string => $detail->production?->spk_no)
            ->filter()
            ->map(fn (mixed $spkNo): string => (string) $spkNo)
            ->unique()
            ->values()
            ->all();

        $craftsmanId = filled($coran->craftsman_id) ? (int) $coran->craftsman_id : 0;

        return [
            'id' => (int) $coran->row_id,
            'docNo' => $coran->doc_no,
            'transDate' => $coran->trans_date?->format('Y-m-d'),
            'craftsmanName' => $craftsmanId > 0
                ? ($craftsmanNames[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                : null,
            'spkCount' => count($spkNos),
            'spkNos' => $spkNos,
            'submitMaterial' => $submitMaterial,
            'spkWeight' => $spkWeight,
            'resultMaterial' => $resultMaterial,
            'shrink' => $shrink,
            'shrinkRatio' => abs($submitMaterial) >= 0.0005 ? $shrink / $submitMaterial : null,
            'hasilRatio' => abs($submitMaterial) >= 0.0005 ? $spkWeight / $submitMaterial : null,
            'sisaRatio' => abs($submitMaterial) >= 0.0005 ? $resultMaterial / $submitMaterial : null,
            'qcNotOkCount' => $qcNotOkCount,
            'qcDetailCount' => $qcDetailCount,
        ];
    }

    /**
     * @param  array<int, array{submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float}>  $rows
     * @return array{
     *     documentSampleCount: int,
     *     perDocument: array<string, mixed>|null,
     *     aggregate: array<string, mixed>|null,
     *     storedBalanceGapPercent: string|null
     * }
     */
    private function reportMaterialBenchmark(array $rows): array
    {
        $submitMaterial = array_sum(array_map(fn (array $row): float => $row['submitMaterial'], $rows));
        $spkWeight = array_sum(array_map(fn (array $row): float => $row['spkWeight'], $rows));
        $resultMaterial = array_sum(array_map(fn (array $row): float => $row['resultMaterial'], $rows));
        $shrink = array_sum(array_map(fn (array $row): float => $row['shrink'], $rows));

        $hasilShares = [];
        $sisaShares = [];
        $shrinkShares = [];

        foreach ($rows as $row) {
            if (abs($row['submitMaterial']) < 0.0005) {
                continue;
            }

            $submit = $row['submitMaterial'];
            $hasilShares[] = $row['spkWeight'] / $submit;
            $sisaShares[] = $row['resultMaterial'] / $submit;
            $shrinkShares[] = $this->balancedShrink($row) / $submit;
        }

        $average = static function (array $values): ?float {
            if ($values === []) {
                return null;
            }

            return array_sum($values) / count($values);
        };

        $balancedShrinkTotal = array_sum(array_map(
            fn (array $row): float => $this->balancedShrink($row),
            $rows,
        ));

        $aggregateHasilShare = abs($submitMaterial) >= 0.0005 ? $spkWeight / $submitMaterial : null;
        $aggregateSisaShare = abs($submitMaterial) >= 0.0005 ? $resultMaterial / $submitMaterial : null;
        $aggregateShrinkShare = abs($submitMaterial) >= 0.0005
            ? $balancedShrinkTotal / $submitMaterial
            : null;

        $storedBalanceGapPercent = null;

        if (abs($submitMaterial) >= 0.0005) {
            $storedAllocated = $spkWeight + $resultMaterial + $shrink;
            $gapRatio = ($submitMaterial - $storedAllocated) / $submitMaterial * 100;

            if (abs($gapRatio) > 0.05) {
                $storedBalanceGapPercent = $this->formatGainAwarePercent($gapRatio);
            }
        }

        return [
            'documentSampleCount' => count($hasilShares),
            'perDocument' => $this->formatMaterialBenchmarkShares(
                $average($hasilShares),
                $average($sisaShares),
                $average($shrinkShares),
            ),
            'aggregate' => $this->formatMaterialBenchmarkShares(
                $aggregateHasilShare,
                $aggregateSisaShare,
                $aggregateShrinkShare,
            ),
            'storedBalanceGapPercent' => $storedBalanceGapPercent,
        ];
    }

    /**
     * Susut teoritis agar hasil + sisa + susut = bahan (sesuai rumus simpan coran).
     *
     * @param  array{submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float}  $row
     */
    private function balancedShrink(array $row): float
    {
        return round($row['submitMaterial'] - $row['resultMaterial'] - $row['spkWeight'], 2);
    }

    /**
     * @param  iterable<int, CoranSpk>  $details
     */
    private function resolveSpkWeight(Coran $coran, iterable $details): float
    {
        $headerWeight = (float) ($this->toFloat($coran->weight) ?? 0.0);

        if (abs($headerWeight) >= 0.0005) {
            return $headerWeight;
        }

        return $this->sumDetailWeights($details);
    }

    private function resolveShrink(
        float $submitMaterial,
        float $resultMaterial,
        float $spkWeight,
        float $storedShrink,
        Coran $coran,
    ): float {
        if (abs($submitMaterial) < 0.0005) {
            return 0.0;
        }

        $balancedShrink = $this->balancedShrink([
            'submitMaterial' => $submitMaterial,
            'resultMaterial' => $resultMaterial,
            'spkWeight' => $spkWeight,
            'shrink' => $storedShrink,
        ]);

        $headerWeight = (float) ($this->toFloat($coran->weight) ?? 0.0);

        if (abs($headerWeight) < 0.0005 && abs($spkWeight) >= 0.0005) {
            return $balancedShrink;
        }

        if (abs($balancedShrink - $storedShrink) > 0.05) {
            return $balancedShrink;
        }

        return $storedShrink;
    }

    /**
     * @return array{
     *     hasilShare: float,
     *     sisaShare: float,
     *     shrinkShare: float,
     *     hasilPercent: string,
     *     sisaPercent: string,
     *     shrinkPercent: string,
     *     allocationTotalPercent: string
     * }|null
     */
    private function formatMaterialBenchmarkShares(
        ?float $hasilShare,
        ?float $sisaShare,
        ?float $shrinkShare,
    ): ?array {
        if ($hasilShare === null || $sisaShare === null || $shrinkShare === null) {
            return null;
        }

        $allocationTotal = ($hasilShare + $sisaShare + $shrinkShare) * 100;

        return [
            'hasilShare' => round($hasilShare, 6),
            'sisaShare' => round($sisaShare, 6),
            'shrinkShare' => round($shrinkShare, 6),
            'hasilPercent' => $this->formatGainAwarePercent($hasilShare * 100),
            'sisaPercent' => $this->formatGainAwarePercent($sisaShare * 100),
            'shrinkPercent' => $this->formatGainAwarePercent($shrinkShare * 100),
            'allocationTotalPercent' => $this->formatGainAwarePercent($allocationTotal),
        ];
    }

    /**
     * @param  array<int, array{craftsmanName: string|null, submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float, shrinkRatio: float|null, qcNotOkCount: int, qcDetailCount: int, spkNos: list<string>}>  $rows
     * @return array<string, mixed>
     */
    private function reportSummary(array $rows): array
    {
        return array_merge(
            $this->aggregateReportRows($rows),
            [
                'craftsmanCount' => count(array_unique(array_filter(array_column($rows, 'craftsmanName')))),
            ],
            $this->reportSpkShrinkAverages($rows),
        );
    }

    /**
     * @param  array<int, array{spkNos: list<string>, submitMaterial: float, shrink: float, shrinkRatio: float|null}>  $rows
     * @return array{
     *     spkCount: int,
     *     averageShrinkPerDocument: string|null,
     *     averageShrinkPercentPerDocument: string|null,
     *     averageShrinkPerSpk: string|null,
     *     averageShrinkPercentPerSpk: string|null
     * }
     */
    private function reportSpkShrinkAverages(array $rows): array
    {
        $documentCount = count($rows);
        $documentShrink = 0.0;
        $documentPercents = [];
        /** @var array<string, array{shrink: float, submitMaterial: float}> $bySpk */
        $bySpk = [];

        foreach ($rows as $row) {
            $shrink = $row['shrink'];
            $submitMaterial = $row['submitMaterial'];
            $documentShrink += $shrink;

            if (abs($submitMaterial) >= 0.0005) {
                $documentPercents[] = ($shrink / $submitMaterial) * 100;
            }

            foreach ($row['spkNos'] as $spkNo) {
                if ($spkNo === '') {
                    continue;
                }

                $bySpk[$spkNo]['shrink'] = ($bySpk[$spkNo]['shrink'] ?? 0.0) + $shrink / max(1, count($row['spkNos']));
                $bySpk[$spkNo]['submitMaterial'] = ($bySpk[$spkNo]['submitMaterial'] ?? 0.0)
                    + $submitMaterial / max(1, count($row['spkNos']));
            }
        }

        $spkCount = count($bySpk);
        $spkPercents = [];

        foreach ($bySpk as $spk) {
            if (abs($spk['submitMaterial']) >= 0.0005) {
                $spkPercents[] = ($spk['shrink'] / $spk['submitMaterial']) * 100;
            }
        }

        $uniqueSpkNos = [];

        foreach ($rows as $row) {
            foreach ($row['spkNos'] as $spkNo) {
                if ($spkNo !== '') {
                    $uniqueSpkNos[$spkNo] = true;
                }
            }
        }

        return [
            'spkCount' => count($uniqueSpkNos),
            'averageShrinkPerDocument' => $documentCount > 0
                ? ($this->formatGainAwareDecimal($documentShrink / $documentCount) ?? '0.00')
                : null,
            'averageShrinkPercentPerDocument' => $documentPercents === []
                ? null
                : $this->formatGainAwarePercent(array_sum($documentPercents) / count($documentPercents)),
            'averageShrinkPerSpk' => $spkCount > 0
                ? ($this->formatGainAwareDecimal(array_sum(array_column($bySpk, 'shrink')) / $spkCount) ?? '0.00')
                : null,
            'averageShrinkPercentPerSpk' => $spkPercents === []
                ? null
                : $this->formatGainAwarePercent(array_sum($spkPercents) / count($spkPercents)),
        ];
    }

    /**
     * @param  array<int, array{craftsmanName: string|null, submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float, shrinkRatio: float|null, qcNotOkCount: int, qcDetailCount: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private function reportByCraftsman(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['craftsmanName'] ?? 'Tanpa pengrajin'][] = $row;
        }

        $result = [];

        foreach ($groups as $craftsmanName => $groupRows) {
            $result[] = array_merge(
                [
                    'craftsmanName' => (string) $craftsmanName,
                ],
                $this->aggregateReportRows($groupRows),
            );
        }

        usort($result, fn (array $left, array $right): int => (float) $right['shrink'] <=> (float) $left['shrink']);

        return $result;
    }

    /**
     * @param  array<int, array{submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float, shrinkRatio: float|null, qcNotOkCount: int, qcDetailCount: int}>  $rows
     * @return array<string, mixed>
     */
    private function aggregateReportRows(array $rows): array
    {
        $submitMaterial = array_sum(array_map(fn (array $row): float => $row['submitMaterial'], $rows));
        $spkWeight = array_sum(array_map(fn (array $row): float => $row['spkWeight'], $rows));
        $resultMaterial = array_sum(array_map(fn (array $row): float => $row['resultMaterial'], $rows));
        $shrink = array_sum(array_map(fn (array $row): float => $row['shrink'], $rows));
        $qcNotOkCount = array_sum(array_map(fn (array $row): int => $row['qcNotOkCount'], $rows));
        $qcDetailCount = array_sum(array_map(fn (array $row): int => $row['qcDetailCount'], $rows));

        return [
            'documentCount' => count($rows),
            'submitMaterial' => $this->formatGainAwareDecimal($submitMaterial) ?? '0.00',
            'spkWeight' => $this->formatGainAwareDecimal($spkWeight) ?? '0.00',
            'resultMaterial' => $this->formatGainAwareDecimal($resultMaterial) ?? '0.00',
            'shrink' => $this->formatGainAwareDecimal($shrink) ?? '0.00',
            'shrinkPercent' => abs($submitMaterial) >= 0.0005
                ? $this->formatGainAwarePercent($shrink / $submitMaterial * 100)
                : null,
            'hasilPercent' => abs($submitMaterial) >= 0.0005
                ? $this->formatGainAwarePercent($spkWeight / $submitMaterial * 100)
                : null,
            'sisaPercent' => abs($submitMaterial) >= 0.0005
                ? $this->formatGainAwarePercent($resultMaterial / $submitMaterial * 100)
                : null,
            'qcNotOkCount' => $qcNotOkCount,
            'qcDetailCount' => $qcDetailCount,
        ];
    }

    /**
     * @return array{year: int, months: list<array{month: int, shrink: string, shrinkPercent: string|null, processCount: int, includeInTrend: bool}>}
     */
    private function reportMonthlyShrink(?int $craftsmanId): array
    {
        $activity = $this->reportMonthlyActivity($craftsmanId);

        return [
            'year' => $activity['year'],
            'months' => array_map(
                fn (array $month): array => [
                    'month' => $month['month'],
                    'shrink' => $month['shrink'],
                    'shrinkPercent' => $month['shrinkPercent'],
                    'processCount' => $month['documentCount'],
                    'includeInTrend' => $month['includeInTrend'],
                ],
                $activity['months'],
            ),
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     months: list<array{
     *         month: int,
     *         documentCount: int,
     *         submitMaterial: string,
     *         spkWeight: string,
     *         resultMaterial: string,
     *         shrink: string,
     *         shrinkPercent: string|null,
     *         includeInTrend: bool
     *     }>
     * }
     */
    private function reportMonthlyActivity(?int $craftsmanId): array
    {
        $today = now();
        $year = (int) $today->year;

        $documents = Coran::query()
            ->notDeleted()
            ->with([
                'details' => fn ($query) => $query
                    ->notDeleted()
                    ->with([
                        'production' => fn ($productionQuery) => $productionQuery
                            ->notDeleted()
                            ->select(['row_id', 'spk_no']),
                    ]),
            ])
            ->whereIn('status', $this->approvedReportStatuses())
            ->when($craftsmanId !== null, fn ($query) => $query->where('craftsman_id', $craftsmanId))
            ->whereYear('trans_date', $year)
            ->whereDate('trans_date', '<=', $today->toDateString())
            ->get(['row_id', 'doc_no', 'trans_date', 'craftsman_id', 'status', 'weight', 'shrink', 'submit_material_rosegold', 'submit_material_whitegold', 'submit_material_yellowgold', 'result_material_rosegold', 'result_material_whitegold', 'result_material_yellowgold']);

        $byMonth = [];

        foreach ($documents as $document) {
            $transDate = $document->trans_date;

            if ($transDate === null) {
                continue;
            }

            $month = (int) $transDate->month;
            $row = $this->toReportRow($document, []);

            if (! isset($byMonth[$month])) {
                $byMonth[$month] = [
                    'documentCount' => 0,
                    'submitMaterial' => 0.0,
                    'spkWeight' => 0.0,
                    'resultMaterial' => 0.0,
                    'shrink' => 0.0,
                ];
            }

            $byMonth[$month]['documentCount']++;
            $byMonth[$month]['submitMaterial'] += $row['submitMaterial'];
            $byMonth[$month]['spkWeight'] += $row['spkWeight'];
            $byMonth[$month]['resultMaterial'] += $row['resultMaterial'];
            $byMonth[$month]['shrink'] += $row['shrink'];
        }

        $months = [];
        $currentMonth = (int) $today->month;

        for ($month = 1; $month <= 12; $month++) {
            $bucket = $byMonth[$month] ?? null;
            $includeInTrend = $month <= $currentMonth;

            $submitMaterial = $includeInTrend ? (float) ($bucket['submitMaterial'] ?? 0) : 0.0;
            $shrink = $includeInTrend ? (float) ($bucket['shrink'] ?? 0) : 0.0;

            $months[] = [
                'month' => $month,
                'documentCount' => $includeInTrend ? (int) ($bucket['documentCount'] ?? 0) : 0,
                'submitMaterial' => number_format($submitMaterial, 2, '.', ''),
                'spkWeight' => number_format($includeInTrend ? (float) ($bucket['spkWeight'] ?? 0) : 0, 2, '.', ''),
                'resultMaterial' => number_format($includeInTrend ? (float) ($bucket['resultMaterial'] ?? 0) : 0, 2, '.', ''),
                'shrink' => number_format($shrink, 2, '.', ''),
                'shrinkPercent' => $includeInTrend && abs($submitMaterial) >= 0.0005
                    ? $this->formatGainAwarePercent($shrink / $submitMaterial * 100)
                    : null,
                'includeInTrend' => $includeInTrend,
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
        ];
    }

    /**
     * @return list<array{statusKey: string, label: string, documentCount: int}>
     */
    private function reportByStatus(?int $craftsmanId, string $dateFrom, string $dateTo): array
    {
        $approval = app(CoranApprovalService::class);
        $labels = [
            'open' => 'Open / Draft',
            'submitted' => 'Pengajuan',
            'manager' => 'Serahkan ke PPIC',
            'done' => 'Done',
        ];

        $documents = Coran::query()
            ->notDeleted()
            ->whereDate('trans_date', '>=', $dateFrom)
            ->whereDate('trans_date', '<=', $dateTo)
            ->when($craftsmanId !== null, fn ($query) => $query->where('craftsman_id', $craftsmanId))
            ->get(['row_id', 'status']);

        $counts = array_fill_keys(array_keys($labels), 0);

        foreach ($documents as $document) {
            if ($approval->isDone($document)) {
                $counts['done']++;
            } elseif ($approval->isPendingComplete($document)) {
                $counts['manager']++;
            } elseif ($approval->isPendingManager($document)) {
                $counts['submitted']++;
            } else {
                $counts['open']++;
            }
        }

        $result = [];

        foreach ($labels as $key => $label) {
            $result[] = [
                'statusKey' => $key,
                'label' => $label,
                'documentCount' => $counts[$key],
            ];
        }

        return $result;
    }

    /**
     * @param  array{id: int, docNo: string|null, transDate: string|null, craftsmanName: string|null, spkCount: int, spkNos: list<string>, submitMaterial: float, spkWeight: float, resultMaterial: float, shrink: float, shrinkRatio: float|null, hasilRatio: float|null, sisaRatio: float|null, qcNotOkCount: int, qcDetailCount: int}  $row
     * @return array<string, mixed>
     */
    private function toPageRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'docNo' => $row['docNo'],
            'transDate' => $row['transDate'],
            'craftsmanName' => $row['craftsmanName'],
            'spkCount' => $row['spkCount'],
            'spkNos' => $row['spkNos'],
            'submitMaterial' => $this->formatGainAwareDecimal($row['submitMaterial']),
            'spkWeight' => $this->formatGainAwareDecimal($row['spkWeight']),
            'resultMaterial' => $this->formatGainAwareDecimal($row['resultMaterial']),
            'shrink' => $this->formatGainAwareDecimal($row['shrink']),
            'shrinkPercent' => $row['shrinkRatio'] === null
                ? null
                : $this->formatGainAwarePercent($row['shrinkRatio'] * 100),
            'hasilPercent' => $row['hasilRatio'] === null
                ? null
                : $this->formatGainAwarePercent($row['hasilRatio'] * 100),
            'sisaPercent' => $row['sisaRatio'] === null
                ? null
                : $this->formatGainAwarePercent($row['sisaRatio'] * 100),
            'qcNotOkCount' => $row['qcNotOkCount'],
            'qcDetailCount' => $row['qcDetailCount'],
        ];
    }

    /**
     * @return array{pending: int, inProgress: int, completed: int}
     */
    private function spkStatusCounts(): array
    {
        return [
            'pending' => Production::query()
                ->tap(fn (Builder $builder) => $this->spkEligibility->applyEligibleScope($builder))
                ->count(),
            'inProgress' => Production::query()
                ->tap(fn (Builder $builder) => $this->spkEligibility->applyInProgressScope($builder))
                ->count(),
            'completed' => Production::query()
                ->tap(fn (Builder $builder) => $this->spkEligibility->applyCompletedScope($builder))
                ->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function approvedReportStatuses(): array
    {
        return [
            CoranApprovalService::STATUS_MANAGER,
            Coran::STATUS_DONE,
        ];
    }

    private function totalSubmitMaterial(Coran $coran): float
    {
        return collect([
            $coran->submit_material_rosegold,
            $coran->submit_material_whitegold,
            $coran->submit_material_yellowgold,
        ])->sum(fn (mixed $value): float => (float) ($value ?? 0));
    }

    private function totalResultMaterial(Coran $coran): float
    {
        return collect([
            $coran->result_material_rosegold,
            $coran->result_material_whitegold,
            $coran->result_material_yellowgold,
        ])->sum(fn (mixed $value): float => (float) ($value ?? 0));
    }

    /**
     * @param  iterable<int, CoranSpk>  $details
     */
    private function sumDetailWeights(iterable $details): float
    {
        $total = 0.0;

        foreach ($details as $detail) {
            $total += (float) ($detail->weight ?? 0);
        }

        return $total;
    }

    private function detailHasNok(CoranSpk $detail): bool
    {
        $statuses = [
            CoranSpk::normalizeInputStatus($detail->status_rosegold),
            CoranSpk::normalizeInputStatus($detail->status_whitegold),
            CoranSpk::normalizeInputStatus($detail->status_yellowgold),
            CoranSpk::normalizeInputStatus($detail->status),
        ];

        return collect($statuses)->contains(CoranSpk::STATUS_NOK);
    }

    /**
     * @param  list<int>  $craftsmanIds
     * @return array<int, string>
     */
    private function resolveCraftsmanNames(array $craftsmanIds): array
    {
        $craftsmanIds = array_values(array_unique(array_filter($craftsmanIds)));

        if ($craftsmanIds === [] || ! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        return DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $craftsmanIds)
            ->pluck('name', 'row_id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    private function formatGainAwareDecimal(mixed $value, int $precision = 2): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        if ($number < -0.0005) {
            return '+'.number_format(abs($number), $precision, '.', '');
        }

        return number_format($number, $precision, '.', '');
    }

    private function formatGainAwarePercent(float $percent): string
    {
        if ($percent < -0.0005) {
            return '+'.number_format(abs($percent), 2, '.', '').'%';
        }

        return number_format($percent, 2, '.', '').'%';
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
