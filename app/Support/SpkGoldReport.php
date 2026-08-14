<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkGoldReport
{
    /**
     * @return array{
     *     issued: string|null,
     *     returned: string|null,
     *     used: string|null,
     *     difference: string|null,
     *     materials: list<array{no: int, name: string, type: string, weight: string, notes: string|null}>,
     *     totalLabel: string
     * }
     */
    public function forProduction(Production $production): array
    {
        $totals = $this->totalsForSpk((int) $production->row_id);
        $issued = $totals['issued'];
        $returned = $totals['returned'];
        $used = $totals['used'];
        $difference = $issued !== null && $returned !== null && $used !== null
            ? round($issued - $used - $returned, 3)
            : null;

        return [
            'issued' => $this->formatNullableWeight($issued),
            'returned' => $this->formatNullableWeight($returned),
            'used' => $this->formatNullableWeight($used),
            'difference' => $this->formatNullableWeight($difference),
            'materials' => $totals['materials'],
            'totalLabel' => $used !== null
                ? $this->formatWeight($used).' g terpakai'
                : '0.000 g terpakai',
        ];
    }

    /**
     * Gold materials from trmaterialgold (transtype 5=OUT/serah, 6=IN/kembali).
     *
     * @return array{
     *     issued: float|null,
     *     returned: float|null,
     *     used: float|null,
     *     materials: list<array{no: int, name: string, type: string, weight: string, notes: string|null}>
     * }
     */
    public function totalsForSpk(int $spkId): array
    {
        $empty = [
            'issued' => null,
            'returned' => null,
            'used' => null,
            'materials' => [],
        ];

        if (! Schema::connection('third')->hasTable('trmaterialgold')) {
            return $empty;
        }

        $refIds = [];

        if (Schema::connection('third')->hasTable('finishinghandmade')) {
            $finishingQuery = DB::connection('third')
                ->table('finishinghandmade')
                ->where('spk_id', $spkId);

            if (Schema::connection('third')->hasColumn('finishinghandmade', 'is_deleted')) {
                $finishingQuery->where('is_deleted', 0);
            }

            $refIds = $finishingQuery->pluck('row_id')->all();
        }

        $query = DB::connection('third')
            ->table('trmaterialgold as t')
            ->whereIn('t.transtype_id', [5, 6]);

        if (Schema::connection('third')->hasTable('msmaterialgold')) {
            $query->leftJoin('msmaterialgold as m', 'm.row_id', '=', 't.materialgold_id');
        }

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $query->where(function ($builder) use ($spkId, $refIds): void {
            $builder->where('t.spk_id', $spkId);

            if ($refIds !== []) {
                $builder->orWhereIn('t.ref_row_id', $refIds);
            }
        });

        $select = [
            't.row_id',
            't.transtype_id',
            't.weight',
            't.notes',
        ];

        if (Schema::connection('third')->hasTable('msmaterialgold')) {
            $select[] = 'm.name as material_name';
        }

        $lines = $query
            ->orderBy('t.transtype_id')
            ->orderBy('t.row_id')
            ->get($select)
            ->unique('row_id')
            ->values();

        if ($lines->isEmpty()) {
            return [
                'issued' => 0.0,
                'returned' => 0.0,
                'used' => 0.0,
                'materials' => [],
            ];
        }

        $issued = round(
            (float) $lines->where('transtype_id', 5)->sum(fn (object $line): float => (float) $line->weight),
            3,
        );
        $returned = round(
            (float) $lines->where('transtype_id', 6)->sum(fn (object $line): float => (float) $line->weight),
            3,
        );

        $materials = [];

        foreach ($lines as $index => $line) {
            $notes = isset($line->notes) && $line->notes !== null && trim((string) $line->notes) !== ''
                ? trim((string) $line->notes)
                : null;

            $materials[] = [
                'no' => $index + 1,
                'name' => filled($line->material_name ?? null)
                    ? (string) $line->material_name
                    : 'Bahan',
                'type' => ((int) $line->transtype_id) === 5 ? 'Serah' : 'Kembali',
                'weight' => $this->formatWeight((float) $line->weight),
                'notes' => $notes,
            ];
        }

        return [
            'issued' => $issued,
            'returned' => $returned,
            'used' => round($issued - $returned, 3),
            'materials' => $materials,
        ];
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
