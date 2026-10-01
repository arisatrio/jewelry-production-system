<?php

namespace Database\Seeders;

use App\Models\DiamondCrtMatrix;
use App\Models\MsShape;
use Illuminate\Database\Seeder;
use RuntimeException;

class DiamondCrtMatrixSeeder extends Seeder
{
    /**
     * Urutan shape sesuai file "Matrix CRT Dossier.xlsx", dipetakan ke msshape.code.
     *
     * @var list<string>
     */
    public const SHAPE_CODES = [
        'R',   // ROUND
        'EM',  // EMERALD
        'OV',  // OVAL
        'PS',  // PEAR
        'MQ',  // MARQUISE
        'HS',  // HEART
        'CS',  // CUSHION
        'RAD', // RADIANT
        'ASH', // ASHER
        'BQ',  // BAGUETTE
        'PC',  // PRINCESS
        'KT',  // KITE
    ];

    /**
     * @var list<array{0: string, 1: string}>
     */
    public const CRT_RANGES = [
        ['0.180', '0.180'],
        ['0.190', '0.190'],
        ['0.200', '0.290'],
        ['0.300', '0.390'],
        ['0.400', '0.490'],
        ['0.500', '0.590'],
        ['0.600', '0.690'],
        ['0.700', '0.790'],
        ['0.800', '0.890'],
        ['0.900', '0.990'],
        ['1.000', '10.000'],
    ];

    /**
     * Seed matrix CRT dossier per shape (idempotent).
     */
    public function run(): void
    {
        $shapeIdsByCode = MsShape::query()
            ->notDeleted()
            ->whereIn('code', self::SHAPE_CODES)
            ->pluck('row_id', 'code');

        $missingCodes = array_diff(self::SHAPE_CODES, $shapeIdsByCode->keys()->all());

        if ($missingCodes !== []) {
            throw new RuntimeException('Shape tidak ditemukan di msshape: '.implode(', ', $missingCodes));
        }

        $now = now();
        $rows = [];
        $sortOrder = 0;

        foreach (self::SHAPE_CODES as $shapeCode) {
            foreach (self::CRT_RANGES as [$crtMin, $crtMax]) {
                $rows[] = [
                    'shape_id' => (int) $shapeIdsByCode[$shapeCode],
                    'crt_min' => $crtMin,
                    'crt_max' => $crtMax,
                    'sort_order' => ++$sortOrder,
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DiamondCrtMatrix::query()->upsert(
            $rows,
            ['shape_id', 'crt_min', 'crt_max'],
            ['sort_order', 'updated_by', 'updated_at'],
        );
    }
}
