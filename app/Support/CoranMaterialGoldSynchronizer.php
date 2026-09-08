<?php

namespace App\Support;

use App\Models\Coran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoranMaterialGoldSynchronizer
{
    /**
     * Transtype map for coran batch materials (bahan / sisa).
     *
     * @var array<string, array{transtype_id: int, color_key: string, bucket: string}>
     */
    public const SECTION_MAP = [
        'bahan_rosegold' => [
            'transtype_id' => 1,
            'color_key' => 'rosegold',
            'bucket' => 'bahan',
        ],
        'bahan_whitegold' => [
            'transtype_id' => 2,
            'color_key' => 'whitegold',
            'bucket' => 'bahan',
        ],
        'sisa_rosegold' => [
            'transtype_id' => 3,
            'color_key' => 'rosegold',
            'bucket' => 'sisa',
        ],
        'sisa_whitegold' => [
            'transtype_id' => 4,
            'color_key' => 'whitegold',
            'bucket' => 'sisa',
        ],
        'bahan_yellowgold' => [
            'transtype_id' => 10,
            'color_key' => 'yellowgold',
            'bucket' => 'bahan',
        ],
        'sisa_yellowgold' => [
            'transtype_id' => 11,
            'color_key' => 'yellowgold',
            'bucket' => 'sisa',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function sectionKeys(): array
    {
        return array_keys(self::SECTION_MAP);
    }

    /**
     * @return list<int>
     */
    public static function transtypeIds(): array
    {
        return array_values(array_unique(array_column(self::SECTION_MAP, 'transtype_id')));
    }

    /**
     * @param  list<array{section: string, materialgold_id: int, weight: string|float, notes?: string|null}>  $lines
     */
    public function sync(Coran $coran, array $lines, string $actor): void
    {
        if (
            ! Schema::connection('third')->hasTable('trmaterialgold')
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            $this->updateTotals($coran, []);

            return;
        }

        $normalized = $this->normalizeLines($lines);
        $this->replaceRows((int) $coran->row_id, $normalized, $actor);
        $this->updateTotals($coran, $normalized);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function materialOptions(): array
    {
        if (! Schema::connection('third')->hasTable('msmaterialgold')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('msmaterialgold')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name');

        if (Schema::connection('third')->hasColumn('msmaterialgold', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->get(['row_id', 'name'])
            ->map(fn (object $row): array => [
                'value' => (string) $row->row_id,
                'label' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{section: string, materialgoldId: int, weight: string, notes: string}>
     */
    public function formLinesFor(Coran $coran): array
    {
        if (
            ! Schema::connection('third')->hasTable('trmaterialgold')
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            return [];
        }

        $sectionByTranstype = [];

        foreach (self::SECTION_MAP as $section => $map) {
            $sectionByTranstype[$map['transtype_id']] = $section;
        }

        $hasNotes = Schema::connection('third')->hasColumn('trmaterialgold', 'notes');
        $columns = ['transtype_id', 'materialgold_id', 'weight'];

        if ($hasNotes) {
            $columns[] = 'notes';
        }

        $query = DB::connection('third')
            ->table('trmaterialgold')
            ->where('ref_row_id', $coran->row_id)
            ->whereIn('transtype_id', array_keys($sectionByTranstype))
            ->orderBy('transtype_id')
            ->orderBy('row_id');

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->get($columns)
            ->map(function (object $row) use ($sectionByTranstype, $hasNotes): ?array {
                $section = $sectionByTranstype[(int) $row->transtype_id] ?? null;
                $materialId = (int) ($row->materialgold_id ?? 0);

                if ($section === null || $materialId <= 0) {
                    return null;
                }

                return [
                    'section' => $section,
                    'materialgoldId' => $materialId,
                    'weight' => number_format((float) $row->weight, 3, '.', ''),
                    'notes' => $hasNotes
                        ? trim((string) ($row->notes ?? ''))
                        : '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{section?: mixed, materialgold_id?: mixed, weight?: mixed, notes?: mixed}>  $lines
     * @return list<array{section: string, materialgold_id: int, weight: float, notes: string, transtype_id: int, color_key: string, bucket: string}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $section = (string) ($line['section'] ?? '');
            $map = self::SECTION_MAP[$section] ?? null;
            $materialId = (int) ($line['materialgold_id'] ?? 0);
            $weight = $this->toFloat($line['weight'] ?? null);
            $notes = trim((string) ($line['notes'] ?? ''));

            if ($map === null || $materialId <= 0 || $weight === null || $weight < 0) {
                continue;
            }

            $normalized[] = [
                'section' => $section,
                'materialgold_id' => $materialId,
                'weight' => round($weight, 3),
                'notes' => $notes,
                'transtype_id' => $map['transtype_id'],
                'color_key' => $map['color_key'],
                'bucket' => $map['bucket'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{section: string, materialgold_id: int, weight: float, notes: string, transtype_id: int, color_key: string, bucket: string}>  $lines
     */
    private function replaceRows(int $coranId, array $lines, string $actor): void
    {
        $query = DB::connection('third')
            ->table('trmaterialgold')
            ->where('ref_row_id', $coranId)
            ->whereIn('transtype_id', self::transtypeIds());

        if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
            $query->where('is_deleted', 0)->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        } else {
            $query->delete();
        }

        $now = now();
        $hasPeriod = Schema::connection('third')->hasColumn('trmaterialgold', 'period_id');
        $hasSpk = Schema::connection('third')->hasColumn('trmaterialgold', 'spk_id');
        $hasTxt = Schema::connection('third')->hasColumn('trmaterialgold', 'txt');
        $hasNotes = Schema::connection('third')->hasColumn('trmaterialgold', 'notes');
        $hasIsDeleted = Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted');

        foreach ($lines as $line) {
            $payload = [
                'transtype_id' => $line['transtype_id'],
                'ref_row_id' => $coranId,
                'materialgold_id' => $line['materialgold_id'],
                'weight' => number_format($line['weight'], 3, '.', ''),
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ];

            if ($hasPeriod) {
                $payload['period_id'] = 1;
            }

            if ($hasSpk) {
                $payload['spk_id'] = 0;
            }

            if ($hasTxt) {
                $payload['txt'] = null;
            }

            if ($hasNotes) {
                $payload['notes'] = $line['notes'];
            }

            if ($hasIsDeleted) {
                $payload['is_deleted'] = 0;
                $payload['deleted_date'] = null;
                $payload['deleted_by'] = null;
            }

            DB::connection('third')->table('trmaterialgold')->insert($payload);
        }
    }

    /**
     * @param  list<array{section: string, materialgold_id: int, weight: float, transtype_id: int, color_key: string, bucket: string}>  $lines
     */
    private function updateTotals(Coran $coran, array $lines): void
    {
        $totals = [
            'submit_material_rosegold' => 0.0,
            'submit_material_whitegold' => 0.0,
            'submit_material_yellowgold' => 0.0,
            'result_material_rosegold' => 0.0,
            'result_material_whitegold' => 0.0,
            'result_material_yellowgold' => 0.0,
        ];

        foreach ($lines as $line) {
            $field = $line['bucket'] === 'bahan'
                ? 'submit_material_'.$line['color_key']
                : 'result_material_'.$line['color_key'];

            if (! array_key_exists($field, $totals)) {
                continue;
            }

            $totals[$field] += $line['weight'];
        }

        $coran->forceFill([
            'submit_material_rosegold' => number_format($totals['submit_material_rosegold'], 3, '.', ''),
            'submit_material_whitegold' => number_format($totals['submit_material_whitegold'], 3, '.', ''),
            'submit_material_yellowgold' => number_format($totals['submit_material_yellowgold'], 3, '.', ''),
            'result_material_rosegold' => number_format($totals['result_material_rosegold'], 3, '.', ''),
            'result_material_whitegold' => number_format($totals['result_material_whitegold'], 3, '.', ''),
            'result_material_yellowgold' => number_format($totals['result_material_yellowgold'], 3, '.', ''),
        ])->save();
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
