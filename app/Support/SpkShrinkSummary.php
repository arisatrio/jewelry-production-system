<?php

namespace App\Support;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkShrinkSummary
{
    public function __construct(
        private readonly SpkGoldReport $goldReport = new SpkGoldReport,
    ) {}

    /**
     * @return array{
     *     rows: list<array{
     *         no: int,
     *         process: string,
     *         setorDate: string,
     *         startWeight: string|null,
     *         endWeight: string|null,
     *         shrink: string,
     *         shrinkPercent: string|null,
     *         tolerance: string|null,
     *         toleranceStatus: string|null
     *     }>,
     *     planningWeight: string|null,
     *     startWeight: string|null,
     *     endWeight: string|null,
     *     goldIssued: string|null,
     *     goldReturned: string|null,
     *     goldUsed: string|null,
     *     goldMaterials: list<array{no: int, name: string, type: string, weight: string, notes: string|null}>,
     *     totalShrink: string,
     *     totalShrinkPercent: string|null,
     *     totalLost: string|null,
     *     totalLostPercent: string|null,
     *     totalLabel: string
     * }
     */
    public function forProduction(Production $production): array
    {
        $spkId = (int) $production->row_id;

        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);

        $rows = [];

        foreach ($sources as $source) {
            foreach ($this->rowsForSource($spkId, $source) as $row) {
                $rows[] = $row;
            }
        }

        usort($rows, function (array $left, array $right): int {
            return strcmp($left['sortDate'], $right['sortDate']);
        });

        $numbered = [];
        $total = 0.0;

        foreach (array_values($rows) as $index => $row) {
            $total += (float) $row['shrinkValue'];
            $numbered[] = [
                'no' => $index + 1,
                'process' => $row['process'],
                'setorDate' => $row['setorDate'],
                'startWeight' => $this->formatNullableWeight($row['startWeight']),
                'endWeight' => $this->formatNullableWeight($row['endWeight']),
                'shrink' => $this->formatWeight((float) $row['shrinkValue']),
                'shrinkPercent' => $row['shrinkPercent'],
                'tolerance' => $row['tolerance'],
                'toleranceStatus' => $row['toleranceStatus'],
            ];
        }

        $planningWeight = $this->nullableFloat($production->gold_weight);
        $startWeight = $this->resolveCoranStartWeight($spkId);
        $endWeight = $this->nullableFloat($production->last_weight);
        $materialGold = $this->goldReport->totalsForSpk($spkId);
        $totalLost = $planningWeight !== null && $endWeight !== null
            ? round($planningWeight - $endWeight, 3)
            : null;

