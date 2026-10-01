<?php

namespace App\Support;

use App\Models\DiamondCrtMatrix;
use App\Models\MsShape;
use Illuminate\Support\Facades\DB;

class DiamondCrtMatrixSettings
{
    /**
     * @return list<array{id: int, code: string|null, name: string|null}>
     */
    public function shapeOptions(): array
    {
        return array_values(MsShape::query()
            ->notDeleted()
            ->orderBy('name')
            ->get(['row_id', 'code', 'name'])
            ->map(fn (MsShape $shape): array => [
                'id' => (int) $shape->row_id,
                'code' => $shape->code,
                'name' => $shape->name,
            ])
            ->all());
    }

    /**
     * @return list<array{shapeId: int, crtMin: string, crtMax: string}>
     */
    public function rows(): array
    {
        return array_values(DiamondCrtMatrix::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DiamondCrtMatrix $range): array => [
                'shapeId' => $range->shape_id,
                'crtMin' => $range->crt_min,
                'crtMax' => $range->crt_max,
            ])
            ->all());
    }

    /**
     * @return array{by: string|null, at: string}|null
     */
    public function lastUpdate(): ?array
    {
        $latest = DiamondCrtMatrix::query()->latest('updated_at')->first();

        if ($latest?->updated_at === null) {
            return null;
        }

        return [
            'by' => $latest->updated_by,
            'at' => $latest->updated_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * Ganti seluruh isi matrix dengan baris baru sesuai urutan input.
     *
     * @param  list<array{shape_id: int|string, crt_min: float|string, crt_max: float|string}>  $rows
     */
    public function sync(array $rows, string $actor): void
    {
        $now = now();
        $sortOrder = 0;

        $records = array_map(function (array $row) use ($actor, $now, &$sortOrder): array {
            return [
                'shape_id' => (int) $row['shape_id'],
                'crt_min' => number_format((float) $row['crt_min'], 3, '.', ''),
                'crt_max' => number_format((float) $row['crt_max'], 3, '.', ''),
                'sort_order' => ++$sortOrder,
                'updated_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        DB::connection('third')->transaction(function () use ($records): void {
            DiamondCrtMatrix::query()->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                DiamondCrtMatrix::query()->insert($chunk);
            }
        });
    }
}
