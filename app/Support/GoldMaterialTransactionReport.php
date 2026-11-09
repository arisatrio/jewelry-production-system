<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoldMaterialTransactionReport
{
    /**
     * @var list<array{
     *     table: string,
     *     craftsman_column: string,
     *     date_column: string,
     *     scope: string
     * }>
     */
    private const PROCESS_SECTIONS = [
        [
            'table' => 'finishinghandmade',
            'craftsman_column' => 'craftsman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'finishing',
        ],
        [
            'table' => 'polishframe',
            'craftsman_column' => 'craftsman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'standard',
        ],
        [
            'table' => 'polishfinishedgood',
            'craftsman_column' => 'craftsman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'poles_barang_jadi',
        ],
        [
            'table' => 'diamondmounting',
            'craftsman_column' => 'craftman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'mounting',
        ],
        [
            'table' => 'finishinghandmade',
            'craftsman_column' => 'craftsman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'finishing_reparation',
        ],
        [
            'table' => 'diamondmounting',
            'craftsman_column' => 'craftman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'mounting_reparation',
        ],
        [
            'table' => 'polishfinishedgood',
            'craftsman_column' => 'craftsman_id',
            'date_column' => 'send_craftsman_date',
            'scope' => 'poles_chrome_reparation',
        ],
    ];

    public function __construct(
        private readonly GoldMaterialLedger $ledger = new GoldMaterialLedger,
    ) {}

    /**
     * @return list<array{spkNo: string, cells: list<list<string>>, totalShrink: string}>
     */
    public function rows(
        int $periodId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $eventType,
    ): array {
        $spkIds = $this->ledger->spkIdsForFilteredTransactions(
            $periodId,
            $search,
            $dateFrom,
            $dateTo,
            $eventType,
        );

        if ($spkIds === []) {
            return [];
        }

        $spkNumbers = $this->spkNumbersById($spkIds);
        $recordsBySection = [];

        foreach (self::PROCESS_SECTIONS as $section) {
            $recordsBySection[$section['scope']] = $this->recordsBySpkId($spkIds, $section);
        }

        $rows = [];

        foreach ($spkIds as $spkId) {
            $spkNo = $spkNumbers[$spkId] ?? null;

            if ($spkNo === null || $spkNo === '') {
                continue;
            }

            $blocks = [];

            foreach (self::PROCESS_SECTIONS as $section) {
                $record = $recordsBySection[$section['scope']][$spkId] ?? null;
                $blocks[] = $this->blockFromRecord($record, $section);
            }

            $totalShrink = $this->sumShrink($blocks);

            $rows[] = [
                'spkNo' => $spkNo,
                'cells' => $blocks,
                'totalShrink' => $this->formatWeight($totalShrink),
            ];
        }

        usort($rows, fn (array $left, array $right): int => strcmp($left['spkNo'], $right['spkNo']));

        return $rows;
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, string>
     */
    private function spkNumbersById(array $spkIds): array
    {
        if (
            $spkIds === []
            || ! Schema::connection('third')->hasTable('spk')
        ) {
            return [];
        }

        $query = DB::connection('third')
            ->table('spk')
            ->whereIn('row_id', $spkIds);

        if (Schema::connection('third')->hasColumn('spk', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->pluck('spk_no', 'row_id')
            ->mapWithKeys(fn (mixed $spkNo, mixed $id): array => [
                (int) $id => (string) $spkNo,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $spkIds
     * @param  array{
     *     table: string,
     *     craftsman_column: string,
     *     date_column: string,
     *     scope: string
     * }  $section
     * @return array<int, object>
     */
    private function recordsBySpkId(array $spkIds, array $section): array
    {
        $table = $section['table'];

        if ($spkIds === [] || ! Schema::connection('third')->hasTable($table)) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->whereIn('spk_id', $spkIds);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $this->applyScope($query, $table, $section['scope']);

        if (Schema::connection('third')->hasColumn($table, $section['date_column'])) {
            $query->orderByDesc($section['date_column']);
        }

        $records = $query
            ->orderByDesc('row_id')
            ->get()
            ->groupBy(fn (object $record): int => (int) ($record->spk_id ?? 0));

        $bySpk = [];

        foreach ($records as $spkId => $spkRecords) {
            if ($spkId <= 0) {
                continue;
            }

            $picked = null;

            foreach ($spkRecords as $record) {
                [, , $shrink] = $this->resolveWeights($record, $section['scope']);

                if ($shrink !== null && abs($shrink) >= 0.0005) {
                    $picked = $record;

                    break;
                }
            }

            $bySpk[$spkId] = $picked ?? $spkRecords->first();
        }

        return $bySpk;
    }

    /**
     * @param  array{
     *     table: string,
     *     craftsman_column: string,
     *     date_column: string,
     *     scope: string
     * }  $section
     * @return list<string>
     */
    private function blockFromRecord(?object $record, array $section): array
    {
        if ($record === null) {
            return $this->emptyBlock();
        }

        $dateRaw = $record->{$section['date_column']} ?? null;
        $date = filled($dateRaw)
            ? Carbon::parse((string) $dateRaw)->format('Y-m-d')
            : '';

        $craftsmanId = (int) ($record->{$section['craftsman_column']} ?? 0);
        $craftsman = $craftsmanId > 0
            ? ($this->craftsmanNames()[$craftsmanId] ?? '')
            : '';

        [$start, $end, $shrink] = $this->resolveWeights($record, $section['scope']);

        if ($date === '' && $craftsman === '' && $start === null && $end === null && $shrink === null) {
            return $this->emptyBlock();
        }

        return [
            $date,
            $craftsman,
            $this->formatWeight($start),
            $this->formatWeight($end),
            $this->formatWeight($shrink),
        ];
    }

    private function applyScope(Builder $query, string $table, string $scope): void
    {
        if (
            $table === 'finishinghandmade'
            && ($scope === 'finishing' || $scope === 'finishing_reparation')
        ) {
            $isReparation = $scope === 'finishing_reparation';

            if ($isReparation) {
                $query->where(function (Builder $inner) use ($table): void {
                    if (Schema::connection('third')->hasColumn($table, 'process_name')) {
                        $inner->where('process_name', 'Reparation');
                    }

                    if (Schema::connection('third')->hasColumn($table, 'status')) {
                        $inner->orWhere('status', 'like', 'RFH%');
                    }
                });
            } else {
                if (Schema::connection('third')->hasColumn($table, 'process_name')) {
                    $query->where(function (Builder $inner): void {
                        $inner->whereNull('process_name')
                            ->orWhere('process_name', '!=', 'Reparation');
                    });
                }

                if (Schema::connection('third')->hasColumn($table, 'status')) {
                    $query->where(function (Builder $inner): void {
                        $inner->whereNull('status')
                            ->orWhere('status', 'not like', 'RFH%');
                    });
                }
            }

            return;
        }

        if (
            $table === 'diamondmounting'
            && ($scope === 'mounting' || $scope === 'mounting_reparation')
            && Schema::connection('third')->hasColumn($table, 'process_name')
        ) {
            if ($scope === 'mounting_reparation') {
                $query->where('process_name', 'Reparation');
            } else {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('process_name')
                        ->orWhere('process_name', 'Pasang Batu');
                });
            }

            return;
        }

        if (
            $table === 'polishfinishedgood'
            && ($scope === 'poles_barang_jadi' || $scope === 'poles_chrome_reparation')
            && Schema::connection('third')->hasColumn($table, 'process_name')
        ) {
            if ($scope === 'poles_chrome_reparation') {
                $query->where('process_name', 'Reparation');
            } else {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('process_name')
                        ->orWhere('process_name', '!=', 'Reparation');
                });
            }
        }
    }

    /**
     * @return array{0: float|null, 1: float|null, 2: float|null}
     */
    private function resolveWeights(object $record, string $scope): array
    {
        if ($scope === 'mounting' || $scope === 'mounting_reparation') {
            $start = $this->toFloat($record->weight_frame ?? null);
            $end = $this->toFloat($record->weight_finish_goods ?? null);
            $shrink = $this->toFloat($record->mounting_shrink ?? null);

            if ($shrink === null && $start !== null && $end !== null) {
                $shrink = round($start - $end, 2);
            }

            return [$start, $end, $shrink];
        }

        if ($scope === 'finishing' || $scope === 'finishing_reparation') {
            $start = $this->toFloat($record->start_weight ?? null);
            $submit = $this->toFloat($record->submit_materialgold ?? null);

            if ($start !== null && $submit !== null) {
                $start = round($start + $submit, 2);
            }

            $end = $this->toFloat($record->finish_weight ?? null);
            $shrink = $this->toFloat($record->shrink ?? null);

            if ($shrink === null && $start !== null && $end !== null) {
                $shrink = round($start - $end, 2);
            }

            return [$start, $end, $shrink];
        }

        $start = $this->toFloat($record->start_weight ?? null);
        $end = $this->toFloat($record->finish_weight ?? null);
        $shrink = $this->toFloat($record->shrink ?? null);

        if ($shrink === null && $start !== null && $end !== null) {
            $shrink = round($start - $end, 2);
        }

        return [$start, $end, $shrink];
    }

    /**
     * @return list<string>
     */
    private function emptyBlock(): array
    {
        return ['', '', '', '', ''];
    }

    /**
     * @param  list<list<string>>  $blocks
     */
    private function sumShrink(array $blocks): float
    {
        $total = 0.0;

        foreach ($blocks as $block) {
            $shrink = $block[4] ?? '';

            if ($shrink === '') {
                continue;
            }

            $total += (float) $shrink;
        }

        return round($total, 2);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function formatWeight(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, 2, '.', '');
    }

    /**
     * @return array<int, string>
     */
    private function craftsmanNames(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        if (! Schema::connection('third')->hasTable('mscraftsman')) {
            return $names = [];
        }

        $query = DB::connection('third')->table('mscraftsman');

        if (Schema::connection('third')->hasColumn('mscraftsman', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $nameColumn = Schema::connection('third')->hasColumn('mscraftsman', 'name')
            ? 'name'
            : (Schema::connection('third')->hasColumn('mscraftsman', 'label_tukang')
                ? 'label_tukang'
                : null);

        if ($nameColumn === null) {
            return $names = [];
        }

        return $names = $query
            ->pluck($nameColumn, 'row_id')
            ->mapWithKeys(fn (mixed $label, mixed $id): array => [
                (int) $id => trim((string) $label),
            ])
            ->all();
    }
}