        return [
            'rows' => $numbered,
            'planningWeight' => $this->formatNullableWeight($planningWeight),
            'startWeight' => $this->formatNullableWeight($startWeight),
            'endWeight' => $this->formatNullableWeight($endWeight),
            'goldIssued' => $this->formatNullableWeight($materialGold['issued']),
            'goldReturned' => $this->formatNullableWeight($materialGold['returned']),
            'goldUsed' => $this->formatNullableWeight($materialGold['used']),
            'goldMaterials' => $materialGold['materials'],
            'totalShrink' => $this->formatWeight($total),
            'totalShrinkPercent' => $this->formatPercentOfPlanning($total, $planningWeight),
            'totalLost' => $this->formatNullableWeight($totalLost),
            'totalLostPercent' => $this->formatPercentOfPlanning($totalLost, $planningWeight),
            'totalLabel' => $this->formatWeight($total).' g',
        ];
    }

    /**
     * @param  array{table: string, label: string, shrink_column: string, date_column: string}  $source
     * @return list<array{
     *     process: string,
     *     setorDate: string,
     *     sortDate: string,
     *     startWeight: float|null,
     *     endWeight: float|null,
     *     shrinkValue: float,
     *     shrinkPercent: string|null,
     *     tolerance: string|null,
     *     toleranceStatus: string|null
     * }>
     */
    private function rowsForSource(int $spkId, array $source): array
    {
        $table = $source['table'];

        if (! Schema::connection('third')->hasTable($table)) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $hasTolerance = Schema::connection('third')->hasColumn($table, 'shrink_tolerance');
        $records = $query->get();
        $rows = [];

        foreach ($records as $record) {
            $shrink = $this->resolveShrink($record, $source['shrink_column']);

            if ($shrink === null || abs($shrink) < 0.0005) {
                continue;
            }

            $dateRaw = $record->{$source['date_column']} ?? null;
            $date = filled($dateRaw) ? Carbon::parse((string) $dateRaw) : null;
            [$startWeight, $endWeight] = $this->resolveRowWeights($record, $source['shrink_column']);
            $shrinkPercent = $this->resolveShrinkPercent($shrink, $startWeight);
            $tolerance = $hasTolerance
                ? $this->nullableFloat($record->shrink_tolerance ?? null)
                : null;
            $toleranceStatus = $this->resolveToleranceStatus($shrinkPercent, $tolerance);

            $rows[] = [
                'process' => $source['label'],
                'setorDate' => $date?->format('d-M-Y H:i') ?? '—',
                'sortDate' => $date?->format('Y-m-d H:i:s') ?? '9999-12-31',
                'startWeight' => $startWeight,
                'endWeight' => $endWeight,
                'shrinkValue' => round($shrink, 3),
                'shrinkPercent' => $shrinkPercent !== null
                    ? number_format($shrinkPercent, 2, '.', '')
                    : null,
                'tolerance' => $tolerance !== null
                    ? number_format($tolerance, 2, '.', '')
                    : null,
                'toleranceStatus' => $toleranceStatus,
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolveRowWeights(object $record, string $shrinkColumn): array
    {
        if ($shrinkColumn === 'computed_mounting') {
            return [
                $this->nullableFloat($record->total_weigth_frame_diamond ?? null),
                $this->nullableFloat($record->weight_finish_goods ?? null),
            ];
        }

        return [
            $this->nullableFloat($record->start_weight ?? null),
            $this->nullableFloat($record->finish_weight ?? null),
        ];
    }

    private function resolveShrinkPercent(float $shrink, ?float $startWeight): ?float
    {
        if ($startWeight === null || abs($startWeight) < 0.0005) {
            return null;
        }

        return round(($shrink / $startWeight) * 100, 2);
    }

    private function resolveToleranceStatus(?float $shrinkPercent, ?float $tolerance): ?string
    {
        if ($shrinkPercent === null || $tolerance === null) {
            return null;
        }

        return abs($shrinkPercent) <= abs($tolerance) + 0.005
            ? 'OK'
            : 'NOK';
    }

    private function resolveCoranStartWeight(int $spkId): ?float
    {
        if (! Schema::connection('third')->hasTable('coranspk')) {
            return null;
        }

        $query = DB::connection('third')
            ->table('coranspk')
            ->where('spk_id', $spkId)
            ->whereNotNull('weight');

        if (Schema::connection('third')->hasColumn('coranspk', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if (Schema::connection('third')->hasColumn('coranspk', 'created_date')) {
            $query->orderBy('created_date');
        }

        $weight = $query->value('weight');

        return $this->nullableFloat($weight);
    }

    private function resolveShrink(object $record, string $shrinkColumn): ?float
    {
        if ($shrinkColumn === 'computed_mounting') {
            $before = isset($record->total_weigth_frame_diamond)
                ? (float) $record->total_weigth_frame_diamond
                : null;
            $after = isset($record->weight_finish_goods)
                ? (float) $record->weight_finish_goods
                : null;

            if ($before === null || $after === null) {
                $mounting = isset($record->mounting_shrink) ? (float) $record->mounting_shrink : null;

                return $mounting;
            }

            return $before - $after;
        }

        if (! isset($record->{$shrinkColumn}) || $record->{$shrinkColumn} === null || $record->{$shrinkColumn} === '') {
            return null;
        }

        return (float) $record->{$shrinkColumn};
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

    private function formatPercentOfPlanning(?float $value, ?float $planningWeight): ?string
    {
        if ($value === null || $planningWeight === null || abs($planningWeight) < 0.0005) {
            return null;
        }

        return number_format(($value / $planningWeight) * 100, 2, '.', '');
    }
}
