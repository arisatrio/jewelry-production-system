<?php

namespace App\Support;

use App\Models\FinishingHandmade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinishingMaterialGoldSynchronizer
{
    /**
     * Transtype map for finishing materials (bahan / sisa).
     *
     * @var array<string, array{transtype_id: int, bucket: string}>
     */
    public const SECTION_MAP = [
        'bahan' => [
            'transtype_id' => FinishingMaterialBreakdown::TRANSTYPE_OUT,
            'bucket' => 'bahan',
        ],
        'sisa' => [
            'transtype_id' => FinishingMaterialBreakdown::TRANSTYPE_IN,
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
        return FinishingMaterialBreakdown::transtypeIds();
    }

    /**
     * @param  list<array{section: string, materialgold_id: int, weight: string|float, notes?: string|null}>  $lines
     */
    public function sync(FinishingHandmade $document, array $lines, string $actor): void
    {
        if (
            ! Schema::connection('third')->hasTable('trmaterialgold')
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            $this->updateTotals($document, []);

            return;
        }

        $normalized = $this->normalizeLines($lines);
        $this->replaceRows((int) $document->row_id, $normalized, $actor);
        $this->updateTotals($document, $normalized);
    }

    /**
     * @return list<array{value: string, label: string, stock: string}>
     */
    public function materialOptions(): array
    {
        if (! Schema::connection('third')->hasTable('msmaterialgold')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('msmaterialgold as material')
            ->whereNotNull('material.name')
            ->where('material.name', '!=', '')
            ->orderBy('material.name');

        if (Schema::connection('third')->hasColumn('msmaterialgold', 'is_deleted')) {
            $query->where('material.is_deleted', 0);
        }

        $columns = ['material.row_id', 'material.name'];

        if (
            Schema::connection('third')->hasTable('trmaterialgold')
            && Schema::connection('third')->hasTable('mstranstype')
        ) {
            $stockQuery = DB::connection('third')
                ->table('trmaterialgold as transaction')
                ->join('mstranstype as transaction_type', 'transaction_type.row_id', '=', 'transaction.transtype_id')
                ->select('transaction.materialgold_id')
                ->selectRaw(
                    "SUM(CASE
                        WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN' THEN COALESCE(transaction.weight, 0)
                        WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT' THEN -COALESCE(transaction.weight, 0)
                        ELSE 0
                    END) as stock"
                )
                ->groupBy('transaction.materialgold_id');

            if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
                $stockQuery->where('transaction.is_deleted', 0);
            }

            if (Schema::connection('third')->hasColumn('mstranstype', 'is_deleted')) {
                $stockQuery->where('transaction_type.is_deleted', 0);
            }

            $query->leftJoinSub(
                $stockQuery,
                'material_stock',
                'material_stock.materialgold_id',
                '=',
                'material.row_id',
            );
            $columns[] = 'material_stock.stock';
        }

        return $query
            ->get($columns)
            ->map(fn (object $row): array => [
                'value' => (string) $row->row_id,
                'label' => (string) $row->name,
                'stock' => number_format((float) ($row->stock ?? 0), 3, '.', ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{section: string, materialgoldId: int, weight: string, notes: string}>
     */
    public function formLinesFor(FinishingHandmade $document): array
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
            ->where('ref_row_id', $document->row_id)
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
     * @return list<array{section: string, materialgold_id: int, weight: float, notes: string, transtype_id: int, bucket: string}>
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
                'bucket' => $map['bucket'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{section: string, materialgold_id: int, weight: float, notes: string, transtype_id: int, bucket: string}>  $lines
     */
    private function replaceRows(int $finishingId, array $lines, string $actor): void
    {
        $query = DB::connection('third')
            ->table('trmaterialgold')
            ->where('ref_row_id', $finishingId)
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
                'ref_row_id' => $finishingId,
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
     * @param  list<array{section: string, materialgold_id: int, weight: float, bucket: string}>  $lines
     */
    private function updateTotals(FinishingHandmade $document, array $lines): void
    {
        $submit = 0.0;
        $result = 0.0;

        foreach ($lines as $line) {
            if ($line['bucket'] === 'bahan') {
                $submit += $line['weight'];
            } else {
                $result += $line['weight'];
            }
        }

        $document->forceFill([
            'submit_materialgold' => number_format($submit, 3, '.', ''),
            'result_materialgold' => number_format($result, 3, '.', ''),
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
