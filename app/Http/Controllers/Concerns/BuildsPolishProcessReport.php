<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\ExportPolishProcessReportRequest;
use App\Models\PolishFinishedGood;
use App\Models\PolishFrame;
use App\Support\FinishingReportExport;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait BuildsPolishProcessReport
{
    /**
     * @return class-string<PolishFrame|PolishFinishedGood>
     */
    abstract protected function polishProcessReportModelClass(): string;

    abstract protected function polishProcessReportTable(): string;

    abstract protected function polishProcessReportExportTitle(): string;

    abstract protected function polishProcessReportExportPrefix(): string;

    abstract protected function polishProcessReportInertiaPage(): string;

    abstract protected function polishProcessReportIncludesProcessName(): bool;

    /**
     * Download the approved process report as an Excel file, ordered by craftsman name.
     */
    public function export(ExportPolishProcessReportRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        $craftsmanId = isset($validated['craftsman']) ? (int) $validated['craftsman'] : null;
        $dateFrom = (string) $validated['date_from'];
        $dateTo = (string) $validated['date_to'];
        $rows = $this->approvedPolishProcessReportRows($craftsmanId, $dateFrom, $dateTo);

        $craftsmanName = $craftsmanId !== null
            ? ($this->resolveCraftsmanName($craftsmanId) ?? "Pengrajin {$craftsmanId}")
            : null;

        $fileNameParts = array_filter([
            $this->polishProcessReportExportPrefix(),
            $craftsmanName !== null ? Str::slug($craftsmanName) : null,
            "{$dateFrom}_{$dateTo}",
        ]);

        return Excel::download(
            new FinishingReportExport(
                $rows,
                craftsmanLabel: $craftsmanName ?? 'All',
                dateFrom: CarbonImmutable::parse($dateFrom),
                dateTo: CarbonImmutable::parse($dateTo),
                exportedAt: now(),
                reportTitle: $this->polishProcessReportExportTitle(),
            ),
            implode('-', $fileNameParts).'.xlsx',
        );
    }

    /**
     * Display the approved process report page.
     */
    public function report(Request $request): Response
    {
        $craftsmanId = $this->resolveCraftsmanFilters($request->input('craftsman'))[0] ?? null;
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString())
            ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString())
            ?? now()->format('Y-m-d');

        if ($dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $rows = $this->approvedPolishProcessReportRows($craftsmanId, $dateFrom, $dateTo);

        return Inertia::render($this->polishProcessReportInertiaPage(), [
            'filters' => [
                'craftsman' => $craftsmanId !== null ? (string) $craftsmanId : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'craftsmanOptions' => $this->craftsmanOptions(),
            'summary' => $this->polishProcessReportSummary($rows),
            'monthlyShrink' => $this->polishProcessReportMonthlyShrink($craftsmanId),
            'byCraftsman' => $this->polishProcessReportByCraftsman($rows),
            'bySkuCategory' => $this->polishProcessReportBySkuCategory($rows),
            'rows' => array_map(fn (array $row): array => $this->toPolishProcessReportPageRow($row), $rows),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function polishProcessReportApprovedStatuses(): array
    {
        return [
            ...$this->statusFilterCodes()['ppic'],
            ...$this->statusFilterCodes()['done'],
        ];
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     docNo: string|null,
     *     sendCraftsmanDate: DateTimeInterface|null,
     *     receivedCraftsmanDate: DateTimeInterface|null,
     *     craftsmanName: string|null,
     *     spkNo: string|null,
     *     item: string|null,
     *     processName: string|null,
     *     itemCategory: string|null,
     *     skuCategory: string|null,
     *     startWeight: float|null,
     *     submitMaterial: float|null,
     *     finishWeight: float|null,
     *     resultMaterial: float|null,
     *     shrink: float|null,
     *     shrinkRatio: float|null,
     *     qcStatus: string|null,
     *     qcNotes: string|null,
     *     workDuration: string|null,
     *     workMinutes: int|null,
     *     notes: string|null
     * }>
     */
    private function approvedPolishProcessReportRows(?int $craftsmanId, string $dateFrom, string $dateTo): array
    {
        $modelClass = $this->polishProcessReportModelClass();

        /** @var Builder<PolishFrame|PolishFinishedGood> $query */
        $query = $modelClass::query()
            ->notDeleted()
            ->with([
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->with($this->productionSpkInfoRelations())
                    ->select($this->productionSpkInfoColumns()),
            ])
            ->whereIn('status', $this->polishProcessReportApprovedStatuses())
            ->whereDate('send_craftsman_date', '>=', $dateFrom)
            ->whereDate('send_craftsman_date', '<=', $dateTo)
            ->when($craftsmanId !== null, fn ($builder) => $builder->where('craftsman_id', $craftsmanId))
            ->orderBy('send_craftsman_date')
            ->orderBy('row_id');

        $documents = $query->get();
        $craftsmanNames = $this->resolveCraftsmanNames(array_values($documents->pluck('craftsman_id')->all()));

        return $documents
            ->map(fn (Model $document): array => $this->toPolishProcessExportRow($document, $craftsmanNames))
            ->sortBy(fn (array $row): array => [
                $row['craftsmanName'] === null ? 1 : 0,
                mb_strtolower($row['craftsmanName'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{year: int, months: list<array{month: int, shrink: string, shrinkPercent: string|null, processCount: int, includeInTrend: bool}>}
     */
    private function polishProcessReportMonthlyShrink(?int $craftsmanId): array
    {
        $today = now();
        $year = (int) $today->year;
        $table = $this->polishProcessReportTable();
        $statuses = $this->polishProcessReportApprovedStatuses();

        $totals = DB::connection('third')
            ->table($table)
            ->where('is_deleted', 0)
            ->whereIn('status', $statuses)
            ->when($craftsmanId !== null, fn ($query) => $query->where('craftsman_id', $craftsmanId))
            ->whereYear('send_craftsman_date', $year)
            ->whereDate('send_craftsman_date', '<=', $today->toDateString())
            ->selectRaw('MONTH(send_craftsman_date) as month, COALESCE(SUM(shrink), 0) as shrink, COALESCE(SUM(start_weight), 0) as gold_in, COUNT(*) as process_count')
            ->groupByRaw('MONTH(send_craftsman_date)')
            ->get();

        $shrinkByMonth = [];
        $goldInByMonth = [];
        $processCountByMonth = [];

        foreach ($totals as $total) {
            $shrinkByMonth[(int) $total->month] = (float) $total->shrink;
            $goldInByMonth[(int) $total->month] = (float) $total->gold_in;
            $processCountByMonth[(int) $total->month] = (int) $total->process_count;
        }

        $months = [];
        $currentMonth = (int) $today->month;

        for ($month = 1; $month <= 12; $month++) {
            $shrink = $shrinkByMonth[$month] ?? 0;
            $goldIn = $goldInByMonth[$month] ?? 0;
            $includeInTrend = $month <= $currentMonth;

            $months[] = [
                'month' => $month,
                'shrink' => number_format($includeInTrend ? $shrink : 0, 2, '.', ''),
                'shrinkPercent' => $includeInTrend && abs($goldIn) >= 0.0005
                    ? $this->formatGainAwarePercent($shrink / $goldIn * 100)
                    : null,
                'processCount' => $includeInTrend ? ($processCountByMonth[$month] ?? 0) : 0,
                'includeInTrend' => $includeInTrend,
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
        ];
    }

    /**
     * @param  array<int, array{craftsmanName: string|null, startWeight: float|null, submitMaterial: float|null, finishWeight: float|null, resultMaterial: float|null, shrink: float|null, qcStatus: string|null, workMinutes: int|null}>  $rows
     * @return array{
     *     documentCount: int,
     *     craftsmanCount: int,
     *     startWeight: string,
     *     submitMaterial: string,
     *     finishWeight: string,
     *     resultMaterial: string,
     *     shrink: string,
     *     shrinkPercent: string|null,
     *     qcNotOkCount: int,
     *     workMinutes: int,
     *     workMinutesCount: int
     * }
     */
    private function polishProcessReportSummary(array $rows): array
    {
        return [
            ...$this->aggregatePolishProcessReportRows($rows),
            'craftsmanCount' => count(array_unique(array_filter(array_column($rows, 'craftsmanName')))),
        ];
    }

    /**
     * @param  array<int, array{craftsmanName: string|null, startWeight: float|null, submitMaterial: float|null, finishWeight: float|null, resultMaterial: float|null, shrink: float|null, qcStatus: string|null, workMinutes: int|null}>  $rows
     * @return list<array{
     *     craftsmanName: string,
     *     documentCount: int,
     *     startWeight: string,
     *     submitMaterial: string,
     *     finishWeight: string,
     *     resultMaterial: string,
     *     shrink: string,
     *     shrinkPercent: string|null,
     *     qcNotOkCount: int,
     *     workMinutes: int,
     *     workMinutesCount: int
     * }>
     */
    private function polishProcessReportByCraftsman(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['craftsmanName'] ?? 'Tanpa pengrajin'][] = $row;
        }

        $result = [];

        foreach ($groups as $craftsmanName => $groupRows) {
            $result[] = [
                'craftsmanName' => (string) $craftsmanName,
                ...$this->aggregatePolishProcessReportRows($groupRows),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{skuCategory: string|null, startWeight: float|null, submitMaterial: float|null, finishWeight: float|null, resultMaterial: float|null, shrink: float|null, qcStatus: string|null, workMinutes: int|null}>  $rows
     * @return list<array{
     *     skuCategory: string,
     *     documentCount: int,
     *     startWeight: string,
     *     submitMaterial: string,
     *     finishWeight: string,
     *     resultMaterial: string,
     *     shrink: string,
     *     shrinkPercent: string|null,
     *     qcNotOkCount: int,
     *     workMinutes: int,
     *     workMinutesCount: int
     * }>
     */
    private function polishProcessReportBySkuCategory(array $rows): array
    {
        $uncategorizedLabel = 'Tanpa kategori SKU';
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['skuCategory'] ?? $uncategorizedLabel][] = $row;
        }

        uksort($groups, fn (string|int $left, string|int $right): int => [
            $left === $uncategorizedLabel ? 1 : 0,
            (string) $left,
        ] <=> [
            $right === $uncategorizedLabel ? 1 : 0,
            (string) $right,
        ]);

        $result = [];

        foreach ($groups as $skuCategory => $groupRows) {
            $result[] = [
                'skuCategory' => (string) $skuCategory,
                ...$this->aggregatePolishProcessReportRows($groupRows),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{startWeight: float|null, submitMaterial: float|null, finishWeight: float|null, resultMaterial: float|null, shrink: float|null, qcStatus: string|null, workMinutes: int|null}>  $rows
     * @return array{
     *     documentCount: int,
     *     startWeight: string,
     *     submitMaterial: string,
     *     finishWeight: string,
     *     resultMaterial: string,
     *     shrink: string,
     *     shrinkPercent: string|null,
     *     qcNotOkCount: int,
     *     workMinutes: int,
     *     workMinutesCount: int
     * }
     */
    private function aggregatePolishProcessReportRows(array $rows): array
    {
        $startWeight = array_sum(array_map(fn (array $row): float => $row['startWeight'] ?? 0.0, $rows));
        $submitMaterial = array_sum(array_map(fn (array $row): float => $row['submitMaterial'] ?? 0.0, $rows));
        $shrink = array_sum(array_map(fn (array $row): float => $row['shrink'] ?? 0.0, $rows));
        $goldIn = $startWeight + $submitMaterial;

        return [
            'documentCount' => count($rows),
            'startWeight' => number_format($startWeight, 2, '.', ''),
            'submitMaterial' => number_format($submitMaterial, 2, '.', ''),
            'finishWeight' => number_format(array_sum(array_map(fn (array $row): float => $row['finishWeight'] ?? 0.0, $rows)), 2, '.', ''),
            'resultMaterial' => number_format(array_sum(array_map(fn (array $row): float => $row['resultMaterial'] ?? 0.0, $rows)), 2, '.', ''),
            'shrink' => $this->formatGainAwareDecimal($shrink) ?? '0.00',
            'shrinkPercent' => abs($goldIn) >= 0.0005
                ? $this->formatGainAwarePercent($shrink / $goldIn * 100)
                : null,
            'qcNotOkCount' => count(array_filter($rows, fn (array $row): bool => $row['qcStatus'] === 'NOT OK')),
            'workMinutes' => array_sum(array_map(fn (array $row): int => $row['workMinutes'] ?? 0, $rows)),
            'workMinutesCount' => count(array_filter($rows, fn (array $row): bool => $row['workMinutes'] !== null)),
        ];
    }

    /**
     * @param  array{
     *     id: int,
     *     docNo: string|null,
     *     sendCraftsmanDate: DateTimeInterface|null,
     *     receivedCraftsmanDate: DateTimeInterface|null,
     *     craftsmanName: string|null,
     *     spkNo: string|null,
     *     item: string|null,
     *     processName: string|null,
     *     itemCategory: string|null,
     *     skuCategory: string|null,
     *     startWeight: float|null,
     *     submitMaterial: float|null,
     *     finishWeight: float|null,
     *     resultMaterial: float|null,
     *     shrink: float|null,
     *     shrinkRatio: float|null,
     *     qcStatus: string|null,
     *     qcNotes: string|null,
     *     workDuration: string|null,
     *     workMinutes: int|null,
     *     notes: string|null
     * }  $row
     * @return array<string, int|string|null>
     */
    private function toPolishProcessReportPageRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'docNo' => $row['docNo'],
            'sendCraftsmanDate' => $row['sendCraftsmanDate']?->format('Y-m-d H:i'),
            'receivedCraftsmanDate' => $row['receivedCraftsmanDate']?->format('Y-m-d H:i'),
            'craftsmanName' => $row['craftsmanName'],
            'spkNo' => $row['spkNo'],
            'item' => $row['item'],
            'processName' => $row['processName'],
            'itemCategory' => $row['itemCategory'],
            'skuCategory' => $row['skuCategory'],
            'startWeight' => $this->formatDecimal($row['startWeight']),
            'submitMaterial' => $this->formatDecimal($row['submitMaterial']),
            'finishWeight' => $this->formatDecimal($row['finishWeight']),
            'resultMaterial' => $this->formatDecimal($row['resultMaterial']),
            'shrink' => $this->formatGainAwareDecimal($row['shrink']),
            'shrinkPercent' => $row['shrinkRatio'] !== null
                ? $this->formatGainAwarePercent($row['shrinkRatio'] * 100)
                : null,
            'qcStatus' => $row['qcStatus'],
            'qcNotes' => $row['qcNotes'],
            'workDuration' => $row['workDuration'],
            'notes' => $row['notes'],
        ];
    }

    /**
     * @param  PolishFrame|PolishFinishedGood  $document
     * @param  array<int, string>  $craftsmanNames
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     sendCraftsmanDate: DateTimeInterface|null,
     *     receivedCraftsmanDate: DateTimeInterface|null,
     *     craftsmanName: string|null,
     *     spkNo: string|null,
     *     item: string|null,
     *     processName: string|null,
     *     itemCategory: string|null,
     *     skuCategory: string|null,
     *     startWeight: float|null,
     *     submitMaterial: float|null,
     *     finishWeight: float|null,
     *     resultMaterial: float|null,
     *     shrink: float|null,
     *     shrinkRatio: float|null,
     *     qcStatus: string|null,
     *     qcNotes: string|null,
     *     workDuration: string|null,
     *     workMinutes: int|null,
     *     notes: string|null
     * }
     */
    private function toPolishProcessExportRow(Model $document, array $craftsmanNames): array
    {
        $craftsmanId = filled($document->craftsman_id) ? (int) $document->craftsman_id : 0;
        $startWeight = $this->toFloat($document->start_weight);
        $submitMaterial = 0.0;
        $shrink = $this->polishProcessHasMissingWeight($document) ? 0.0 : ($this->toFloat($document->shrink) ?? 0.0);
        $goldIn = ($startWeight ?? 0.0) + $submitMaterial;
        $skuFields = $this->productionSkuFields($document->production);
        $skuCategory = $document->production?->categoryPrefix?->displayName();
        $sendAt = $document->send_craftsman_date;
        $receivedAt = $document->received_craftsman_date;
        $workMinutes = $this->polishProcessWorkMinutes($sendAt, $receivedAt);

        $itemLines = array_filter([
            implode(' | ', array_filter([$skuFields['typeCode'], $skuFields['productItemName']])),
            $skuFields['skuCode'] ?? '',
            $skuFields['skuCode'] === null ? ($skuFields['itemDescription'] ?? '') : '',
        ], fn (string $line): bool => $line !== '');

        $processName = null;

        if ($this->polishProcessReportIncludesProcessName() && filled($document->process_name)) {
            $processName = (string) $document->process_name;
        }

        return [
            'id' => (int) $document->getKey(),
            'docNo' => $document->doc_no,
            'sendCraftsmanDate' => $sendAt,
            'receivedCraftsmanDate' => $receivedAt,
            'craftsmanName' => $craftsmanId > 0
                ? ($craftsmanNames[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                : null,
            'spkNo' => $document->production?->spk_no,
            'item' => $itemLines === [] ? null : implode("\n", $itemLines),
            'processName' => $processName,
            'itemCategory' => null,
            'skuCategory' => $skuCategory !== null && $skuCategory !== '-' ? $skuCategory : null,
            'startWeight' => $startWeight,
            'submitMaterial' => $submitMaterial,
            'finishWeight' => $this->toFloat($document->finish_weight),
            'resultMaterial' => 0.0,
            'shrink' => round($shrink, 2),
            'shrinkRatio' => abs($goldIn) >= 0.0005 ? round($shrink / $goldIn, 4) : null,
            'qcStatus' => $this->polishProcessQcStatus($document->status_item ?? null),
            'qcNotes' => null,
            'workDuration' => $workMinutes !== null ? $this->polishProcessFormatWorkDuration($workMinutes) : null,
            'workMinutes' => $workMinutes,
            'notes' => filled($document->notes) ? (string) $document->notes : null,
        ];
    }

    /**
     * @param  PolishFrame|PolishFinishedGood  $document
     */
    private function polishProcessHasMissingWeight(Model $document): bool
    {
        return abs($this->toFloat($document->start_weight) ?? 0.0) < 0.0005
            || abs($this->toFloat($document->finish_weight) ?? 0.0) < 0.0005;
    }

    private function polishProcessQcStatus(mixed $statusItem): ?string
    {
        if (! filled($statusItem)) {
            return null;
        }

        return strtoupper(trim((string) $statusItem)) === 'NOK' ? 'NOT OK' : 'OK';
    }

    private function polishProcessWorkMinutes(?DateTimeInterface $sendAt, ?DateTimeInterface $receivedAt): ?int
    {
        if ($sendAt === null || $receivedAt === null || $receivedAt < $sendAt) {
            return null;
        }

        return intdiv($receivedAt->getTimestamp() - $sendAt->getTimestamp(), 60);
    }

    private function polishProcessFormatWorkDuration(int $totalMinutes): string
    {
        if ($totalMinutes === 0) {
            return '< 1 menit';
        }

        $days = intdiv($totalMinutes, 1440);
        $hours = intdiv($totalMinutes % 1440, 60);
        $minutes = $totalMinutes % 60;

        return implode(' ', array_filter([
            $days > 0 ? "{$days} hari" : null,
            $hours > 0 ? "{$hours} jam" : null,
            $minutes > 0 ? "{$minutes} menit" : null,
        ]));
    }

    private function resolveCraftsmanName(mixed $craftsmanId): ?string
    {
        $id = filled($craftsmanId) ? (int) $craftsmanId : 0;

        if ($id <= 0) {
            return null;
        }

        return $this->resolveCraftsmanNames([$id])[$id] ?? null;
    }

    private function formatGainAwarePercent(float $percent): string
    {
        if ($percent < -0.0005) {
            return '+'.number_format(abs($percent), 2, '.', '').'%';
        }

        return number_format($percent, 2, '.', '').'%';
    }
}
