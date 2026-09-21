<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinishingMaterialBreakdown
{
    public const TRANSTYPE_OUT = 5;

    public const TRANSTYPE_IN = 6;

    /**
     * @return list<int>
     */
    public static function transtypeIds(): array
    {
        return [self::TRANSTYPE_OUT, self::TRANSTYPE_IN];
    }

    /**
     * @param  list<int|string>  $finishingIds
     * @return array<int, array{
     *     bahan: list<array{name: string, weight: float, notes: string|null}>,
     *     sisa: list<array{name: string, weight: float, notes: string|null}>
     * }>
     */
    public function forIds(array $finishingIds): array
    {
        $empty = [];

        foreach ($finishingIds as $finishingId) {
            $empty[(int) $finishingId] = $this->empty();
        }

        if (
            $finishingIds === []
            || ! Schema::connection('third')->hasTable('trmaterialgold')
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('trmaterialgold as t')
            ->leftJoin('msmaterialgold as m', 'm.row_id', '=', 't.materialgold_id')
            ->whereIn('t.ref_row_id', $finishingIds)
            ->whereIn('t.transtype_id', self::transtypeIds());

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $lines = $query
            ->orderBy('t.row_id')
            ->get([
                't.ref_row_id',
                't.transtype_id',
                't.weight',
                't.notes',
                'm.name as material_name',
            ]);

        $grouped = $empty;

        foreach ($lines as $line) {
            $finishingId = (int) $line->ref_row_id;

            if (! array_key_exists($finishingId, $grouped)) {
                continue;
            }

            $bucket = (int) $line->transtype_id === self::TRANSTYPE_OUT ? 'bahan' : 'sisa';

            $grouped[$finishingId][$bucket][] = [
                'name' => filled($line->material_name) ? (string) $line->material_name : 'Bahan',
                'weight' => round((float) $line->weight, 2),
                'notes' => filled($line->notes ?? null) ? (string) $line->notes : null,
            ];
        }

        return $grouped;
    }

    /**
     * @return array{
     *     bahan: list<array{name: string, weight: float, notes: string|null}>,
     *     sisa: list<array{name: string, weight: float, notes: string|null}>
     * }
     */
    public function empty(): array
    {
        return [
            'bahan' => [],
            'sisa' => [],
        ];
    }
}
