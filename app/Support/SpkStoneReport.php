<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkStoneReport
{
    private const TRANSTYPE_START = 7;

    private const TRANSTYPE_END = 8;

    /**
     * @return array{
     *     rows: list<array{
     *         no: int,
     *         stone: string,
     *         pcsStart: int|float|null,
     *         pcsEnd: int|float|null,
     *         startCrt: string|null,
     *         endCrt: string|null,
     *         difference: string|null
     *     }>,
     *     totalStartCrt: string|null,
     *     totalEndCrt: string|null,
     *     totalDifference: string|null,
     *     totalLabel: string
     * }
     */
    public function forProduction(Production $production): array
    {
        $spkId = (int) $production->row_id;

        if (
            ! Schema::connection('third')->hasTable('trstone')
            || ! Schema::connection('third')->hasTable('msstone')
        ) {
            return $this->emptyReport();
        }

        $query = DB::connection('third')
            ->table('trstone as t')
            ->leftJoin('msstone as m', 'm.row_id', '=', 't.stone_id')
            ->where('t.spk_id', $spkId)
            ->whereIn('t.transtype_id', [self::TRANSTYPE_START, self::TRANSTYPE_END]);

        if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $hasShape = Schema::connection('third')->hasTable('msshape');

        if ($hasShape) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 'm.shape_id');
        }

        $select = [
            't.transtype_id',
            't.stone_id',
            't.pcs',
            't.crt',
            'm.name as stone_name',
            'm.parcel',
            'm.stone_size',
        ];

        if ($hasShape) {
            $select[] = 's.name as shape_name';
        }

        $lines = $query
            ->orderBy('t.stone_id')
            ->orderBy('t.row_id')
            ->get($select);

        /** @var array<string, array{stone: string, pcsStart: float, pcsEnd: float, startCrt: float, endCrt: float}> $grouped */
        $grouped = [];

        foreach ($lines as $line) {
            $stoneKey = (string) ($line->stone_id ?? '0').'|'.$this->resolveStoneLabel($line);
            $crt = $line->crt !== null && $line->crt !== ''
                ? (float) $line->crt
                : 0.0;
            $pcs = $line->pcs !== null && $line->pcs !== ''
                ? (float) $line->pcs
                : 0.0;

            if (! isset($grouped[$stoneKey])) {
                $grouped[$stoneKey] = [
                    'stone' => $this->resolveStoneLabel($line),
                    'pcsStart' => 0.0,
                    'pcsEnd' => 0.0,
                    'startCrt' => 0.0,
                    'endCrt' => 0.0,
                ];
            }

            if ((int) $line->transtype_id === self::TRANSTYPE_START) {
                $grouped[$stoneKey]['pcsStart'] += $pcs;
                $grouped[$stoneKey]['startCrt'] += $crt;
            } else {
                $grouped[$stoneKey]['pcsEnd'] += $pcs;
                $grouped[$stoneKey]['endCrt'] += $crt;
            }
        }

        $rows = [];
        $totalStart = 0.0;
        $totalEnd = 0.0;

        foreach (array_values($grouped) as $index => $group) {
            $startCrt = round($group['startCrt'], 4);
            $endCrt = round($group['endCrt'], 4);
            $difference = round($startCrt - $endCrt, 4);
            $totalStart += $startCrt;
            $totalEnd += $endCrt;

            $rows[] = [
                'no' => $index + 1,
                'stone' => $group['stone'],
                'pcsStart' => $this->normalizePcs($group['pcsStart']),
                'pcsEnd' => $this->normalizePcs($group['pcsEnd']),
                'startCrt' => $this->formatCrt($startCrt),
                'endCrt' => $this->formatCrt($endCrt),
                'difference' => $this->formatCrt($difference),
            ];
        }

        $totalDifference = round($totalStart - $totalEnd, 4);

        return [
            'rows' => $rows,
            'totalStartCrt' => $this->formatNullableCrt($totalStart > 0 || $totalEnd > 0 ? $totalStart : null),
            'totalEndCrt' => $this->formatNullableCrt($totalStart > 0 || $totalEnd > 0 ? $totalEnd : null),
            'totalDifference' => $this->formatNullableCrt($totalStart > 0 || $totalEnd > 0 ? $totalDifference : null),
            'totalLabel' => $rows === []
                ? '0.0000 crt'
                : $this->formatCrt($totalDifference).' crt selisih',
        ];
    }

    /**
     * @return array{
     *     rows: list<array{
     *         no: int,
     *         stone: string,
     *         pcsStart: int|float|null,
     *         pcsEnd: int|float|null,
     *         startCrt: string|null,
     *         endCrt: string|null,
     *         difference: string|null
     *     }>,
     *     totalStartCrt: string|null,
     *     totalEndCrt: string|null,
     *     totalDifference: string|null,
     *     totalLabel: string
     * }
     */
    private function emptyReport(): array
    {
        return [
            'rows' => [],
            'totalStartCrt' => null,
            'totalEndCrt' => null,
            'totalDifference' => null,
            'totalLabel' => '0.0000 crt',
        ];
    }

    private function resolveStoneLabel(object $line): string
    {
        if (filled($line->stone_name ?? null)) {
            return (string) $line->stone_name;
        }

        $label = trim(implode(' ', array_filter([
            $line->shape_name ?? null,
            $line->parcel ?? null,
            filled($line->stone_size ?? null) ? ((string) $line->stone_size).' MM' : null,
        ])));

        return $label !== '' ? $label : 'Batu';
    }

    private function normalizePcs(float $value): int|float|null
    {
        if (abs($value) < 0.0005) {
            return null;
        }

        return abs($value - round($value)) < 0.0005
            ? (int) round($value)
            : round($value, 2);
    }

    private function formatCrt(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function formatNullableCrt(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->formatCrt($value);
    }
}
