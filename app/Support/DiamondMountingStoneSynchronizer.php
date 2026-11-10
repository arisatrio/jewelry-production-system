<?php

namespace App\Support;

use App\Models\DiamondMounting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondMountingStoneSynchronizer
{
    public const TRANSTYPE_SETTING = 7;

    public const TRANSTYPE_RETURN = 8;

    public function __construct(private DiamondDossierInventory $diamondDossierInventory) {}

    /**
     * @return list<array{value: string, label: string, description: string, code: string|null, diamondType: string|null, shape: string|null, certificate: string|null, crt: string|null}>
     */
    public function diamondOptions(?DiamondMounting $document = null): array
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        return $this->diamondDossierInventory->mountingOptions(
            $document !== null ? (int) $document->row_id : null,
        );
    }

    /**
     * @return list<array{value: string, label: string, stock: string}>
     */
    public function stoneOptions(): array
    {
        if (! Schema::connection('third')->hasTable('msstone')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('msstone as stone')
            ->whereNotNull('stone.name')
            ->where('stone.name', '!=', '')
            ->orderBy('stone.name');

        if (Schema::connection('third')->hasColumn('msstone', 'is_deleted')) {
            $query->where('stone.is_deleted', 0);
        }

        $columns = ['stone.row_id', 'stone.name'];

        if (
            Schema::connection('third')->hasTable('trstone')
            && Schema::connection('third')->hasTable('mstranstype')
        ) {
            $stockQuery = DB::connection('third')
                ->table('trstone as transaction')
                ->join('mstranstype as transaction_type', 'transaction_type.row_id', '=', 'transaction.transtype_id')
                ->select('transaction.stone_id')
                ->selectRaw(
                    "SUM(CASE
                        WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN' THEN COALESCE(transaction.pcs, 0)
                        WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT' THEN -COALESCE(transaction.pcs, 0)
                        ELSE 0
                    END) as stock"
                )
                ->groupBy('transaction.stone_id');

            if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
                $stockQuery->where('transaction.is_deleted', 0);
            }

            if (Schema::connection('third')->hasColumn('mstranstype', 'is_deleted')) {
                $stockQuery->where('transaction_type.is_deleted', 0);
            }

            $query->leftJoinSub(
                $stockQuery,
                'stone_stock',
                'stone_stock.stone_id',
                '=',
                'stone.row_id',
            );
            $columns[] = 'stone_stock.stock';
        }

        return $query
            ->get($columns)
            ->map(function (object $row): array {
                $stock = (int) round((float) ($row->stock ?? 0));

                return [
                    'value' => (string) $row->row_id,
                    'label' => (string) $row->name,
                    'stock' => (string) $stock,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function shapeOptions(): array
    {
        if (! Schema::connection('third')->hasTable('msshape')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('msshape')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name');

        if (Schema::connection('third')->hasColumn('msshape', 'is_deleted')) {
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
     * @return array{
     *     setting: list<array{stoneId: int, pcs: string, crt: string, notes: string}>,
     *     return: list<array{stoneId: int, pcs: string, crt: string, notes: string}>,
     *     diamonds: list<array{diamondId: int}>,
     *     mounted: list<array{diamondCode: string, shapeId: int|null, pcs: string, crt: string, size: string}>
     * }
     */
    public function formPayloadFor(DiamondMounting $document): array
    {
        return [
            'setting' => $this->formStoneLinesFor($document, self::TRANSTYPE_SETTING),
            'return' => $this->formStoneLinesFor($document, self::TRANSTYPE_RETURN),
            'diamonds' => $this->formDiamondLinesFor($document),
            'mounted' => $this->formMountedLinesFor($document),
        ];
    }

    /**
     * @return array{
     *     setting: list<array{batu: string, pcs: int|float|null, crt: string|null, notes: string|null}>,
     *     return: list<array{batu: string, pcs: int|float|null, crt: string|null, notes: string|null}>,
     *     diamonds: list<array{kode: string, diamond: string, bentuk: string, sertifikat: string, crt: string|null}>,
     *     mounted: list<array{kode: string, shape: string, pcs: int|float|null, crt: string|null, size: string}>
     * }
     */
    public function detailPayloadFor(DiamondMounting $document): array
    {
        $mountingId = (int) $document->row_id;

        return [
            'setting' => $this->detailStoneLines([$mountingId], self::TRANSTYPE_SETTING)[$mountingId] ?? [],
            'return' => $this->detailStoneLines([$mountingId], self::TRANSTYPE_RETURN)[$mountingId] ?? [],
            'diamonds' => $this->detailDiamondLines([$mountingId])[$mountingId] ?? [],
            'mounted' => $this->mergeMountedDetailWithSetting(
                $this->detailMountedLines([$mountingId])[$mountingId] ?? [],
                $this->detailStoneLines([$mountingId], self::TRANSTYPE_SETTING)[$mountingId] ?? [],
            ),
        ];
    }

    /**
     * @return array{
     *     setting: list<array{batu: string, pcs: int|float|null, crt: string|null, notes: string|null}>,
     *     return: list<array{batu: string, pcs: int|float|null, crt: string|null, notes: string|null}>,
     *     diamonds: list<array{kode: string, diamond: string, bentuk: string, sertifikat: string, crt: string|null}>,
     *     mounted: list<array{kode: string, shape: string, pcs: int|float|null, crt: string|null, size: string}>
     * }
     */
    public function emptyDetail(): array
    {
        return [
            'setting' => [],
            'return' => [],
            'diamonds' => [],
            'mounted' => [],
        ];
    }

    /**
     * @param  array{
     *     setting?: list<array<string, mixed>>,
     *     return?: list<array<string, mixed>>,
     *     diamonds?: list<array<string, mixed>>,
     *     mounted?: list<array<string, mixed>>
     * }  $payload
     */
    public function sync(DiamondMounting $document, array $payload, string $actor): void
    {
        $spkId = filled($document->spk_id) ? (int) $document->spk_id : null;

        $settingLines = $this->collapseStoneLinesByStoneId($payload['setting'] ?? []);

        $this->syncStoneLines(
            $document,
            $spkId,
            self::TRANSTYPE_SETTING,
            $settingLines,
            $actor,
        );
        $this->syncStoneLines(
            $document,
            $spkId,
            self::TRANSTYPE_RETURN,
            $this->collapseStoneLinesByStoneId($payload['return'] ?? []),
            $actor,
        );
        $this->syncDiamondLines($document, $payload['diamonds'] ?? [], $actor);

        $mountedLines = array_merge(
            $this->mountedLinesFromSetting($settingLines),
            $payload['mounted'] ?? [],
        );

        $this->syncMountedLines($document, $mountedLines, $actor);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{stoneId: int, pcs: string, crt: string, notes: string}>
     */
    private function formStoneLinesFor(DiamondMounting $document, int $transtypeId): array
    {
        if (! Schema::connection('third')->hasTable('trstone')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('trstone')
            ->where('ref_row_id', $document->row_id)
            ->where('transtype_id', $transtypeId)
            ->orderBy('row_id');

        if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->get(['stone_id', 'pcs', 'crt', 'notes'])
            ->map(function (object $row): ?array {
                $stoneId = (int) ($row->stone_id ?? 0);

                if ($stoneId <= 0) {
                    return null;
                }

                return [
                    'stoneId' => $stoneId,
                    'pcs' => $this->formatNumber($row->pcs, 0),
                    'crt' => $this->formatNumber($row->crt, 3),
                    'notes' => trim((string) ($row->notes ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{diamondId: int}>
     */
    private function formDiamondLinesFor(DiamondMounting $document): array
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        return array_values(DB::connection('third')
            ->table('trdiamond')
            ->where('diamondmounting_id', $document->row_id)
            ->where('is_deleted', 0)
            ->orderBy('row_id')
            ->pluck('row_id')
            ->map(fn (mixed $id): array => ['diamondId' => (int) $id])
            ->all());
    }

    /**
     * @return list<array{diamondCode: string, shapeId: int|null, pcs: string, crt: string, size: string}>
     */
    private function formMountedLinesFor(DiamondMounting $document): array
    {
        if (! Schema::connection('third')->hasTable('diamondmountingdetail')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('diamondmountingdetail')
            ->where('row_id', $document->row_id)
            ->orderBy('line_id');

        if (Schema::connection('third')->hasColumn('diamondmountingdetail', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->get(['diamond_code', 'shape_id', 'pcs', 'crt', 'size'])
            ->map(fn (object $row): array => [
                'diamondCode' => trim((string) ($row->diamond_code ?? '')),
                'shapeId' => filled($row->shape_id ?? null) && (int) $row->shape_id > 0
                    ? (int) $row->shape_id
                    : null,
                'pcs' => $this->formatNumber($row->pcs, 0),
                'crt' => $this->formatNumber($row->crt, 3),
                'size' => trim((string) ($row->size ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{batu: string, pcs: int|float|null, crt: string|null, notes: string|null}>>
     */
    private function detailStoneLines(array $mountingIds, int $transtypeId): array
    {
        $empty = [];

        foreach ($mountingIds as $mountingId) {
            $empty[$mountingId] = [];
        }

        if (
            $mountingIds === []
            || ! Schema::connection('third')->hasTable('trstone')
            || ! Schema::connection('third')->hasTable('msstone')
        ) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('trstone as t')
            ->leftJoin('msstone as m', 'm.row_id', '=', 't.stone_id')
            ->whereIn('t.ref_row_id', $mountingIds)
            ->where('t.transtype_id', $transtypeId);

        if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $lines = $query
            ->orderBy('t.row_id')
            ->get(['t.ref_row_id', 't.pcs', 't.crt', 't.notes', 'm.name as stone_name']);

        $grouped = $empty;

        foreach ($lines as $line) {
            $mountingId = (int) $line->ref_row_id;

            if (! array_key_exists($mountingId, $grouped)) {
                continue;
            }

            $notes = trim((string) ($line->notes ?? ''));

            $grouped[$mountingId][] = [
                'batu' => filled($line->stone_name ?? null) ? (string) $line->stone_name : 'Batu',
                'pcs' => $this->nullableNumeric($line->pcs),
                'crt' => $this->formatCrt($line->crt),
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{kode: string, diamond: string, bentuk: string, sertifikat: string, crt: string|null}>>
     */
    private function detailDiamondLines(array $mountingIds): array
    {
        $empty = [];

        foreach ($mountingIds as $mountingId) {
            $empty[$mountingId] = [];
        }

        if ($mountingIds === [] || ! Schema::connection('third')->hasTable('trdiamond')) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('trdiamond as t')
            ->whereIn('t.diamondmounting_id', $mountingIds);

        if (Schema::connection('third')->hasColumn('trdiamond', 'is_deleted')) {
            $query->where('t.is_deleted', 0);
        }

        $hasShape = Schema::connection('third')->hasTable('msshape');

        if ($hasShape) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 't.shape_id');
        }

        $select = ['t.diamondmounting_id', 't.doc_no', 't.diamond_type', 't.certificate', 't.crt'];

        if ($hasShape) {
            $select[] = 's.name as shape_name';
        }

        $lines = $query->orderBy('t.row_id')->get($select);
        $grouped = $empty;

        foreach ($lines as $line) {
            $mountingId = (int) $line->diamondmounting_id;

            if (! array_key_exists($mountingId, $grouped)) {
                continue;
            }

            $grouped[$mountingId][] = [
                'kode' => filled($line->doc_no ?? null) ? (string) $line->doc_no : '—',
                'diamond' => filled($line->diamond_type ?? null) ? (string) $line->diamond_type : '—',
                'bentuk' => filled($line->shape_name ?? null) ? (string) $line->shape_name : '—',
                'sertifikat' => filled($line->certificate ?? null) ? (string) $line->certificate : '—',
                'crt' => $this->formatCrt($line->crt),
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{kode: string, shape: string, pcs: int|float|null, crt: string|null, size: string}>>
     */
    private function detailMountedLines(array $mountingIds): array
    {
        $empty = [];

        foreach ($mountingIds as $mountingId) {
            $empty[$mountingId] = [];
        }

        if ($mountingIds === [] || ! Schema::connection('third')->hasTable('diamondmountingdetail')) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('diamondmountingdetail as d')
            ->whereIn('d.row_id', $mountingIds);

        if (Schema::connection('third')->hasColumn('diamondmountingdetail', 'is_deleted')) {
            $query->where('d.is_deleted', 0);
        }

        $hasShape = Schema::connection('third')->hasTable('msshape');

        if ($hasShape) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 'd.shape_id');
        }

        $select = ['d.row_id', 'd.diamond_code', 'd.pcs', 'd.crt', 'd.size'];

        if ($hasShape) {
            $select[] = 's.name as shape_name';
        }

        $lines = $query->orderBy('d.line_id')->get($select);
        $grouped = $empty;

        foreach ($lines as $line) {
            $mountingId = (int) $line->row_id;

            if (! array_key_exists($mountingId, $grouped)) {
                continue;
            }

            $grouped[$mountingId][] = [
                'kode' => filled($line->diamond_code ?? null) ? (string) $line->diamond_code : '—',
                'shape' => filled($line->shape_name ?? null) ? (string) $line->shape_name : '—',
                'pcs' => $this->nullableNumeric($line->pcs),
                'crt' => $this->formatCrt($line->crt),
                'size' => filled($line->size ?? null) ? (string) $line->size : '—',
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<array{
     *     kode?: string,
     *     diamond_code?: string,
     *     pcs?: int|float|string|null,
     *     crt?: int|float|string|null
     * }>  $mounted
     * @param  list<array{
     *     batu: string,
     *     pcs: int|float|null,
     *     crt: string|null,
     *     notes: string|null
     * }>  $setting
     * @return list<array{kode: string, shape: string, pcs: int|float|null, crt: string|null, size: string}>
     */
    private function mergeMountedDetailWithSetting(array $mounted, array $setting): array
    {
        $seen = [];

        foreach ($mounted as $row) {
            $seen[$this->mountedRowFingerprint($row)] = true;
        }

        foreach ($setting as $row) {
            $converted = [
                'kode' => (string) ($row['batu'] ?? 'Batu'),
                'shape' => '—',
                'pcs' => $row['pcs'] ?? null,
                'crt' => $row['crt'] ?? null,
                'size' => filled($row['notes'] ?? null) ? (string) $row['notes'] : '—',
            ];

            $fingerprint = $this->mountedRowFingerprint($converted);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $mounted[] = $converted;
            $seen[$fingerprint] = true;
        }

        return $mounted;
    }

    /**
     * @param  array{kode?: string, diamond_code?: string, pcs?: mixed, crt?: mixed}  $row
     */
    private function mountedRowFingerprint(array $row): string
    {
        $kode = strtolower(trim((string) ($row['kode'] ?? $row['diamond_code'] ?? '')));
        $kode = preg_replace('/\s*\([^)]*\)\s*$/', '', $kode) ?? $kode;
        $pcs = trim((string) ($row['pcs'] ?? ''));
        $crt = trim((string) ($row['crt'] ?? ''));

        return $kode.'|'.$pcs.'|'.$crt;
    }

    /**
     * @param  list<array<string, mixed>>  $settingLines
     * @return list<array{diamond_code: string, shape_id: null, pcs: string|null, crt: string|null, size: string|null}>
     */
    private function mountedLinesFromSetting(array $settingLines): array
    {
        if ($settingLines === [] || ! Schema::connection('third')->hasTable('msstone')) {
            return [];
        }

        $stoneIds = array_values(array_filter(
            array_map(fn (array $line): int => (int) ($line['stone_id'] ?? 0), $settingLines),
            fn (int $id): bool => $id > 0,
        ));

        if ($stoneIds === []) {
            return [];
        }

        $names = DB::connection('third')
            ->table('msstone')
            ->whereIn('row_id', $stoneIds)
            ->pluck('name', 'row_id');

        $mounted = [];

        foreach ($settingLines as $line) {
            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $label = trim((string) ($names[$stoneId] ?? ''));

            if ($label === '') {
                $label = 'Batu #'.$stoneId;
            }

            $notes = trim((string) ($line['notes'] ?? ''));

            if ($notes !== '') {
                $label = $label.' ('.$notes.')';
            }

            $mounted[] = [
                'diamond_code' => $label,
                'shape_id' => null,
                'pcs' => filled($line['pcs'] ?? null)
                    ? (string) $line['pcs']
                    : null,
                'crt' => filled($line['crt'] ?? null)
                    ? (string) $line['crt']
                    : null,
                'size' => null,
            ];
        }

        return $mounted;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function collapseStoneLinesByStoneId(array $lines): array
    {
        /** @var array<int, array{stone_id: int, pcs: float, crt: float, notes: list<string>}> $merged */
        $merged = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $pcs = $this->toFloat($line['pcs'] ?? null) ?? 0.0;
            $crt = $this->toFloat($line['crt'] ?? null) ?? 0.0;
            $note = trim((string) ($line['notes'] ?? ''));

            if (! isset($merged[$stoneId])) {
                $merged[$stoneId] = [
                    'stone_id' => $stoneId,
                    'pcs' => $pcs,
                    'crt' => $crt,
                    'notes' => $note !== '' ? [$note] : [],
                ];

                continue;
            }

            $merged[$stoneId]['pcs'] += $pcs;
            $merged[$stoneId]['crt'] += $crt;

            if ($note !== '' && ! in_array($note, $merged[$stoneId]['notes'], true)) {
                $merged[$stoneId]['notes'][] = $note;
            }
        }

        ksort($merged);

        return array_values(array_map(
            function (array $line): array {
                $notes = collect($line['notes'])
                    ->filter(fn (string $note): bool => $note !== '')
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'stone_id' => $line['stone_id'],
                    'pcs' => $line['pcs'] > 0 ? $this->formatNumber($line['pcs'], 0) : null,
                    'crt' => $line['crt'] > 0 ? $this->formatNumber($line['crt'], 3) : null,
                    'notes' => $notes !== '' ? $notes : null,
                ];
            },
            $merged,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncStoneLines(
        DiamondMounting $document,
        ?int $spkId,
        int $transtypeId,
        array $lines,
        string $actor,
    ): void {
        if (! Schema::connection('third')->hasTable('trstone')) {
            return;
        }

        $query = DB::connection('third')
            ->table('trstone')
            ->where('ref_row_id', $document->row_id)
            ->where('transtype_id', $transtypeId);

        if (Schema::connection('third')->hasColumn('trstone', 'is_deleted')) {
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

        foreach ($lines as $line) {
            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $pcs = $this->toFloat($line['pcs'] ?? null);
            $crt = $this->toFloat($line['crt'] ?? null);
            $notes = trim((string) ($line['notes'] ?? ''));

            DB::connection('third')->table('trstone')->insert([
                'period_id' => 1,
                'transtype_id' => $transtypeId,
                'ref_row_id' => $document->row_id,
                'stone_id' => $stoneId,
                'spk_id' => $spkId,
                'pcs' => $pcs,
                'crt' => $crt !== null ? number_format($crt, 3, '.', '') : null,
                'txt' => null,
                'notes' => $notes !== '' ? $notes : null,
                'is_used' => 0,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
                'deleted_date' => null,
                'deleted_by' => null,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncDiamondLines(DiamondMounting $document, array $lines, string $actor): void
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return;
        }

        $diamondIds = array_values(array_filter(
            array_map(fn (array $line): int => (int) ($line['diamond_id'] ?? 0), $lines),
            fn (int $diamondId): bool => $diamondId > 0,
        ));

        $this->diamondDossierInventory->assignToMounting((int) $document->row_id, $diamondIds, $actor);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncMountedLines(DiamondMounting $document, array $lines, string $actor): void
    {
        if (! Schema::connection('third')->hasTable('diamondmountingdetail')) {
            return;
        }

        $query = DB::connection('third')
            ->table('diamondmountingdetail')
            ->where('row_id', $document->row_id);

        if (Schema::connection('third')->hasColumn('diamondmountingdetail', 'is_deleted')) {
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

        foreach ($lines as $line) {
            $diamondCode = trim((string) ($line['diamond_code'] ?? ''));
            $shapeId = filled($line['shape_id'] ?? null) && (int) $line['shape_id'] > 0
                ? (int) $line['shape_id']
                : null;
            $pcs = $this->toFloat($line['pcs'] ?? null);
            $crt = $this->toFloat($line['crt'] ?? null);
            $size = trim((string) ($line['size'] ?? ''));

            if ($diamondCode === '' && $shapeId === null && $pcs === null && $crt === null && $size === '') {
                continue;
            }

            DB::connection('third')->table('diamondmountingdetail')->insert([
                'row_id' => $document->row_id,
                'diamond_code' => $diamondCode !== '' ? $diamondCode : null,
                'shape_id' => $shapeId,
                'pcs' => $pcs,
                'crt' => $crt !== null ? number_format($crt, 3, '.', '') : null,
                'size' => $size !== '' ? $size : null,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
                'deleted_date' => null,
                'deleted_by' => null,
            ]);
        }
    }

    private function formatNumber(mixed $value, int $precision): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        if ($precision === 0 && abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return number_format($number, $precision, '.', '');
    }

    private function formatCrt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 3, '.', '');
    }

    private function nullableNumeric(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        return str_contains($string, '.') ? (float) $value : (int) $value;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
