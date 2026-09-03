<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoranMaterialBreakdown
{
    /**
     * @param  list<int|string>  $coranIds
     * @return array<int, list<array{
     *     color: string,
     *     colorKey: string,
     *     bahan: list<array{name: string, weight: float}>,
     *     sisa: list<array{name: string, weight: float}>
     * }>>
     */
    public function forIds(array $coranIds): array
    {
        $empty = [];

        foreach ($coranIds as $coranId) {
            $empty[(int) $coranId] = $this->empty();
        }

        if (
            $coranIds === []
            || ! Schema::connection('third')->hasTable('trmaterialgold')
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            return $empty;
        }

        $transtypeMap = [
            1 => ['colorKey' => 'rosegold', 'bucket' => 'bahan'],
            2 => ['colorKey' => 'whitegold', 'bucket' => 'bahan'],
            3 => ['colorKey' => 'rosegold', 'bucket' => 'sisa'],
            4 => ['colorKey' => 'whitegold', 'bucket' => 'sisa'],
            10 => ['colorKey' => 'yellowgold', 'bucket' => 'bahan'],
            11 => ['colorKey' => 'yellowgold', 'bucket' => 'sisa'],
        ];

        $query = DB::connection('third')
            ->table('trmaterialgold as t')
            ->leftJoin('msmaterialgold as m', 'm.row_id', '=', 't.materialgold_id')
            ->whereIn('t.ref_row_id', $coranIds)
            ->whereIn('t.transtype_id', array_keys($transtypeMap));

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $lines = $query
            ->orderBy('t.transtype_id')
            ->orderBy('t.row_id')
            ->get([
                't.ref_row_id',
                't.transtype_id',
                't.weight',
                'm.name as material_name',
            ]);

        $grouped = $empty;

        foreach ($lines as $line) {
            $coranId = (int) $line->ref_row_id;
            $map = $transtypeMap[(int) $line->transtype_id] ?? null;

            if ($map === null || ! array_key_exists($coranId, $grouped)) {
                continue;
            }

            $colorKey = $map['colorKey'];
            $bucket = $map['bucket'];

            foreach ($grouped[$coranId] as $index => $section) {
                if ($section['colorKey'] !== $colorKey) {
                    continue;
                }

                $grouped[$coranId][$index][$bucket][] = [
                    'name' => filled($line->material_name)
                        ? (string) $line->material_name
                        : 'Bahan',
                    'weight' => round((float) $line->weight, 3),
                ];
            }
        }

        return $grouped;
    }

    /**
     * @return list<array{
     *     color: string,
     *     colorKey: string,
     *     bahan: list<array{name: string, weight: float}>,
     *     sisa: list<array{name: string, weight: float}>
     * }>
     */
    public function empty(): array
    {
        return [
            [
                'color' => 'Rose Gold',
                'colorKey' => 'rosegold',
                'bahan' => [],
                'sisa' => [],
            ],
            [
                'color' => 'White Gold',
                'colorKey' => 'whitegold',
                'bahan' => [],
                'sisa' => [],
            ],
            [
                'color' => 'Yellow Gold',
                'colorKey' => 'yellowgold',
                'bahan' => [],
                'sisa' => [],
            ],
        ];
    }
}
