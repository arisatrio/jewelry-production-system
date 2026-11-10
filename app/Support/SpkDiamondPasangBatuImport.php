<?php

namespace App\Support;

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\SpkDiamond;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkDiamondPasangBatuImport
{
    public function __construct(
        private readonly SpkDiamondDocumentService $documents,
        private readonly ProductionOrderTypeLabel $orderTypeLabel,
    ) {}

    /**
     * @param  list<int>  $excludeIds
     * @return list<array{
     *     rowId: int,
     *     docNo: string,
     *     spkNo: string|null,
     *     spkRowId: int,
     *     itemName: string,
     *     status: string,
     *     statusLabel: string,
     *     lineCount: int
     * }>
     */
    public function search(string $search, int $limit, array $excludeIds = [], ?int $allowLinkedId = null): array
    {
        if (! $this->documents->tableAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $excludeIds = collect($excludeIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = SpkDiamond::query()
            ->where('is_deleted', 0)
            ->with([
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->select(['row_id', 'spk_no', 'item_name']),
            ])
            ->when($excludeIds !== [], fn (Builder $builder) => $builder->whereNotIn('row_id', $excludeIds))
            ->tap(fn (Builder $builder) => $this->applyEligibleForPasangBatuScope($builder, $allowLinkedId))
            ->orderByDesc('row_id')
            ->limit($limit);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $innerQuery) use ($like): void {
                $innerQuery->where('doc_no', 'like', $like)
                    ->orWhere('spk_no', 'like', $like)
                    ->orWhereHas('production', function (Builder $productionQuery) use ($like): void {
                        $productionQuery->notDeleted()
                            ->where(function (Builder $productionInner) use ($like): void {
                                $productionInner->where('spk_no', 'like', $like)
                                    ->orWhere('item_name', 'like', $like)
                                    ->orWhere('customer_name', 'like', $like);
                            });
                    });
            });
        }

        return $query->get()
            ->map(function (SpkDiamond $document): array {
                $statusPayload = SpkDiamondStatus::payloadForDocument($document);
                $production = $document->production;
                $itemName = $production !== null && filled($production->item_name)
                    ? (string) $production->item_name
                    : '—';

                return [
                    'rowId' => (int) $document->row_id,
                    'docNo' => (string) $document->doc_no,
                    'spkNo' => filled($document->spk_no)
                        ? (string) $document->spk_no
                        : ($production !== null ? $production->spk_no : null),
                    'spkRowId' => (int) $document->spk_row_id,
                    'itemName' => $itemName,
                    'status' => $statusPayload['status'],
                    'statusLabel' => $statusPayload['label'],
                    'lineCount' => (int) ($document->jumlah_baris ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     spkDiamondId: int,
     *     spkDiamondDocNo: string,
     *     weightDiamond: string|null,
     *     spk: array{
     *         rowId: int,
     *         spkNo: string|null,
     *         lastWeight: string|null,
     *         spkType: string|null,
     *         orderTypeLabel: string|null,
     *         skuCode: string|null,
     *         typeCode: string|null,
     *         productItemName: string|null,
     *         itemDescription: string|null,
     *         satuan: string
     *     },
     *     settingStones: list<array{stoneId: int, pcs: string, crt: string, notes: string}>,
     *     diamonds: list<array{diamondId: int}>,
     *     mountedStones: list<array{diamondCode: string, shapeId: int|null, pcs: string, crt: string, size: string}>
     * }
     */
    public function importFormPayload(SpkDiamond $document, ?int $allowLinkedMountingId = null): array
    {
        $this->assertEligibleForImport($document, $allowLinkedMountingId);

        $production = Production::query()
            ->notDeleted()
            ->with([
                'sku' => fn ($skuQuery) => $skuQuery->select(['id', 'sku_code', 'item_original']),
                'categoryPrefix' => fn ($prefixQuery) => $prefixQuery->select(['id', 'prefix']),
            ])
            ->where('row_id', (int) $document->spk_row_id)
            ->first();

        if ($production === null) {
            throw new HttpException(422, 'SPK produksi pada dokumen SPK Diamond tidak ditemukan.');
        }

        $detail = $this->documents->detailPayload($document);
        $stoneLines = $this->stoneLinesFromDetailItems($detail['items'] ?? []);
        $totalCarat = $detail['totals']['carat'] ?? null;

        return [
            'spkDiamondId' => (int) $document->row_id,
            'spkDiamondDocNo' => (string) $document->doc_no,
            'weightDiamond' => is_numeric($totalCarat)
                ? number_format((float) $totalCarat, 3, '.', '')
                : null,
            'spk' => $this->productionSelectorRow($production),
            ...$stoneLines,
        ];
    }

    /**
     * @return array{id: int, docNo: string}|null
     */
    public function linkedSummaryForMounting(DiamondMounting $mounting): ?array
    {
        if (! $this->documents->diamondMountingIdColumnAvailable()) {
            return null;
        }

        $document = SpkDiamond::query()
            ->where('is_deleted', 0)
            ->where('diamondmounting_id', (int) $mounting->row_id)
            ->orderByDesc('row_id')
            ->first(['row_id', 'doc_no']);

        if ($document === null) {
            return null;
        }

        return [
            'id' => (int) $document->row_id,
            'docNo' => (string) $document->doc_no,
        ];
    }

    public function assertEligibleForImport(SpkDiamond $document, ?int $allowLinkedMountingId = null): void
    {
        if ((int) $document->is_deleted === 1) {
            throw new HttpException(422, 'Dokumen SPK Diamond tidak valid.');
        }

        if (SpkDiamondStatus::columnAvailable()) {
            $status = SpkDiamondStatus::normalize($document->status);

            if ($status !== SpkDiamondStatus::RECEIVED_BY_WORKSHOP && $status !== SpkDiamondStatus::INSTALLED) {
                throw new HttpException(
                    422,
                    'SPK Diamond harus berstatus Diterima Workshop sebelum diimpor ke Pasang Batu.',
                );
            }
        }

        if ($this->documents->diamondMountingIdColumnAvailable()) {
            $linkedId = (int) ($document->diamondmounting_id ?? 0);

            if ($linkedId > 0 && $allowLinkedMountingId !== null && $linkedId !== $allowLinkedMountingId) {
                throw new HttpException(422, 'SPK Diamond sudah terhubung ke dokumen Pasang Batu lain.');
            }

            if ($linkedId > 0 && $allowLinkedMountingId === null) {
                throw new HttpException(422, 'SPK Diamond sudah terhubung ke dokumen Pasang Batu lain.');
            }
        }
    }

    public function syncMountingLink(
        ?int $previousSpkDiamondId,
        ?int $newSpkDiamondId,
        int $mountingId,
        string $actor,
    ): void {
        if (! $this->documents->diamondMountingIdColumnAvailable()) {
            return;
        }

        $previousSpkDiamondId = filled($previousSpkDiamondId) && (int) $previousSpkDiamondId > 0
            ? (int) $previousSpkDiamondId
            : null;
        $newSpkDiamondId = filled($newSpkDiamondId) && (int) $newSpkDiamondId > 0
            ? (int) $newSpkDiamondId
            : null;

        if ($previousSpkDiamondId !== null && $previousSpkDiamondId !== $newSpkDiamondId) {
            $this->clearMountingLink($previousSpkDiamondId, $actor);
        }

        if ($newSpkDiamondId !== null) {
            $document = SpkDiamond::query()
                ->where('row_id', $newSpkDiamondId)
                ->where('is_deleted', 0)
                ->first();

            if ($document !== null) {
                $this->assertEligibleForImport($document, $mountingId);

                $updates = [
                    'diamondmounting_id' => $mountingId,
                ];

                if (Schema::connection('third')->hasColumn('spk_diamond', 'modified_date')) {
                    $updates['modified_date'] = now();
                }

                if (Schema::connection('third')->hasColumn('spk_diamond', 'modified_by')) {
                    $updates['modified_by'] = $actor;
                }

                $document->update($updates);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     settingStones: list<array{stoneId: int, pcs: string, crt: string, notes: string}>,
     *     diamonds: list<array{diamondId: int}>,
     *     mountedStones: list<array{diamondCode: string, shapeId: int|null, pcs: string, crt: string, size: string}>
     * }
     */
    private function stoneLinesFromDetailItems(array $items): array
    {
        $shapeIdsByName = $this->shapeIdsByDisplayName();
        $dossierCodesById = $this->dossierCodesByIds(
            collect($items)
                ->pluck('diamondId')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all(),
        );

        $microAggregate = [];
        $diamonds = [];
        $mountedStones = [];
        $diamondIdsAdded = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $source = (string) ($item['source'] ?? '');
            $pcs = $this->formatQuantityString($item['reservedPcs'] ?? '0');
            $crt = $this->formatQuantityString($item['reservedCrt'] ?? null, 3);
            $positionName = is_string($item['positionName'] ?? null) && trim((string) $item['positionName']) !== ''
                ? trim((string) $item['positionName'])
                : '';
            $shapeName = is_string($item['shape'] ?? null) ? trim((string) $item['shape']) : '';
            $shapeId = $this->resolveShapeId($shapeName, $shapeIdsByName);

            if ($source === SpkStoneAllocationService::SOURCE_MICRO) {
                $stoneId = filled($item['msStoneId'] ?? null) ? (int) $item['msStoneId'] : 0;

                if ($stoneId <= 0) {
                    continue;
                }

                if (! isset($microAggregate[$stoneId])) {
                    $microAggregate[$stoneId] = [
                        'stoneId' => $stoneId,
                        'pcs' => 0.0,
                        'crt' => 0.0,
                        'notes' => [],
                    ];
                }

                $pcsValue = (float) str_replace(',', '.', (string) ($item['reservedPcs'] ?? '0'));
                $crtValue = filled($item['reservedCrt'] ?? null)
                    ? (float) str_replace(',', '.', (string) $item['reservedCrt'])
                    : 0.0;

                if (is_finite($pcsValue) && $pcsValue > 0) {
                    $microAggregate[$stoneId]['pcs'] += $pcsValue;
                }

                if (is_finite($crtValue) && $crtValue > 0) {
                    $microAggregate[$stoneId]['crt'] += $crtValue;
                }

                if ($positionName !== '' && $positionName !== '-') {
                    $microAggregate[$stoneId]['notes'][] = $positionName;
                }

                continue;
            }

            if ($source === SpkStoneAllocationService::SOURCE_DOSSIER) {
                $diamondId = filled($item['diamondId'] ?? null) ? (int) $item['diamondId'] : 0;

                if ($diamondId <= 0) {
                    continue;
                }

                if (! in_array($diamondId, $diamondIdsAdded, true)) {
                    $diamonds[] = ['diamondId' => $diamondId];
                    $diamondIdsAdded[] = $diamondId;
                }

                $certificate = is_string($item['certificate'] ?? null) ? trim((string) $item['certificate']) : '';
                $diamondCode = $certificate !== ''
                    ? $certificate
                    : ($dossierCodesById[$diamondId] ?? '');

                $mountedStones[] = [
                    'diamondCode' => $diamondCode,
                    'shapeId' => $shapeId,
                    'pcs' => $pcs,
                    'crt' => $crt,
                    'size' => '',
                ];
            }
        }

        $settingStones = collect($microAggregate)
            ->sortKeys()
            ->map(function (array $line): array {
                $notes = collect($line['notes'])
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'stoneId' => (int) $line['stoneId'],
                    'pcs' => $this->formatQuantityString($line['pcs']),
                    'crt' => $line['crt'] > 0 ? $this->formatQuantityString($line['crt'], 3) : '',
                    'notes' => $notes,
                ];
            })
            ->values()
            ->all();

        return [
            'settingStones' => $settingStones,
            'diamonds' => $diamonds,
            'mountedStones' => $mountedStones,
        ];
    }

    /**
     * @return array{
     *     rowId: int,
     *     spkNo: string|null,
     *     lastWeight: string|null,
     *     spkType: string|null,
     *     orderTypeLabel: string|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     satuan: string
     * }
     */
    private function productionSelectorRow(Production $production): array
    {
        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production->item_name ?? ''));
        }

        $itemDescription = trim((string) ($production->description ?? ''));

        return [
            'rowId' => (int) $production->row_id,
            'spkNo' => filled($production->spk_no) ? (string) $production->spk_no : null,
            'lastWeight' => $this->formatDecimal($production->last_weight),
            'spkType' => filled($production->spk_type) ? (string) $production->spk_type : null,
            'orderTypeLabel' => $this->orderTypeLabel->forProduction($production),
            'skuCode' => filled($production->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
            'satuan' => SpkQtyUnit::label($production->qty, $production->satuan),
        ];
    }

    private function applyEligibleForPasangBatuScope(Builder $query, ?int $allowLinkedId): void
    {
        if (SpkDiamondStatus::columnAvailable()) {
            $query->whereIn('status', [
                SpkDiamondStatus::RECEIVED_BY_WORKSHOP,
                SpkDiamondStatus::INSTALLED,
            ]);
        }

        if ($this->documents->diamondMountingIdColumnAvailable()) {
            $query->where(function (Builder $innerQuery) use ($allowLinkedId): void {
                $innerQuery->whereNull('diamondmounting_id')
                    ->orWhere('diamondmounting_id', 0);

                if ($allowLinkedId !== null && $allowLinkedId > 0) {
                    $innerQuery->orWhere('diamondmounting_id', $allowLinkedId);
                }
            });
        }
    }

    private function clearMountingLink(int $spkDiamondId, string $actor): void
    {
        $updates = [
            'diamondmounting_id' => null,
        ];

        if (Schema::connection('third')->hasColumn('spk_diamond', 'modified_date')) {
            $updates['modified_date'] = now();
        }

        if (Schema::connection('third')->hasColumn('spk_diamond', 'modified_by')) {
            $updates['modified_by'] = $actor;
        }

        SpkDiamond::query()
            ->where('row_id', $spkDiamondId)
            ->where('is_deleted', 0)
            ->update($updates);
    }

    /**
     * @param  list<int>  $diamondIds
     * @return array<int, string>
     */
    private function dossierCodesByIds(array $diamondIds): array
    {
        if ($diamondIds === [] || ! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        $schema = Schema::connection('third');
        $columns = ['row_id'];

        foreach (['doc_no', 'diamond_code', 'certificate'] as $column) {
            if ($schema->hasColumn('trdiamond', $column)) {
                $columns[] = $column;
            }
        }

        return DB::connection('third')
            ->table('trdiamond')
            ->whereIn('row_id', $diamondIds)
            ->get($columns)
            ->mapWithKeys(function (object $row): array {
                $id = (int) $row->row_id;
                $code = '';

                if (property_exists($row, 'certificate') && filled($row->certificate ?? null)) {
                    $code = trim((string) $row->certificate);
                }

                if ($code === '' && property_exists($row, 'diamond_code')) {
                    $code = trim((string) ($row->diamond_code ?? ''));
                }

                if ($code === '' && property_exists($row, 'doc_no')) {
                    $code = trim((string) ($row->doc_no ?? ''));
                }

                return [$id => $code];
            })
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function shapeIdsByDisplayName(): array
    {
        if (! Schema::connection('third')->hasTable('msshape')) {
            return [];
        }

        $query = DB::connection('third')->table('msshape');

        if (Schema::connection('third')->hasColumn('msshape', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $map = [];

        foreach ($query->get(['row_id', 'name']) as $row) {
            $name = trim((string) ($row->name ?? ''));

            if ($name === '') {
                continue;
            }

            $map[$this->normalizeShapeKey($name)] = (int) $row->row_id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $shapeIdsByName
     */
    private function resolveShapeId(string $shapeName, array $shapeIdsByName): ?int
    {
        if ($shapeName === '' || $shapeName === '-') {
            return null;
        }

        $normalized = $this->normalizeShapeKey($shapeName);

        if (isset($shapeIdsByName[$normalized])) {
            return $shapeIdsByName[$normalized];
        }

        foreach ($shapeIdsByName as $key => $id) {
            if (str_contains($normalized, $key) || str_contains($key, $normalized)) {
                return $id;
            }
        }

        return null;
    }

    private function normalizeShapeKey(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }

    private function formatDecimal(mixed $value, int $precision = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (! is_finite($number)) {
            return null;
        }

        return number_format($number, $precision, '.', '');
    }

    private function formatQuantityString(mixed $value, int $precision = 0): string
    {
        if ($value === null || $value === '') {
            return $precision > 0 ? '' : '0';
        }

        if (! is_numeric($value)) {
            return '';
        }

        $number = (float) $value;

        if (! is_finite($number)) {
            return '';
        }

        if ($precision === 0) {
            if (abs($number - (int) $number) < 0.00001) {
                return (string) (int) $number;
            }
        }

        return rtrim(rtrim(number_format($number, $precision, '.', ''), '0'), '.');
    }
}
