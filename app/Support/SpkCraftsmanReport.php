<?php

namespace App\Support;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkCraftsmanReport
{
    /**
     * @return list<array{
     *     no: int,
     *     craftsmanId: int|null,
     *     craftsmanName: string,
     *     process: string,
     *     workDuration: string|null,
     *     workDurationMinutes: int|null,
     *     sentAt: string|null,
     *     receivedAt: string|null,
     *     shrink: string|null
     * }>
     */
    public function forProduction(Production $production): array
    {
        $spkId = (int) $production->row_id;

        /** @var list<array{table: string, label: string, shrink_column: string, date_column: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);

        $cards = [];

        foreach ($sources as $source) {
            foreach ($this->cardsForSource($spkId, $source) as $card) {
                $cards[] = $card;
            }
        }

        usort($cards, function (array $left, array $right): int {
            return strcmp($left['sortDate'], $right['sortDate']);
        });

        $names = $this->resolveCraftsmanNames(
            array_values(array_filter(array_column($cards, 'craftsmanId'))),
        );

        $numbered = [];

        foreach (array_values($cards) as $index => $card) {
            $craftsmanId = $card['craftsmanId'];

            $numbered[] = [
                'no' => $index + 1,
                'craftsmanId' => $craftsmanId,
                'craftsmanName' => $craftsmanId !== null
                    ? ($names[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                    : 'Tanpa pengrajin',
                'process' => $card['process'],
                'workDuration' => $card['workDuration'],
                'workDurationMinutes' => $card['workDurationMinutes'],
                'sentAt' => $card['sentAt'],
                'receivedAt' => $card['receivedAt'],
                'shrink' => $card['shrink'],
            ];
        }

        return $numbered;
    }

    /**
     * @param  array{table: string, label: string, shrink_column: string, date_column: string}  $source
     * @return list<array{
     *     craftsmanId: int|null,
     *     process: string,
     *     workDuration: string|null,
     *     workDurationMinutes: int|null,
     *     sentAt: string|null,
     *     receivedAt: string|null,
     *     shrink: string|null,
     *     sortDate: string
     * }>
     */
    private function cardsForSource(int $spkId, array $source): array
    {
        $table = $source['table'];

        if (! Schema::connection('third')->hasTable($table)) {
            return [];
        }

        $craftsmanColumn = $this->resolveCraftsmanColumn($table);

        if ($craftsmanColumn === null) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->where('spk_id', $spkId)
            ->whereNotNull($craftsmanColumn);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $hasReceivedColumn = Schema::connection('third')->hasColumn($table, 'received_craftsman_date');
        $hasDateFrom = Schema::connection('third')->hasColumn($table, 'date_from');
        $hasDateTo = Schema::connection('third')->hasColumn($table, 'date_to');
        $records = $query->get();
        $cards = [];

        foreach ($records as $record) {
            $craftsmanId = isset($record->{$craftsmanColumn}) && $record->{$craftsmanColumn} !== ''
                ? (int) $record->{$craftsmanColumn}
                : null;

            if ($craftsmanId === null || $craftsmanId === 0) {
                continue;
            }

            $sentAt = $this->nullableDate($record->{$source['date_column']} ?? null);
            $receivedAt = $hasReceivedColumn
                ? $this->nullableDate($record->received_craftsman_date ?? null)
                : null;

            if ($sentAt === null && $hasDateFrom) {
                $sentAt = $this->nullableDate($record->date_from ?? null);
            }

            if ($receivedAt === null && $hasDateTo) {
                $receivedAt = $this->nullableDate($record->date_to ?? null);
            }

            $durationMinutes = $this->resolveDurationMinutes($sentAt, $receivedAt);
            $shrink = $this->resolveShrink($record, $source['shrink_column']);

            $cards[] = [
                'craftsmanId' => $craftsmanId,
                'process' => $source['label'],
                'workDuration' => $this->formatDuration($durationMinutes),
                'workDurationMinutes' => $durationMinutes,
                'sentAt' => $sentAt?->format('d-M-Y H:i'),
                'receivedAt' => $receivedAt?->format('d-M-Y H:i'),
                'shrink' => $shrink !== null ? $this->formatWeight($shrink) : null,
                'sortDate' => $sentAt?->format('Y-m-d H:i:s') ?? '9999-12-31',
            ];
        }

        return $cards;
    }

    private function resolveCraftsmanColumn(string $table): ?string
    {
        if (Schema::connection('third')->hasColumn($table, 'craftsman_id')) {
            return 'craftsman_id';
        }

        if (Schema::connection('third')->hasColumn($table, 'craftman_id')) {
            return 'craftman_id';
        }

        return null;
    }

    /**
     * @param  list<int>  $craftsmanIds
     * @return array<int, string>
     */
    private function resolveCraftsmanNames(array $craftsmanIds): array
    {
        if ($craftsmanIds === [] || ! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        return DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', array_values(array_unique($craftsmanIds)))
            ->pluck('name', 'row_id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    private function resolveDurationMinutes(?Carbon $sentAt, ?Carbon $receivedAt): ?int
    {
        if ($sentAt === null || $receivedAt === null || $receivedAt->lt($sentAt)) {
            return null;
        }

        $minutes = (int) $sentAt->diffInMinutes($receivedAt);

        if ($minutes === 0 && $receivedAt->gt($sentAt)) {
            return 0;
        }

        return $minutes;
    }

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes === 0) {
            return '< 1 menit';
        }

        if ($minutes < 60) {
            return "{$minutes} menit";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$remainingMinutes} menit";
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
                return isset($record->mounting_shrink)
                    ? (float) $record->mounting_shrink
                    : null;
            }

            return round($before - $after, 3);
        }

        if (! isset($record->{$shrinkColumn}) || $record->{$shrinkColumn} === null || $record->{$shrinkColumn} === '') {
            return null;
        }

        return round((float) $record->{$shrinkColumn}, 3);
    }

    private function nullableDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function formatWeight(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
