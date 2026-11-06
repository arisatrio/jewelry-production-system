<?php

namespace App\Support;

use App\Models\MsStone;
use App\Models\Production;
use App\Models\SpkDiamond;
use App\Models\SpkStone;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkDiamondDocumentService
{
    public function __construct(
        private readonly SpkStoneAllocationService $stoneAllocation,
    ) {}

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('spk_diamond');
    }

    public function diamondMountingIdColumnAvailable(): bool
    {
        return $this->tableAvailable()
            && Schema::connection('third')->hasColumn('spk_diamond', 'diamondmounting_id');
    }

    /**
     * @return array{
     *     docNo: string,
     *     tanggal: string,
     *     spkRowId: int
     * }|null
     */
    public function latestSummaryForProduction(Production $production): ?array
    {
        if (! $this->tableAvailable()) {
            return $this->summaryFromSpkHeader($production);
        }

        $document = SpkDiamond::query()
            ->where('spk_row_id', (int) $production->row_id)
            ->where('is_deleted', 0)
            ->orderByDesc('row_id')
            ->first(['doc_no', 'tanggal', 'spk_row_id']);

        if ($document === null) {
            return $this->summaryFromSpkHeader($production);
        }

        return [
            'docNo' => (string) $document->doc_no,
            'tanggal' => $document->tanggal->toDateString(),
            'spkRowId' => (int) $document->spk_row_id,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     docNo: string,
     *     tanggal: string,
     *     lineCount: int,
     *     spkProduksiNo: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null
     * }>
     */
    public function documentsForProduction(Production $production): array
    {
        $documents = [];

        if ($this->tableAvailable()) {
            $documents = SpkDiamond::query()
                ->where('spk_row_id', (int) $production->row_id)
                ->where('is_deleted', 0)
                ->orderByDesc('row_id')
                ->get([
                    'row_id',
                    'doc_no',
                    'tanggal',
                    'jumlah_baris',
                    'spk_no',
                    'created_by',
                    'created_date',
                ])
                ->map(fn (SpkDiamond $document): array => [
                    'id' => (int) $document->row_id,
                    'docNo' => (string) $document->doc_no,
                    'tanggal' => $document->tanggal->toDateString(),
                    'lineCount' => (int) $document->jumlah_baris,
                    'spkProduksiNo' => filled($document->spk_no) ? (string) $document->spk_no : null,
                    'createdBy' => filled($document->created_by) ? (string) $document->created_by : null,
                    'createdDate' => $document->created_date?->format('Y-m-d H:i:s'),
                ])
                ->values()
                ->all();
        }

        if ($documents !== []) {
            return $documents;
        }

        $headerSummary = $this->summaryFromSpkHeader($production);

        if ($headerSummary === null) {
            return [];
        }

        return [[
            'id' => 0,
            'docNo' => $headerSummary['docNo'],
            'tanggal' => $headerSummary['tanggal'],
            'lineCount' => 0,
            'spkProduksiNo' => filled($production->spk_no) ? (string) $production->spk_no : null,
            'createdBy' => null,
            'createdDate' => null,
        ]];
    }

    public function store(
        Production $production,
        string $docNo,
        Carbon $tanggal,
        string $actor,
    ): SpkDiamond {
        if (! $this->tableAvailable()) {
            throw new HttpException(503, 'Penyimpanan SPK Pasang Batu belum tersedia.');
        }

        if (! $this->stoneAllocation->tableAvailable()) {
            throw new HttpException(503, 'Alokasi batu belum tersedia.');
        }

        $stones = SpkStone::query()
            ->notDeleted()
            ->where('row_id', $production->row_id)
            ->with(['position', 'shape'])
            ->orderBy('line_id')
            ->get();

        if ($stones->isEmpty()) {
            throw ValidationException::withMessages([
                'spk_row_id' => 'SPK tidak memiliki baris batu.',
            ]);
        }

        $allocatedByLineId = $this->stoneAllocation->allocationsGroupedByLineId($stones);

        foreach ($stones as $stone) {
            $lineId = (int) $stone->line_id;
            $allocatedItems = $allocatedByLineId[$lineId] ?? [];
            $allocatedPcs = $this->stoneAllocation->allocatedPcs($stone);

            if (! $this->stoneLineFullyAllocated($stone, $allocatedPcs, $allocatedItems)) {
                throw ValidationException::withMessages([
                    'spk_row_id' => 'Alokasikan semua baris batu pada SPK terlebih dahulu.',
                ]);
            }
        }

        $items = $this->buildItems($stones, $allocatedByLineId);

        if ($items === []) {
            throw ValidationException::withMessages([
                'spk_row_id' => 'Tidak ada batu teralokasi untuk SPK Pasang Batu.',
            ]);
        }

        if ($this->activeDocumentExistsForProduction((int) $production->row_id)) {
            throw ValidationException::withMessages([
                'spk_row_id' => 'SPK Pasang Batu untuk SPK produksi ini sudah ada.',
            ]);
        }

        $allocationRowIds = collect($items)
            ->pluck('allocationRowId')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $now = now();

        return DB::connection('third')->transaction(function () use (
            $production,
            $docNo,
            $tanggal,
            $items,
            $allocationRowIds,
            $actor,
            $now,
        ): SpkDiamond {
            $attributes = [
                'doc_no' => $docNo,
                'tanggal' => $tanggal->toDateString(),
                'spk_row_id' => (int) $production->row_id,
                'spk_no' => filled($production->spk_no) ? (string) $production->spk_no : null,
                'jumlah_baris' => count($items),
                'allocation_row_ids' => $allocationRowIds,
                'items' => $items,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
            ];

            if (SpkDiamondStatus::columnAvailable()) {
                $attributes['status'] = SpkDiamondStatus::DRAFT;
            }

            $document = SpkDiamond::query()->create($attributes);

            $this->syncSpkHeaderDocNo((int) $production->row_id, $docNo, $actor, $now);

            return $document;
        });
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $allocatedByLineId
     * @return list<array<string, mixed>>
     */
    private function buildItems(Collection $stones, array $allocatedByLineId): array
    {
        $items = [];

        foreach ($stones as $stone) {
            $lineId = (int) $stone->line_id;
            $positionName = $stone->position?->nama ?? '-';

            foreach ($allocatedByLineId[$lineId] ?? [] as $allocation) {
                $items[] = [
                    'allocationRowId' => (int) ($allocation['id'] ?? 0),
                    'spkStoneLineId' => $lineId,
                    'positionName' => $positionName,
                    'source' => (string) ($allocation['source'] ?? ''),
                    'displayId' => $allocation['displayId'] ?? $allocation['code'] ?? null,
                    'code' => $allocation['code'] ?? null,
                    'certificate' => $allocation['certificate'] ?? null,
                    'label' => $allocation['label'] ?? null,
                    'diamondType' => $allocation['diamondType'] ?? null,
                    'shape' => $allocation['shape'] ?? null,
                    'diamondId' => filled($allocation['diamondId'] ?? null)
                        ? (int) $allocation['diamondId']
                        : null,
                    'msStoneId' => filled($allocation['msStoneId'] ?? null)
                        ? (int) $allocation['msStoneId']
                        : null,
                    'reservedPcs' => (string) ($allocation['reservedPcs'] ?? '1'),
                    'reservedCrt' => $allocation['reservedCrt'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $allocatedItems
     */
    private function stoneLineFullyAllocated(
        SpkStone $stone,
        int $allocatedPcs,
        array $allocatedItems,
    ): bool {
        $required = (int) ($stone->pcs ?? 0);

        if ($required <= 0) {
            return $allocatedItems !== [];
        }

        return $allocatedPcs >= $required;
    }

    private function activeDocumentExistsForProduction(int $spkRowId): bool
    {
        $existsInDocumentTable = SpkDiamond::query()
            ->where('spk_row_id', $spkRowId)
            ->where('is_deleted', 0)
            ->exists();

        if ($existsInDocumentTable) {
            return true;
        }

        $schema = Schema::connection('third');

        if (! $schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            return false;
        }

        return DB::connection('third')
            ->table('spk')
            ->where('row_id', $spkRowId)
            ->where('is_deleted', 0)
            ->whereNotNull('spk_diamond_doc_no')
            ->where('spk_diamond_doc_no', '!=', '')
            ->exists();
    }

    private function syncSpkHeaderDocNo(
        int $spkRowId,
        string $docNo,
        string $actor,
        DateTimeInterface $now,
    ): void {
        $schema = Schema::connection('third');

        if (! $schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            return;
        }

        $payload = ['spk_diamond_doc_no' => $docNo];

        if ($schema->hasColumn('spk', 'modified_date')) {
            $payload['modified_date'] = $now;
        }

        if ($schema->hasColumn('spk', 'modified_by')) {
            $payload['modified_by'] = $actor;
        }

        DB::connection('third')
            ->table('spk')
            ->where('row_id', $spkRowId)
            ->where('is_deleted', 0)
            ->update($payload);
    }

    /**
     * @return array{docNo: string, tanggal: string, spkRowId: int}|null
     */
    private function summaryFromSpkHeader(Production $production): ?array
    {
        $schema = Schema::connection('third');

        if (! $schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            return null;
        }

        $docNo = DB::connection('third')
            ->table('spk')
            ->where('row_id', (int) $production->row_id)
            ->where('is_deleted', 0)
            ->value('spk_diamond_doc_no');

        if (! is_string($docNo) || trim($docNo) === '') {
            return null;
        }

        return [
            'docNo' => trim($docNo),
            'tanggal' => now()->toDateString(),
            'spkRowId' => (int) $production->row_id,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     docNo: string,
     *     tanggal: string,
     *     spkRowId: int,
     *     spkNo: string|null,
     *     orderReference: string|null,
     *     spkType: string|null,
     *     orderTypeLabel: string|null,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     skuAssigned: bool,
     *     spkImageUrl: string|null,
     *     lineCount: int,
     *     allocatedStones: list<array{
     *         positionName: string,
     *         source: string,
     *         sourceLabel: string,
     *         certificate: string|null,
     *         shape: string,
     *         label: string,
     *         diamondId: int|null,
     *         msStoneId: int|null,
     *         crtPerPcs: string|null,
     *         reservedPcs: string,
     *         reservedCrt: string|null
     *     }>,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     status: string,
     *     statusLabel: string,
     *     diamondMountingId: int|null
     * }>
     */
    public function paginateForIndex(
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        if (! $this->tableAvailable()) {
            return SpkDiamond::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $sortColumn = match ($sort) {
            'doc_no' => 'spk_diamond.doc_no',
            'spk_no' => 'spk_diamond.spk_no',
            'status' => 'spk_diamond.status',
            'diamondmounting_id' => 'spk_diamond.diamondmounting_id',
            'created_by' => 'spk_diamond.created_by',
            'created_date' => 'spk_diamond.created_date',
            'item_name' => 'spk_sort.item_name',
            default => 'spk_diamond.tanggal',
        };

        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query = SpkDiamond::query()
            ->where('spk_diamond.is_deleted', 0);

        if ($sort === 'item_name') {
            $query->leftJoin('spk as spk_sort', function ($join): void {
                $join->on('spk_sort.row_id', '=', 'spk_diamond.spk_row_id')
                    ->where('spk_sort.is_deleted', 0);
            });
        }

        $paginator = $query
            ->select('spk_diamond.*')
            ->with([
                'production' => fn ($query) => $query
                    ->notDeleted()
                    ->select([
                        'row_id',
                        'spk_no',
                        'spk_type',
                        'request_order_no',
                        'customer_name',
                        'item_name',
                        'file_name',
                        'sku_id',
                        'description',
                    ])
                    ->with(['categoryPrefix', 'sku']),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($innerQuery) use ($like, $search): void {
                    $innerQuery->where('spk_diamond.doc_no', 'like', $like)
                        ->orWhere('spk_diamond.spk_no', 'like', $like)
                        ->orWhereHas('production', function ($productionQuery) use ($like): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($like): void {
                                    $productionInner->where('spk_no', 'like', $like)
                                        ->orWhere('item_name', 'like', $like);
                                });
                        });

                    if ($this->diamondMountingIdColumnAvailable() && ctype_digit($search)) {
                        $innerQuery->orWhere(
                            'spk_diamond.diamondmounting_id',
                            (int) $search,
                        );
                    }
                });
            })
            ->when($dateFrom !== null, function ($query) use ($dateFrom): void {
                $query->whereDate('spk_diamond.tanggal', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function ($query) use ($dateTo): void {
                $query->whereDate('spk_diamond.tanggal', '<=', $dateTo);
            })
            ->orderBy($sortColumn, $direction)
            ->orderByDesc('spk_diamond.row_id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (SpkDiamond $document): array {
                $spkNo = filled($document->spk_no)
                    ? (string) $document->spk_no
                    : ($document->production !== null && filled($document->production->spk_no)
                        ? (string) $document->production->spk_no
                        : null);

                $itemPresentation = SpkProductionListItemPresentation::forProduction(
                    $document->production,
                );

                return [
                    'id' => (int) $document->row_id,
                    'docNo' => (string) $document->doc_no,
                    'tanggal' => $document->tanggal->toDateString(),
                    'spkRowId' => (int) $document->spk_row_id,
                    'spkNo' => $spkNo,
                    ...$this->productionSpkColumnFields($document->production),
                    ...$itemPresentation,
                    'lineCount' => (int) $document->jumlah_baris,
                    'allocatedStones' => $this->normalizeStoredItemsForDisplay(
                        is_array($document->items) ? $document->items : [],
                    ),
                    'createdBy' => filled($document->created_by) ? (string) $document->created_by : null,
                    'createdDate' => $document->created_date?->format('Y-m-d H:i:s'),
                    ...SpkDiamondStatus::payloadForDocument($document),
                    'diamondMountingId' => $this->resolveDiamondMountingId($document),
                ];
            }),
        );

        return $paginator;
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string,
     *     tanggal: string,
     *     spkRowId: int,
     *     spkNo: string|null,
     *     itemName: string|null,
     *     lineCount: int,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     status: string,
     *     statusLabel: string,
     *     items: list<array{
     *         positionName: string,
     *         sourceLabel: string,
     *         certificate: string|null,
     *         shape: string,
     *         label: string,
     *         crtPerPcs: string|null,
     *         reservedPcs: string,
     *         reservedCrt: string|null
     *     }>,
     *     totals: array{pcs: int, carat: float}
     * }
     */
    public function detailPayload(SpkDiamond $document): array
    {
        $document->load([
            'production' => fn ($query) => $query
                ->notDeleted()
                ->select(['row_id', 'spk_no', 'item_name']),
        ]);

        $spkNo = filled($document->spk_no)
            ? (string) $document->spk_no
            : ($document->production !== null && filled($document->production->spk_no)
                ? (string) $document->production->spk_no
                : null);

        $items = $this->normalizeStoredItemsForDisplay(is_array($document->items) ? $document->items : []);

        $totalPcs = 0;
        $totalCarat = 0.0;

        foreach ($items as $item) {
            $pcs = (int) round((float) str_replace(',', '.', (string) ($item['reservedPcs'] ?? '0')));

            if ($pcs > 0) {
                $totalPcs += $pcs;
            }

            $crt = $item['reservedCrt'] ?? null;

            if (is_string($crt) && trim($crt) !== '') {
                $parsedCrt = (float) str_replace(',', '.', $crt);

                if (is_finite($parsedCrt)) {
                    $totalCarat += $parsedCrt;
                }
            }
        }

        return [
            'id' => (int) $document->row_id,
            'docNo' => (string) $document->doc_no,
            'tanggal' => $document->tanggal->toDateString(),
            'spkRowId' => (int) $document->spk_row_id,
            'spkNo' => $spkNo,
            'itemName' => $document->production !== null && filled($document->production->item_name)
                ? (string) $document->production->item_name
                : null,
            'lineCount' => (int) $document->jumlah_baris,
            'createdBy' => filled($document->created_by) ? (string) $document->created_by : null,
            'createdDate' => $document->created_date?->format('Y-m-d H:i:s'),
            ...SpkDiamondStatus::payloadForDocument($document),
            'items' => $items,
            'totals' => [
                'pcs' => $totalPcs,
                'carat' => round($totalCarat, 3),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $storedItems
     * @return list<array{
     *     positionName: string,
     *     sourceLabel: string,
     *     certificate: string|null,
     *     shape: string,
     *     label: string,
     *     crtPerPcs: string|null,
     *     reservedPcs: string,
     *     reservedCrt: string|null
     * }>
     */
    private function normalizeStoredItemsForDisplay(array $storedItems): array
    {
        $certificatesByAllocationId = $this->certificatesByAllocationRowIds(
            collect($storedItems)
                ->pluck('allocationRowId')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all(),
        );

        $microStoneNamesById = $this->microStoneNamesByIds(
            collect($storedItems)
                ->filter(static fn (mixed $item): bool => is_array($item))
                ->map(static function (array $item): int {
                    if (($item['source'] ?? '') !== SpkStoneAllocationService::SOURCE_MICRO) {
                        return 0;
                    }

                    return filled($item['msStoneId'] ?? null) ? (int) $item['msStoneId'] : 0;
                })
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all(),
        );

        $normalized = [];

        foreach ($storedItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $allocationRowId = (int) ($item['allocationRowId'] ?? 0);
            $source = (string) ($item['source'] ?? '');
            $sourceLabel = $source === SpkStoneAllocationService::SOURCE_DOSSIER
                ? 'Dossier'
                : ($source === SpkStoneAllocationService::SOURCE_MICRO ? 'Mikro' : $source);

            $certificate = $item['certificate'] ?? null;

            if (! is_string($certificate) || trim($certificate) === '') {
                $certificate = $certificatesByAllocationId[$allocationRowId] ?? null;
            }

            $label = $this->displayLabelForStoredItem($item, $microStoneNamesById);

            $shape = $item['shape'] ?? '-';
            $shape = is_string($shape) && trim($shape) !== '' ? trim($shape) : '-';

            $reservedPcs = (string) ($item['reservedPcs'] ?? '1');
            $reservedCrt = filled($item['reservedCrt'] ?? null)
                ? (string) $item['reservedCrt']
                : null;

            $crtPerPcs = null;

            if ($reservedCrt !== null) {
                $pcsValue = (float) str_replace(',', '.', $reservedPcs);
                $crtValue = (float) str_replace(',', '.', $reservedCrt);

                if ($pcsValue > 0 && is_finite($crtValue)) {
                    $crtPerPcs = number_format($crtValue / $pcsValue, 3, '.', '');
                }
            }

            $normalized[] = [
                'positionName' => is_string($item['positionName'] ?? null) && trim((string) $item['positionName']) !== ''
                    ? trim((string) $item['positionName'])
                    : '-',
                'source' => $source,
                'sourceLabel' => $sourceLabel !== '' ? $sourceLabel : '-',
                'certificate' => is_string($certificate) && trim($certificate) !== ''
                    ? trim($certificate)
                    : null,
                'shape' => $shape,
                'label' => $label,
                'diamondId' => filled($item['diamondId'] ?? null)
                    ? (int) $item['diamondId']
                    : null,
                'msStoneId' => filled($item['msStoneId'] ?? null)
                    ? (int) $item['msStoneId']
                    : null,
                'crtPerPcs' => $crtPerPcs,
                'reservedPcs' => $reservedPcs,
                'reservedCrt' => $reservedCrt,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<int>  $allocationRowIds
     * @return array<int, string>
     */
    private function certificatesByAllocationRowIds(array $allocationRowIds): array
    {
        if ($allocationRowIds === [] || ! $this->stoneAllocation->tableAvailable()) {
            return [];
        }

        $rows = DB::connection('third')
            ->table(SpkStoneAllocationService::TABLE.' as allocation')
            ->leftJoin('trdiamond as diamond', 'diamond.row_id', '=', 'allocation.diamond_id')
            ->whereIn('allocation.row_id', $allocationRowIds)
            ->where('allocation.is_deleted', 0)
            ->get(['allocation.row_id', 'diamond.certificate']);

        $certificates = [];

        foreach ($rows as $row) {
            $certificate = is_string($row->certificate ?? null) ? trim($row->certificate) : '';

            if ($certificate === '') {
                continue;
            }

            $certificates[(int) $row->row_id] = $certificate;
        }

        return $certificates;
    }

    /**
     * @param  list<int>  $msStoneIds
     * @return array<int, string>
     */
    private function microStoneNamesByIds(array $msStoneIds): array
    {
        if ($msStoneIds === [] || ! Schema::connection('third')->hasTable('msstone')) {
            return [];
        }

        return MsStone::query()
            ->whereIn('row_id', $msStoneIds)
            ->where('is_deleted', 0)
            ->get(['row_id', 'name'])
            ->reduce(function (array $names, MsStone $stone): array {
                $name = trim((string) ($stone->name ?? ''));

                if ($name !== '') {
                    $names[(int) $stone->row_id] = $name;
                }

                return $names;
            }, []);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $microStoneNamesById
     */
    private function displayLabelForStoredItem(array $item, array $microStoneNamesById): string
    {
        $source = (string) ($item['source'] ?? '');
        $msStoneId = filled($item['msStoneId'] ?? null) ? (int) $item['msStoneId'] : 0;

        if ($source === SpkStoneAllocationService::SOURCE_MICRO && $msStoneId > 0) {
            $resolvedName = $microStoneNamesById[$msStoneId] ?? null;

            if (filled($resolvedName)) {
                return trim((string) $resolvedName);
            }
        }

        $storedLabel = is_string($item['label'] ?? null) ? trim($item['label']) : '';

        if ($storedLabel !== '' && $storedLabel !== '-') {
            if (
                $source === SpkStoneAllocationService::SOURCE_MICRO
                && $msStoneId > 0
                && $this->storedMicroLabelIsStoneId($storedLabel, $msStoneId)
            ) {
                return 'Batu mikro #'.$msStoneId;
            }

            return $storedLabel;
        }

        $code = is_string($item['code'] ?? null) ? trim($item['code']) : '';
        $displayId = is_string($item['displayId'] ?? null) ? trim($item['displayId']) : '';

        if ($source === SpkStoneAllocationService::SOURCE_DOSSIER) {
            if ($code !== '') {
                return $code;
            }

            return $displayId !== '' ? $displayId : '-';
        }

        if ($msStoneId > 0) {
            return 'Batu mikro #'.$msStoneId;
        }

        if ($code !== '') {
            return $code;
        }

        return $displayId !== '' ? $displayId : '-';
    }

    private function storedMicroLabelIsStoneId(string $label, int $msStoneId): bool
    {
        if ($msStoneId <= 0) {
            return false;
        }

        if ($label === (string) $msStoneId) {
            return true;
        }

        return strcasecmp($label, 'Batu mikro #'.$msStoneId) === 0;
    }

    private function resolveDiamondMountingId(SpkDiamond $document): ?int
    {
        if (! $this->diamondMountingIdColumnAvailable()) {
            return null;
        }

        $mountingId = (int) ($document->diamondmounting_id ?? 0);

        return $mountingId > 0 ? $mountingId : null;
    }

    /**
     * @return array{
     *     orderReference: string|null,
     *     spkType: string|null,
     *     orderTypeLabel: string|null
     * }
     */
    private function productionSpkColumnFields(?Production $production): array
    {
        if ($production === null) {
            return [
                'orderReference' => null,
                'spkType' => null,
                'orderTypeLabel' => null,
            ];
        }

        return [
            'orderReference' => SpkOrderReference::label($production),
            'spkType' => filled($production->spk_type)
                ? (string) $production->spk_type
                : null,
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
        ];
    }
}
