<?php

namespace App\Support;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkProductionControlReport
{
    /**
     * @return array{
     *     leadTime: array{
     *         startDate: string|null,
     *         endDate: string|null,
     *         durationLabel: string|null,
     *         durationDays: float|null,
     *         estimatedDays: float|null,
     *         varianceDays: float|null,
     *         varianceLabel: string|null
     *     },
     *     idleTimes: list<array{
     *         no: int,
     *         fromProcess: string,
     *         toProcess: string,
     *         fromDate: string|null,
     *         toDate: string|null,
     *         idleLabel: string|null,
     *         idleMinutes: int|null
     *     }>,
     *     yieldPlanning: array{
     *         planningWeight: string|null,
     *         endWeight: string|null,
     *         yieldPercent: string|null,
     *         goldUsed: string|null,
     *         goldYieldPercent: string|null
     *     }
     * }
     */
    public function forProduction(Production $production, ?SpkGoldReport $goldReport = null): array
    {
        $spkId = (int) $production->row_id;
        $timeline = $this->resolveProcessTimeline($spkId);
        $startAt = $this->nullableDate($production->created_date);
        $endAt = $timeline !== []
            ? ($timeline[array_key_last($timeline)]['endAt'] ?? $timeline[array_key_last($timeline)]['startAt'])
            : $this->nullableDate($production->modified_date);

        $durationMinutes = $this->resolveDurationMinutes($startAt, $endAt);
        $durationDays = $durationMinutes !== null
            ? round($durationMinutes / (60 * 24), 2)
            : null;
        $estimatedDays = $this->nullableFloat($production->work_estimated);
        $varianceDays = $durationDays !== null && $estimatedDays !== null
            ? round($durationDays - $estimatedDays, 2)
            : null;

        $gold = ($goldReport ?? new SpkGoldReport)->totalsForSpk($spkId);
        $planningWeight = $this->nullableFloat($production->gold_weight);
        $endWeight = $this->nullableFloat($production->last_weight);
        $goldUsed = $gold['used'];

        return [
            'leadTime' => [
                'startDate' => $startAt?->format('d-M-Y H:i'),
                'endDate' => $endAt?->format('d-M-Y H:i'),
                'durationLabel' => $this->formatDuration($durationMinutes),
                'durationDays' => $durationDays,
                'estimatedDays' => $estimatedDays,
                'varianceDays' => $varianceDays,
                'varianceLabel' => $this->formatVarianceLabel($varianceDays),
            ],
            'idleTimes' => $this->resolveIdleTimes($timeline),
            'yieldPlanning' => [
                'planningWeight' => $this->formatNullableWeight($planningWeight),
                'endWeight' => $this->formatNullableWeight($endWeight),
                'yieldPercent' => $this->formatYieldPercent($endWeight, $planningWeight),
                'goldUsed' => $this->formatNullableWeight($goldUsed),
                'goldYieldPercent' => $this->formatYieldPercent($goldUsed, $planningWeight),
            ],
        ];
    }

    /**
     * @return list<array{process: string, startAt: Carbon|null, endAt: Carbon|null, sortDate: string}>
     */
    private function resolveProcessTimeline(int $spkId): array
    {
        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);
        $events = [];

        foreach ($sources as $source) {
            $table = $source['table'];

            if (! Schema::connection('third')->hasTable($table)) {
                continue;
            }

            $query = DB::connection('third')
                ->table($table)
                ->where('spk_id', $spkId);

            if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $hasReceived = Schema::connection('third')->hasColumn($table, 'received_craftsman_date');
            $hasDateFrom = Schema::connection('third')->hasColumn($table, 'date_from');
            $hasDateTo = Schema::connection('third')->hasColumn($table, 'date_to');

            foreach ($query->get() as $record) {
                $startAt = $this->nullableDate($record->{$source['date_column']} ?? null);

                if ($startAt === null && $hasDateFrom) {
                    $startAt = $this->nullableDate($record->date_from ?? null);
                }

                $endAt = $hasReceived
                    ? $this->nullableDate($record->received_craftsman_date ?? null)
                    : null;

                if ($endAt === null && $hasDateTo) {
                    $endAt = $this->nullableDate($record->date_to ?? null);
                }

                if ($startAt === null && $endAt === null) {
                    continue;
                }

                $events[] = [
                    'process' => $source['label'],
                    'startAt' => $startAt,
                    'endAt' => $endAt,
                    'sortDate' => ($startAt ?? $endAt)?->format('Y-m-d H:i:s') ?? '9999-12-31',
                ];
            }
        }

        usort($events, fn (array $left, array $right): int => strcmp($left['sortDate'], $right['sortDate']));

        return $events;
    }

    /**
     * @param  list<array{process: string, startAt: Carbon|null, endAt: Carbon|null, sortDate: string}>  $timeline
     * @return list<array{
     *     no: int,
     *     fromProcess: string,
     *     toProcess: string,
     *     fromDate: string|null,
     *     toDate: string|null,
     *     idleLabel: string|null,
     *     idleMinutes: int|null
     * }>
     */
    private function resolveIdleTimes(array $timeline): array
    {
        $idleTimes = [];

        for ($index = 0; $index < count($timeline) - 1; $index++) {
            $current = $timeline[$index];
            $next = $timeline[$index + 1];
            $fromAt = $current['endAt'] ?? $current['startAt'];
            $toAt = $next['startAt'] ?? $next['endAt'];
            $idleMinutes = $this->resolveDurationMinutes($fromAt, $toAt);

            $idleTimes[] = [
                'no' => $index + 1,
                'fromProcess' => $current['process'],
                'toProcess' => $next['process'],
                'fromDate' => $fromAt?->format('d-M-Y H:i'),
                'toDate' => $toAt?->format('d-M-Y H:i'),
                'idleLabel' => $this->formatDuration($idleMinutes),
                'idleMinutes' => $idleMinutes,
            ];
        }

        return $idleTimes;
    }

    private function formatVarianceLabel(?float $varianceDays): ?string
    {
        if ($varianceDays === null) {
            return null;
        }

        if (abs($varianceDays) < 0.005) {
            return 'Sesuai estimasi';
        }

        $absolute = number_format(abs($varianceDays), 2, '.', '');

        return $varianceDays > 0
            ? "+{$absolute} hari vs estimasi"
            : "-{$absolute} hari vs estimasi";
    }

    private function formatYieldPercent(?float $actual, ?float $planning): ?string
    {
        if ($actual === null || $planning === null || abs($planning) < 0.0005) {
            return null;
        }

        return number_format(($actual / $planning) * 100, 2, '.', '');
    }

    private function resolveDurationMinutes(?Carbon $startAt, ?Carbon $endAt): ?int
    {
        if ($startAt === null || $endAt === null || $endAt->lt($startAt)) {
            return null;
        }

        return (int) $startAt->diffInMinutes($endAt);
    }

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes === 0) {
            return '< 1 menit';
        }

        $days = intdiv($minutes, 60 * 24);
        $hours = intdiv($minutes % (60 * 24), 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days} hari";
        }

        if ($hours > 0) {
            $parts[] = "{$hours} jam";
        }

        if ($remainingMinutes > 0 && $days === 0) {
            $parts[] = "{$remainingMinutes} menit";
        }

        return $parts === [] ? '< 1 menit' : implode(' ', $parts);
    }

    private function nullableDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
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

    private function formatNullableWeight(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->formatWeight($value);
    }
}
