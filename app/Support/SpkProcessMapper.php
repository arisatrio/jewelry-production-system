<?php

namespace App\Support;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkProcessMapper
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     tables: list<string>,
     *     placement: string,
     *     parent?: array<string, array{
     *         table: string,
     *         local_key: string,
     *         owner_key: string,
     *         fields: array<string, string>
     *     }>
     * }>
     */
    public function tabs(): array
    {
        /** @var list<array{key: string, label: string, tables: list<string>, placement?: string, parent?: array<string, mixed>}> $tabs */
        $tabs = config('spk_processes.tabs', []);

        return array_map(
            function (array $tab): array {
                $tab['placement'] = $tab['placement'] ?? 'proses-produksi';

                return $tab;
            },
            $tabs,
        );
    }

    /**
     * @return list<string>
     */
    public function tabLabels(): array
    {
        return array_values(array_map(
            fn (array $tab): string => $tab['label'],
            $this->tabs(),
        ));
    }

    /**
     * Resolve which main section and production sub-tab should open by default
     * based on Production.last_process / proses terakhir.
     *
     * @return array{mainSection: string, processKey: string}
     */
    public function resolveDefaultSelection(?string $lastProcess): array
    {
        $tabs = $this->tabs();
        $productionTabs = array_values(array_filter(
            $tabs,
            fn (array $tab): bool => ($tab['placement'] ?? 'proses-produksi') === 'proses-produksi',
        ));
        $fallbackProcessKey = $productionTabs[0]['key'] ?? 'JewelCAD';
        $matched = $this->matchTabByLastProcess($lastProcess, $tabs);

        if ($matched === null) {
            return [
                'mainSection' => 'informasi-produksi',
                'processKey' => $fallbackProcessKey,
            ];
        }

        return [
            'mainSection' => 'informasi-produksi',
            'processKey' => ($matched['placement'] ?? 'proses-produksi') === 'main'
                ? $fallbackProcessKey
                : $matched['key'],
        ];
    }

    /**
     * Resolve process table names for a given Production.last_process value.
     *
     * @return list<string>
     */
    public function tablesForLastProcess(?string $lastProcess): array
    {
        $matched = $this->matchTabByLastProcess($lastProcess, $this->tabs());

        if ($matched === null) {
            return [];
        }

        /** @var list<string> $tables */
        $tables = $matched['tables'] ?? [];

        return array_values(array_filter(
            $tables,
            fn (mixed $table): bool => is_string($table) && $table !== '',
        ));
    }

    /**
     * @param  list<array{key: string, label: string, tables: list<string>, placement: string}>  $tabs
     * @return array{key: string, label: string, tables: list<string>, placement: string}|null
     */
    private function matchTabByLastProcess(?string $lastProcess, array $tabs): ?array
    {
        $value = trim((string) $lastProcess);

        if ($value === '' || strcasecmp($value, 'Done') === 0) {
            return null;
        }

        foreach ($tabs as $tab) {
            if (strcasecmp($tab['key'], $value) === 0 || strcasecmp($tab['label'], $value) === 0) {
                return $tab;
            }
        }

        $normalized = $this->normalizeProcessName($value);
        $aliases = [
            'finishinghandmade' => 'Finishing',
            'finishing' => 'Finishing',
            'jewelcud' => 'JewelCAD',
            'jewelcad' => 'JewelCAD',
            'grafir' => 'Pengerjaan Lanjutan',
            'polesbarangjadi' => 'Poles Chrome',
            'polishfinishedgood' => 'Poles Chrome',
            'poleschrome' => 'Poles Chrome',
            'polesrangka' => 'Poles Rangka',
            'polishframe' => 'Poles Rangka',
            'pasangbatu' => 'Pasang Batu',
            'modifikasibarangjadi' => 'Modifikasi Barang Jadi',
            'pengerjaanlanjutan' => 'Pengerjaan Lanjutan',
        ];

        $aliasedKey = $aliases[$normalized] ?? null;

        if ($aliasedKey !== null) {
            foreach ($tabs as $tab) {
                if ($tab['key'] === $aliasedKey) {
                    return $tab;
                }
            }
        }

        foreach ($tabs as $tab) {
            $tabNormalized = $this->normalizeProcessName($tab['key']);
            $labelNormalized = $this->normalizeProcessName($tab['label']);

            if (
                $tabNormalized !== '' && (
                    str_contains($normalized, $tabNormalized)
                    || str_contains($tabNormalized, $normalized)
                    || str_contains($normalized, $labelNormalized)
                    || str_contains($labelNormalized, $normalized)
                )
            ) {
                return $tab;
            }
        }

        return null;
    }

    private function normalizeProcessName(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     tables: list<string>,
     *     placement: string,
     *     recordCount: int,
     *     sources: list<array{table: string, recordCount: int, records: list<array<string, mixed>>}>
     * }>
     */
    public function forProduction(int $spkId): array
    {
        return array_map(
            fn (array $tab): array => $this->mapTab($spkId, $tab),
            $this->tabs(),
        );
    }

    /**
     * @param  array{
     *     key: string,
     *     label: string,
     *     tables: list<string>,
     *     placement?: string,
     *     parent?: array<string, array{
     *         table: string,
     *         local_key: string,
     *         owner_key: string,
     *         fields: array<string, string>
     *     }>
     * }  $tab
     * @return array{
     *     key: string,
     *     label: string,
     *     tables: list<string>,
     *     placement: string,
     *     recordCount: int,
     *     sources: list<array{table: string, recordCount: int, records: list<array<string, mixed>>}>
     * }
     */
    private function mapTab(int $spkId, array $tab): array
    {
        $sources = [];
        $total = 0;

        foreach ($tab['tables'] as $table) {
            $records = $this->recordsForTable($spkId, $table, $tab['parent'][$table] ?? null);
            $count = count($records);
            $total += $count;

            $sources[] = [
                'table' => $table,
                'recordCount' => $count,
                'records' => $records,
            ];
        }

        return [
            'key' => $tab['key'],
            'label' => $tab['label'],
            'tables' => $tab['tables'],
            'placement' => $tab['placement'] ?? 'proses-produksi',
            'recordCount' => $total,
            'sources' => $sources,
        ];
    }

    /**
     * @param  array{
     *     table: string,
     *     local_key: string,
     *     owner_key: string,
     *     fields: array<string, string>
     * }|null  $parent
     * @return list<array<string, mixed>>
     */
    private function recordsForTable(int $spkId, string $table, ?array $parent = null): array
    {
        if (! Schema::connection('third')->hasTable($table)) {
            return [];
        }

        $query = DB::connection('third')
            ->table($table)
            ->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if (Schema::connection('third')->hasColumn($table, 'created_date')) {
            $query->orderBy('created_date');
        } elseif (Schema::connection('third')->hasColumn($table, 'created_at')) {
            $query->orderBy('created_at');
        }

        if (Schema::connection('third')->hasColumn($table, 'row_id')) {
            $query->orderBy('row_id');
        } elseif (Schema::connection('third')->hasColumn($table, 'line_id')) {
            $query->orderBy('line_id');
        }

        $records = $query
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->values()
            ->all();

        if ($parent !== null && $records !== []) {
            $records = $this->enrichWithParent($records, $parent);
        }

        $records = $this->enrichWithCraftsmanNames($records);
        $records = $this->enrichWithCoranMetrics($table, $records, $spkId);
        $records = $this->enrichWithMaterialBreakdown($table, $records);
        $records = $this->enrichWithDiamondMountingDetails($table, $records, $spkId);

        return $this->enrichWithApprovals($table, $records);
    }

    /**
     * @return list<array{
     *     status: string,
     *     statusLabel: string,
     *     approve: string,
     *     notes: string|null,
     *     createdBy: string|null,
     *     createdAt: string|null
     * }>
     */
    private function enrichWithApprovals(string $table, array $records): array
    {
        if ($records === [] || ! Schema::connection('third')->hasTable('sysapproval')) {
            return array_map(
                fn (array $record): array => [...$record, 'approvals' => []],
                $records,
            );
        }

        $docName = $this->approvalDocName($table);
        $docIds = collect($records)
            ->pluck('row_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($docIds === []) {
            return array_map(
                fn (array $record): array => [...$record, 'approvals' => []],
                $records,
            );
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', $docName)
            ->whereIn('doc_id', $docIds);

        if (Schema::connection('third')->hasColumn('sysapproval', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $rows = $query
            ->orderBy('created_date')
            ->orderBy('row_id')
            ->get([
                'doc_id',
                'status',
                'approve',
                'notes',
                'created_date',
                'created_by',
            ])
            ->groupBy(fn (object $row): int => (int) $row->doc_id);

        $statusLabels = $this->resolveApprovalStatusLabels($docName);

        return array_map(function (array $record) use ($rows, $statusLabels): array {
            $docId = (int) ($record['row_id'] ?? 0);
            $approvals = ($rows->get($docId) ?? collect())
                ->map(function (object $row) use ($statusLabels): array {
                    $status = (string) ($row->status ?? '');

                    return [
                        'status' => $status,
                        'statusLabel' => $statusLabels[$status] ?? $status,
                        'approve' => filled($row->approve ?? null)
                            ? (string) $row->approve
                            : '—',
                        'notes' => filled($row->notes ?? null)
                            ? (string) $row->notes
                            : null,
                        'createdBy' => filled($row->created_by ?? null)
                            ? (string) $row->created_by
                            : null,
                        'createdAt' => filled($row->created_date ?? null)
                            ? (string) $row->created_date
                            : null,
                    ];
                })
                ->values()
                ->all();

            return [
                ...$record,
                'approvals' => $approvals,
            ];
        }, $records);
    }

    private function approvalDocName(string $table): string
    {
        return match ($table) {
            'coranspk' => 'coran',
            'requestjwcaddetails' => 'requestjwcad',
            'resindetails' => 'resin',
            default => $table,
        };
    }

    /**
     * @return array<string, string>
     */
    private function resolveApprovalStatusLabels(string $docName): array
    {
        $fallbacks = $this->approvalStatusLabelFallbacks($docName);

        if (! Schema::connection('third')->hasTable('sysstatus')) {
            return $fallbacks;
        }

        $query = DB::connection('third')
            ->table('sysstatus')
            ->where('doc_name', $docName);

        if (Schema::connection('third')->hasColumn('sysstatus', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $labels = $query
            ->pluck('current_status', 'code')
            ->map(fn (mixed $label): string => (string) $label)
            ->filter(fn (string $label, mixed $code): bool => $label !== '' && $label !== (string) $code)
            ->all();

        return array_merge($fallbacks, $labels);
    }

    /**
     * @return array<string, string>
     */
    private function approvalStatusLabelFallbacks(string $docName): array
    {
        return match ($docName) {
            'resin' => [
                ResinApprovalService::STATUS_SUBMITTED => 'Pengajuan Approval',
                ResinApprovalService::STATUS_MANAGER => 'Serahkan ke Resin',
                ResinApprovalService::STATUS_DONE => 'Completed',
            ],
            'requestjwcad' => [
                JewelCadApprovalService::STATUS_SUBMITTED => 'Pengajuan Approval',
                JewelCadApprovalService::STATUS_MANAGER => 'Serahkan ke JWCAD',
                JewelCadApprovalService::STATUS_DONE => 'Completed',
            ],
            default => [],
        };
    }

    /**
     * Aggregate coran parent material totals and SPK usage % by gold color.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function enrichWithCoranMetrics(string $table, array $records, int $spkId): array
    {
        if ($table !== 'coranspk' || $records === []) {
            return $records;
        }

        if (! Schema::connection('third')->hasTable('coran')) {
            return $records;
        }

        $parentIds = collect($records)
            ->pluck('row_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($parentIds === []) {
            return $records;
        }

        $parents = DB::connection('third')
            ->table('coran')
            ->whereIn('row_id', $parentIds)
            ->get()
            ->keyBy('row_id');

        $breakdowns = $this->resolveCoranMaterialBreakdowns($parentIds);

        $craftsmanIds = $parents
            ->pluck('craftsman_id')
            ->filter(fn (mixed $id): bool => $id !== null && $id !== '' && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $craftsmanNames = [];

        if (
            $craftsmanIds !== []
            && Schema::connection('third')->hasTable('mscraftsman')
        ) {
            $craftsmanNames = DB::connection('third')
                ->table('mscraftsman')
                ->whereIn('row_id', $craftsmanIds)
                ->pluck('name', 'row_id')
                ->map(fn (mixed $name): string => (string) $name)
                ->all();
        }

        $productionMeta = Production::query()
            ->where('row_id', $spkId)
            ->first(['gold_color', 'spk_no']);

        $goldColor = $productionMeta?->gold_color;
        $spkNo = filled($productionMeta?->spk_no)
            ? trim((string) $productionMeta->spk_no)
            : null;

        $colorKey = $this->resolveCoranGoldColorKey(
            is_string($goldColor) ? $goldColor : null,
        );

        return array_map(function (array $record) use (
            $parents,
            $breakdowns,
            $craftsmanNames,
            $goldColor,
            $spkNo,
            $colorKey,
        ): array {
            $parent = $parents->get($record['row_id'] ?? null);
            $breakdown = $breakdowns[(int) ($record['row_id'] ?? 0)]
                ?? $this->emptyCoranBreakdown();

            if ($parent === null) {
                return [
                    ...$record,
                    'pengrajin' => null,
                    'craftsman_id' => null,
                    'total_submit_material' => null,
                    'total_result_material' => null,
                    'submit_materials' => [],
                    'result_materials' => [],
                    'coran_breakdown' => $breakdown,
                    'shrink' => null,
                    'shrink_percent' => null,
                    'spk_usage_percent' => null,
                    'spk_usage_gold_color' => null,
                    'spk_no' => $spkNo,
                ];
            }

            $craftsmanId = isset($parent->craftsman_id) && $parent->craftsman_id !== null && $parent->craftsman_id !== ''
                ? (int) $parent->craftsman_id
                : null;

            if ($craftsmanId === 0) {
                $craftsmanId = null;
            }

            $pengrajin = $craftsmanId !== null
                ? ($craftsmanNames[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                : null;

            $submitRose = (float) ($parent->submit_material_rosegold ?? 0);
            $submitWhite = (float) ($parent->submit_material_whitegold ?? 0);
            $submitYellow = (float) ($parent->submit_material_yellowgold ?? 0);
            $resultRose = (float) ($parent->result_material_rosegold ?? 0);
            $resultWhite = (float) ($parent->result_material_whitegold ?? 0);
            $resultYellow = (float) ($parent->result_material_yellowgold ?? 0);
            $totalSubmit = round($submitRose + $submitWhite + $submitYellow, 3);
            $totalResult = round($resultRose + $resultWhite + $resultYellow, 3);

            $submitByColor = [
                'rosegold' => $submitRose,
                'whitegold' => $submitWhite,
                'yellowgold' => $submitYellow,
            ];
            $resultByColor = [
                'rosegold' => $resultRose,
                'whitegold' => $resultWhite,
                'yellowgold' => $resultYellow,
            ];

            $spkWeight = isset($record['weight']) && $record['weight'] !== null && $record['weight'] !== ''
                ? (float) $record['weight']
                : null;

            $usageBase = null;

            if ($colorKey === 'twotone') {
                $usageBase = $submitRose + $submitWhite;
                if (abs($usageBase) < 0.0005) {
                    $usageBase = $resultRose + $resultWhite;
                }
            } elseif ($colorKey !== null) {
                $usageBase = $submitByColor[$colorKey];
                if (abs($usageBase) < 0.0005) {
                    $usageBase = $resultByColor[$colorKey];
                }
            }

            $usagePercent = null;

            if ($spkWeight !== null && $usageBase !== null && abs($usageBase) >= 0.0005) {
                $usagePercent = round(($spkWeight / $usageBase) * 100, 2);
            }

            $shrink = isset($parent->shrink) && $parent->shrink !== null && $parent->shrink !== ''
                ? round((float) $parent->shrink, 3)
                : null;

            $shrinkPercent = null;

            if ($shrink !== null && abs($totalSubmit) >= 0.0005) {
                $shrinkPercent = round(($shrink / $totalSubmit) * 100, 2);
            }

            return [
                ...$record,
                'pengrajin' => $pengrajin,
                'craftsman_id' => $craftsmanId,
                'total_submit_material' => $totalSubmit,
                'total_result_material' => $totalResult,
                'submit_materials' => $this->coranMaterialLines([
                    'Rose Gold' => $submitRose,
                    'White Gold' => $submitWhite,
                    'Yellow Gold' => $submitYellow,
                ]),
                'result_materials' => $this->coranMaterialLines([
                    'Rose Gold' => $resultRose,
                    'White Gold' => $resultWhite,
                    'Yellow Gold' => $resultYellow,
                ]),
                'coran_breakdown' => $breakdown,
                'shrink' => $shrink,
                'shrink_percent' => $shrinkPercent,
                'spk_usage_percent' => $usagePercent,
                'spk_usage_gold_color' => filled($goldColor) ? trim((string) $goldColor) : null,
                'spk_no' => $spkNo,
            ];
        }, $records);
    }

    /**
     * @param  array<string, float>  $materials
     * @return list<array{name: string, weight: float, notes: null}>
     */
    private function coranMaterialLines(array $materials): array
    {
        $lines = [];

        foreach ($materials as $name => $weight) {
            if (abs($weight) < 0.0005) {
                continue;
            }

            $lines[] = [
                'name' => $name,
                'weight' => round($weight, 3),
                'notes' => null,
            ];
        }

        return $lines;
    }

    /**
     * Bahan / sisa line items from trmaterialgold for each coran form.
     *
     * @param  list<int|string>  $coranIds
     * @return array<int, list<array{
     *     color: string,
     *     colorKey: string,
     *     bahan: list<array{name: string, weight: float}>,
     *     sisa: list<array{name: string, weight: float}>
     * }>>
     */
    private function resolveCoranMaterialBreakdowns(array $coranIds): array
    {
        $empty = [];

        foreach ($coranIds as $coranId) {
            $empty[(int) $coranId] = $this->emptyCoranBreakdown();
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
    private function emptyCoranBreakdown(): array
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

    private function resolveCoranGoldColorKey(?string $goldColor): ?string
    {
        if ($goldColor === null || trim($goldColor) === '') {
            return null;
        }

        $normalized = strtolower(trim($goldColor));

        if (str_contains($normalized, 'two') || str_contains($normalized, 'tone')) {
            return 'twotone';
        }

        if (str_contains($normalized, 'rose') || str_starts_with($normalized, 'rg')) {
            return 'rosegold';
        }

        if (str_contains($normalized, 'yellow')) {
            return 'yellowgold';
        }

        if (str_contains($normalized, 'white')) {
            return 'whitegold';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function enrichWithCraftsmanNames(array $records): array
    {
        if ($records === []) {
            return $records;
        }

        $idKey = array_key_exists('craftsman_id', $records[0])
            ? 'craftsman_id'
            : (array_key_exists('craftman_id', $records[0]) ? 'craftman_id' : null);

        if ($idKey === null) {
            return $records;
        }

        if (! Schema::connection('third')->hasTable('mscraftsman')) {
            return $records;
        }

        $craftsmanIds = collect($records)
            ->pluck($idKey)
            ->filter(fn (mixed $id): bool => $id !== null && $id !== '' && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($craftsmanIds === []) {
            return array_map(
                fn (array $record): array => [
                    'craftsman_name' => null,
                    'craftsman_id' => null,
                    ...$record,
                ],
                $records,
            );
        }

        $names = DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $craftsmanIds)
            ->pluck('name', 'row_id');

        return array_map(function (array $record) use ($names, $idKey): array {
            $rawId = $record[$idKey] ?? null;
            $craftsmanId = $rawId !== null && $rawId !== '' && (int) $rawId > 0
                ? (int) $rawId
                : null;

            return [
                'craftsman_name' => $craftsmanId !== null
                    ? ($names[$craftsmanId] ?? null)
                    : null,
                'craftsman_id' => $craftsmanId,
                ...$record,
            ];
        }, $records);
    }

    /**
     * Attach finishing report fields + per-material gold lines (serah / kembali).
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array{
     *     materials_out?: list<array{name: string, weight: float, notes: string|null}>,
     *     materials_in?: list<array{name: string, weight: float, notes: string|null}>,
     *     tanggal?: string|null,
     *     pengrajin?: string|null,
     *     shrink_percent?: float|null,
     *     ...
     * }>
     */
    private function enrichWithMaterialBreakdown(string $table, array $records): array
    {
        if ($table !== 'finishinghandmade' || $records === []) {
            return $records;
        }

        $canLoadMaterials = Schema::connection('third')->hasTable('trmaterialgold')
            && Schema::connection('third')->hasTable('msmaterialgold');

        $linesByRef = collect();

        if ($canLoadMaterials) {
            $refIds = collect($records)
                ->pluck('row_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($refIds !== []) {
                $materialQuery = DB::connection('third')
                    ->table('trmaterialgold as t')
                    ->leftJoin('msmaterialgold as m', 'm.row_id', '=', 't.materialgold_id')
                    ->whereIn('t.ref_row_id', $refIds)
                    ->whereIn('t.transtype_id', [5, 6]);

                if (Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')) {
                    $materialQuery->where('t.is_deleted', 0);
                }

                $linesByRef = $materialQuery
                    ->orderBy('t.row_id')
                    ->get([
                        't.ref_row_id',
                        't.transtype_id',
                        't.weight',
                        't.notes',
                        'm.name as material_name',
                    ])
                    ->groupBy('ref_row_id');
            }
        }

        return array_map(function (array $record) use ($linesByRef): array {
            $refLines = $linesByRef->get($record['row_id'] ?? null, collect());

            $mapLine = fn (object $line): array => [
                'name' => $line->material_name ?: 'Bahan',
                'weight' => round((float) $line->weight, 3),
                'notes' => $line->notes !== null && $line->notes !== ''
                    ? (string) $line->notes
                    : null,
            ];

            $tanggal = null;

            if (filled($record['send_craftsman_date'] ?? null)) {
                $tanggal = Carbon::parse((string) $record['send_craftsman_date'])->format('d-M-Y');
            }

            $pengrajin = filled($record['craftsman_name'] ?? null)
                ? (string) $record['craftsman_name']
                : null;

            $shrink = isset($record['shrink']) && $record['shrink'] !== null && $record['shrink'] !== ''
                ? round((float) $record['shrink'], 3)
                : null;

            $startWeight = isset($record['start_weight']) && $record['start_weight'] !== null && $record['start_weight'] !== ''
                ? (float) $record['start_weight']
                : null;

            $shrinkPercent = null;

            if ($shrink !== null && $startWeight !== null && abs($startWeight) >= 0.0005) {
                $shrinkPercent = round(($shrink / $startWeight) * 100, 2);
            }

            return [
                ...$record,
                'materials_out' => $refLines
                    ->where('transtype_id', 5)
                    ->values()
                    ->map($mapLine)
                    ->all(),
                'materials_in' => $refLines
                    ->where('transtype_id', 6)
                    ->values()
                    ->map($mapLine)
                    ->all(),
                'tanggal' => $tanggal,
                'pengrajin' => $pengrajin,
                'shrink_percent' => $shrinkPercent,
            ];
        }, $records);
    }

    /**
     * Attach stone setting / return / diamond / mounted stone tables for Pasang Batu.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function enrichWithDiamondMountingDetails(string $table, array $records, int $spkId): array
    {
        if ($table !== 'diamondmounting' || $records === []) {
            return $records;
        }

        $mountingIds = collect($records)
            ->pluck('row_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($mountingIds === []) {
            return $records;
        }

        $settingByRef = $this->resolveMountingStoneLines($mountingIds, 7);
        $returnByRef = $this->resolveMountingStoneLines($mountingIds, 8);
        $diamondsByMounting = $this->resolveMountingDiamonds($mountingIds);
        $mountedByMounting = $this->resolveMountedStones($mountingIds);

        $spkNo = Production::query()
            ->where('row_id', $spkId)
            ->value('spk_no');
        $spkNo = filled($spkNo) ? trim((string) $spkNo) : null;

        return array_map(function (array $record) use (
            $settingByRef,
            $returnByRef,
            $diamondsByMounting,
            $mountedByMounting,
            $spkNo,
        ): array {
            $mountingId = (int) ($record['row_id'] ?? 0);

            $framePlusStone = isset($record['total_weigth_frame_diamond'])
                && $record['total_weigth_frame_diamond'] !== null
                && $record['total_weigth_frame_diamond'] !== ''
                ? (float) $record['total_weigth_frame_diamond']
                : null;
            $finishWeight = isset($record['weight_finish_goods'])
                && $record['weight_finish_goods'] !== null
                && $record['weight_finish_goods'] !== ''
                ? (float) $record['weight_finish_goods']
                : null;

            $mountingShrink = isset($record['mounting_shrink'])
                && $record['mounting_shrink'] !== null
                && $record['mounting_shrink'] !== ''
                ? round((float) $record['mounting_shrink'], 3)
                : null;

            if (
                $mountingShrink === null
                && $framePlusStone !== null
                && $finishWeight !== null
            ) {
                $mountingShrink = round($framePlusStone - $finishWeight, 3);
            }

            $tanggal = null;

            if (filled($record['trans_date'] ?? null)) {
                $tanggal = Carbon::parse((string) $record['trans_date'])->format('d-M-Y');
            }

            return [
                ...$record,
                'tanggal' => $tanggal,
                'spk_no' => $spkNo,
                'mounting_shrink' => $mountingShrink,
                'stone_setting' => $settingByRef[$mountingId] ?? [],
                'stone_return' => $returnByRef[$mountingId] ?? [],
                'stone_diamonds' => $diamondsByMounting[$mountingId] ?? [],
                'stone_mounted' => $mountedByMounting[$mountingId] ?? [],
            ];
        }, $records);
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{batu: string, pcs: int|float|null, crt: float|null}>>
     */
    private function resolveMountingStoneLines(array $mountingIds, int $transtypeId): array
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

        $hasShape = Schema::connection('third')->hasTable('msshape');

        if ($hasShape) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 'm.shape_id');
        }

        $select = [
            't.ref_row_id',
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
            ->orderBy('t.row_id')
            ->get($select);

        $grouped = $empty;

        foreach ($lines as $line) {
            $mountingId = (int) $line->ref_row_id;

            if (! array_key_exists($mountingId, $grouped)) {
                continue;
            }

            $batu = filled($line->stone_name ?? null)
                ? (string) $line->stone_name
                : trim(implode(' ', array_filter([
                    $line->shape_name ?? null,
                    $line->parcel ?? null,
                    filled($line->stone_size ?? null) ? ((string) $line->stone_size).' MM' : null,
                ])));

            $grouped[$mountingId][] = [
                'batu' => $batu !== '' ? $batu : 'Batu',
                'pcs' => $line->pcs !== null && $line->pcs !== ''
                    ? (str_contains((string) $line->pcs, '.') ? (float) $line->pcs : (int) $line->pcs)
                    : null,
                'crt' => $line->crt !== null && $line->crt !== ''
                    ? round((float) $line->crt, 4)
                    : null,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{kode: string, diamond: string, bentuk: string, sertifikat: string, crt: float|null}>>
     */
    private function resolveMountingDiamonds(array $mountingIds): array
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

        if (Schema::connection('third')->hasTable('msshape')) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 't.shape_id');
        }

        $select = [
            't.diamondmounting_id',
            't.doc_no',
            't.diamond_type',
            't.certificate',
            't.crt',
        ];

        if (Schema::connection('third')->hasTable('msshape')) {
            $select[] = 's.name as shape_name';
        }

        $lines = $query
            ->orderBy('t.row_id')
            ->get($select);

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
                'crt' => $line->crt !== null && $line->crt !== ''
                    ? round((float) $line->crt, 4)
                    : null,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $mountingIds
     * @return array<int, list<array{kode: string, shape: string, pcs: int|float|null, crt: float|null, size: string}>>
     */
    private function resolveMountedStones(array $mountingIds): array
    {
        $empty = [];

        foreach ($mountingIds as $mountingId) {
            $empty[$mountingId] = [];
        }

        if (
            $mountingIds === []
            || ! Schema::connection('third')->hasTable('diamondmountingdetail')
        ) {
            return $empty;
        }

        $query = DB::connection('third')
            ->table('diamondmountingdetail as d')
            ->whereIn('d.row_id', $mountingIds);

        if (Schema::connection('third')->hasColumn('diamondmountingdetail', 'is_deleted')) {
            $query->where('d.is_deleted', 0);
        }

        if (Schema::connection('third')->hasTable('msshape')) {
            $query->leftJoin('msshape as s', 's.row_id', '=', 'd.shape_id');
        }

        $select = [
            'd.row_id',
            'd.diamond_code',
            'd.pcs',
            'd.crt',
            'd.size',
        ];

        if (Schema::connection('third')->hasTable('msshape')) {
            $select[] = 's.name as shape_name';
        }

        $lines = $query
            ->orderBy('d.line_id')
            ->get($select);

        $grouped = $empty;

        foreach ($lines as $line) {
            $mountingId = (int) $line->row_id;

            if (! array_key_exists($mountingId, $grouped)) {
                continue;
            }

            $grouped[$mountingId][] = [
                'kode' => filled($line->diamond_code ?? null) ? (string) $line->diamond_code : '—',
                'shape' => filled($line->shape_name ?? null) ? (string) $line->shape_name : '—',
                'pcs' => $line->pcs !== null && $line->pcs !== ''
                    ? (str_contains((string) $line->pcs, '.') ? (float) $line->pcs : (int) $line->pcs)
                    : null,
                'crt' => $line->crt !== null && $line->crt !== ''
                    ? round((float) $line->crt, 4)
                    : null,
                'size' => filled($line->size ?? null) ? (string) $line->size : '—',
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array{
     *     table: string,
     *     local_key: string,
     *     owner_key: string,
     *     fields: array<string, string>
     * }  $parent
     * @return list<array<string, mixed>>
     */
    private function enrichWithParent(array $records, array $parent): array
    {
        if (! Schema::connection('third')->hasTable($parent['table'])) {
            return $records;
        }

        $localKey = $parent['local_key'];
        $ownerKey = $parent['owner_key'];
        $parentIds = collect($records)
            ->pluck($localKey)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($parentIds === []) {
            return $records;
        }

        $parents = DB::connection('third')
            ->table($parent['table'])
            ->whereIn($ownerKey, $parentIds)
            ->get()
            ->keyBy($ownerKey);

        return array_map(function (array $record) use ($parents, $localKey, $parent): array {
            $parentRow = $parents->get($record[$localKey] ?? null);
            $enriched = [];

            foreach ($parent['fields'] as $alias => $column) {
                $value = $parentRow?->{$column};

                if ($alias === 'tanggal' && $value !== null && $value !== '') {
                    $enriched[$alias] = Carbon::parse((string) $value)->format('d-M-Y');
                } else {
                    $enriched[$alias] = $value;
                }
            }

            return [...$enriched, ...$record];
        }, $records);
    }
}
