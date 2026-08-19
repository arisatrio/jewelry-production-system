<?php

namespace App\Support;

use App\Models\MsShape;
use App\Models\SkuMasterDiamond;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SkuMasterDiamondMapper
{
    /**
     * @var array<string, string>
     */
    private const CODE_ALIASES = [
        'RD' => 'R',
        'R.D' => 'R',
        'RD.F' => 'R',
        'RD1' => 'R',
        'CU' => 'CS',
        'CUS' => 'CS',
        'CU1' => 'CS',
        'CS1' => 'CS',
        'RA' => 'RAD',
        'EME' => 'EM',
        'EM1' => 'EM',
        'EMD' => 'EM',
        'ASC' => 'ASH',
        'AS' => 'ASH',
        'HS1' => 'HS',
        'HSI' => 'HS',
        'PS1' => 'PS',
        'OV1' => 'OV',
        'OVA' => 'OV',
        'MQ1' => 'MQ',
        'RUBY' => 'RB',
        'KITE' => 'KT',
        'TRI' => 'TR',
        'PC1' => 'PC',
    ];

    /**
     * @var array<string, MsShape>
     */
    private array $shapesByCode = [];

    /**
     * @var array<string, MsShape>
     */
    private array $shapesByName = [];

    public function __construct()
    {
        MsShape::query()
            ->notDeleted()
            ->get(['row_id', 'code', 'name'])
            ->each(function (MsShape $shape): void {
                $code = strtoupper(trim((string) ($shape->code ?? '')));

                if ($code !== '') {
                    $this->shapesByCode[$code] = $shape;
                }

                $name = strtoupper(trim((string) ($shape->name ?? '')));

                if ($name !== '') {
                    $this->shapesByName[$name] = $shape;
                }
            });
    }

    /**
     * @param  Collection<int, SkuMasterDiamond>  $diamonds
     * @return list<array{
     *     shapeId: string,
     *     shapeName: string,
     *     positionId: string,
     *     positionNama: string,
     *     pcs: string,
     *     caratPerPcs: string,
     *     size: string
     * }>
     */
    public function toFormStones(Collection $diamonds): array
    {
        return $diamonds
            ->map(fn (SkuMasterDiamond $diamond): array => $this->toFormStone($diamond))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     shapeId: string,
     *     shapeName: string,
     *     positionId: string,
     *     positionNama: string,
     *     pcs: string,
     *     caratPerPcs: string,
     *     size: string
     * }
     */
    public function toFormStone(SkuMasterDiamond $diamond): array
    {
        $shape = $this->resolveShape($diamond->diamond_type);
        $position = filled($diamond->position) ? trim((string) $diamond->position) : '';

        return [
            'shapeId' => $shape !== null ? (string) $shape->row_id : '',
            'shapeName' => $shape !== null
                ? $this->shapeDisplayName($shape)
                : (filled($diamond->diamond_type) ? trim((string) $diamond->diamond_type) : ''),
            'positionId' => '',
            'positionNama' => $position,
            'pcs' => $diamond->grain !== null ? (string) $diamond->grain : '',
            'caratPerPcs' => $diamond->grade !== null
                ? number_format((float) $diamond->grade, 3, '.', '')
                : '',
            'size' => filled($diamond->diameter) ? trim((string) $diamond->diameter) : '',
        ];
    }

    public function resolveShape(?string $diamondType): ?MsShape
    {
        $raw = strtoupper(trim((string) $diamondType));

        if ($raw === '') {
            return null;
        }

        $normalized = Str::of($raw)
            ->replace(['.', ' '], '')
            ->toString();
        $aliased = self::CODE_ALIASES[$raw]
            ?? self::CODE_ALIASES[$normalized]
            ?? $normalized;

        return $this->shapesByCode[$aliased]
            ?? $this->shapesByCode[$raw]
            ?? $this->shapesByName[$raw]
            ?? null;
    }

    private function shapeDisplayName(MsShape $shape): string
    {
        $name = trim((string) ($shape->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($shape->code ?? ''));

        return $code !== '' ? $code : 'Shape #'.$shape->row_id;
    }
}
