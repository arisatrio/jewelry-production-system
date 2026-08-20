<?php

namespace App\Support;

use App\Models\MsPosition;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpkService
{
    public const TYPES = ['Pesanan', 'Exchange', 'Refund', 'Reparasi', 'Stock'];

    public const REFERENCE_TYPES = ['Exchange', 'Refund', 'Reparasi'];

    public const DEFAULT_SUPPLIER_ID = 1;

    public const UNITS = ['Pcs', 'Pasang'];

    public const DEFAULT_UNIT = 'Pcs';

    public function __construct(
        private RequestOrderRepository $requestOrders,
        private SpkStatusOrder $statusOrder,
        private GoogleCloudStorageService $gcs,
    ) {}

    /**
     * Create a new SPK header based on production type rules (draft header only).
     *
     * @param  array{spk_type: string, request_order_no?: string|null, ref_spk_id?: int|null}  $data
     */
    public function create(array $data, string $actor): Production
    {
        $type = $data['spk_type'];

        return match ($type) {
            'Pesanan' => $this->createFromRequestOrder((string) $data['request_order_no'], $actor),
            'Exchange', 'Refund', 'Reparasi' => $this->createFromReferenceSpk(
                (int) $data['ref_spk_id'],
                $type,
                $actor,
            ),
            'Stock' => $this->createStock($actor),
            default => throw new InvalidArgumentException("Tipe produksi tidak dikenali: {$type}"),
        };
    }

    /**
     * Create SPK with full form details. Nomor SPK digenerate saat simpan.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithDetails(array $data, string $actor, ?UploadedFile $file = null): Production
    {
        return DB::connection('third')->transaction(function () use ($data, $actor, $file): Production {
            $type = (string) $data['spk_type'];
            $typeAttributes = match ($type) {
                'Pesanan' => $this->attributesFromRequestOrder((string) $data['request_order_no']),
                'Exchange', 'Refund', 'Reparasi' => $this->attributesFromReference(
                    (int) $data['ref_spk_id'],
                    $type,
                ),
                'Stock' => ['spk_type' => 'Stock'],
                default => throw new InvalidArgumentException("Tipe produksi tidak dikenali: {$type}"),
            };

            $now = now();
            $orderDate = Carbon::parse((string) $data['order_date'])->startOfDay();
            ['estimatedDelivery' => $estimatedDelivery, 'workEstimated' => $workEstimated] = $this->resolveEstimatedSchedule($orderDate, $data);
            $category = $this->resolveCategory($data);
            $sku = $this->resolveSku($data);
            $ukuranLabel = $this->resolveUkuranLabel($data);

            $attributes = [
                ...$typeAttributes,
                'spk_no' => $this->generateNumber($now),
                'order_date' => $orderDate->toDateString(),
                'priority' => $data['priority'],
                'description' => $data['description'],
                'estimated_delivery_time' => $estimatedDelivery->toDateString(),
                'work_estimated' => $workEstimated,
                'category_prefix_id' => $category?->id,
                'sku_id' => $sku?->id,
                'frame_id' => null,
                'supplier_id' => self::DEFAULT_SUPPLIER_ID,
                'qty' => (int) $data['qty'],
                'satuan' => $data['satuan'] ?? self::DEFAULT_UNIT,
                'status_order' => $this->statusOrder->code($sku?->id),
                'diameter_length_ringsize' => $ukuranLabel,
                'gold_weight' => $data['gold_weight'],
                'gold_color' => $data['gold_color'],
                'gold_content' => ($data['gold_content'] ?? null) ?: null,
                'jwcad_3d' => $this->resolveJwcadFile($data, $sku),
                'notes' => ($data['notes'] ?? null) ?: null,
                'status' => '',
                'is_deleted' => 0,
                'is_coran' => 0,
                'is_finishinghandmade' => 0,
                'is_polishframe' => 0,
                'is_diamondmounting' => 0,
                'is_polishfinishedgood' => 0,
                'is_grafir' => 0,
                'is_inprocess' => 0,
                'is_from_new_system' => 1,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ];

            if (blank($attributes['request_order_no'] ?? null)) {
                $attributes['item_id'] = null;
                $attributes['item_name'] = $category?->category;
            } else {
                $order = $this->requestOrders->findByDocNo((string) $attributes['request_order_no']);

                if ($order !== null) {
                    $attributes['customer_name'] = $order['customer'];
                    $attributes['item_name'] = $order['item'];
                    $attributes['item_id'] = $order['itemId'];
                }
            }

            if ($file !== null) {
                $attributes['file_name'] = $this->storeProductionImage($file);
            } elseif ($sku !== null) {
                $skuImageFileName = $this->resolveSkuImageFileName($sku);

                if ($skuImageFileName !== null) {
                    $attributes['file_name'] = $skuImageFileName;
                }
            }

            $production = Production::query()->create($attributes);

            $this->syncStones($production, $data['stones'] ?? [], $actor);

            return $production->refresh();
        });
    }

    public function createFromRequestOrder(string $requestOrderNo, string $actor): Production
    {
        return $this->insertHeader($this->attributesFromRequestOrder($requestOrderNo), $actor);
    }

    public function createFromReferenceSpk(int $refSpkId, string $spkType, string $actor): Production
    {
        return $this->insertHeader($this->attributesFromReference($refSpkId, $spkType), $actor);
    }

    public function createStock(string $actor): Production
    {
        return $this->insertHeader([
            'spk_type' => 'Stock',
        ], $actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromRequestOrder(string $requestOrderNo): array
    {
        $order = $this->requestOrders->findByDocNo($requestOrderNo);

        if ($order === null) {
            throw new InvalidArgumentException('Request order tidak ditemukan.');
        }

        return [
            'spk_type' => 'Pesanan',
            'request_order_no' => $order['docNo'],
            'customer_name' => $order['customer'],
            'item_name' => $order['item'],
            'item_id' => $order['itemId'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromReference(int $refSpkId, string $spkType): array
    {
        if (! in_array($spkType, self::REFERENCE_TYPES, true)) {
            throw new InvalidArgumentException('Tipe referensi SPK tidak valid.');
        }

        $reference = Production::query()
            ->notDeleted()
            ->where('row_id', $refSpkId)
            ->where('status', 'SPKDONE')
            ->first();

        if ($reference === null) {
            throw new InvalidArgumentException('SPK referensi harus berstatus SPKDONE.');
        }

        return [
            'spk_type' => $spkType,
            'ref_spk_id' => $reference->row_id,
            'request_order_no' => $reference->request_order_no,
            'customer_name' => $reference->customer_name,
            'item_name' => $reference->item_name,
            'description' => $reference->description,
            'work_estimated' => $reference->work_estimated,
            'item_id' => $reference->item_id,
            'qty' => $reference->qty,
            'diameter_length_ringsize' => $reference->diameter_length_ringsize,
            'gold_weight' => $reference->gold_weight,
            'gold_color' => $reference->gold_color,
            'gold_content' => $reference->gold_content,
            'priority' => $reference->priority,
            'item_type_id' => $reference->item_type_id,
            'sku_id' => $reference->sku_id,
            'category_prefix_id' => $reference->category_prefix_id,
        ];
    }

    /**
     * Generate next SPK number in format YYYY/PRD/00001.
     */
    public function generateNumber(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->format('Y');
        $prefix = "{$year}/PRD/";

        $latest = Production::query()
            ->where('spk_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('spk_no')
            ->value('spk_no');

        $next = 1;

        if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%05d', $prefix, $next);
    }

    /**
     * Search approved SPKs usable as Exchange/Refund/Reparasi references.
     *
     * @return list<array{rowId: int, spkNo: string, customer: string, item: string, lastWeight: string|null, frameNo: string|null, requestOrderNo: string|null}>
     */
    public function searchReferenceSpks(string $search = '', int $limit = 25): array
    {
        $limit = max(1, min($limit, 50));

        $query = Production::query()
            ->from('spk as s')
            ->leftJoin('trframe as f', 'f.row_id', '=', 's.frame_id')
            ->where('s.is_deleted', 0)
            ->where('s.status', 'SPKDONE')
            ->whereNotNull('s.spk_no')
            ->orderByDesc('s.row_id')
            ->limit($limit)
            ->select([
                's.row_id',
                's.spk_no',
                's.customer_name',
                's.item_name',
                's.last_weight',
                's.request_order_no',
                'f.doc_no as frame_no',
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('s.spk_no', 'like', $like)
                    ->orWhere('s.customer_name', 'like', $like)
                    ->orWhere('s.item_name', 'like', $like)
                    ->orWhere('s.request_order_no', 'like', $like)
                    ->orWhere('f.doc_no', 'like', $like);
            });
        }

        return $query->get()->map(fn ($row): array => [
            'rowId' => (int) $row->row_id,
            'spkNo' => (string) $row->spk_no,
            'customer' => filled($row->customer_name) ? (string) $row->customer_name : '—',
            'item' => filled($row->item_name) ? (string) $row->item_name : '—',
            'lastWeight' => $row->last_weight !== null ? (string) $row->last_weight : null,
            'frameNo' => filled($row->frame_no) ? (string) $row->frame_no : null,
            'requestOrderNo' => filled($row->request_order_no) ? (string) $row->request_order_no : null,
        ])->all();
    }

    /**
     * Save editable SPK header fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveHeader(Production $production, array $data, string $actor, ?UploadedFile $file = null): Production
    {
        return DB::connection('third')->transaction(function () use ($production, $data, $actor, $file): Production {
            $orderDate = Carbon::parse((string) $data['order_date'])->startOfDay();
            ['estimatedDelivery' => $estimatedDelivery, 'workEstimated' => $workEstimated] = $this->resolveEstimatedSchedule($orderDate, $data);
            $category = $this->resolveCategory($data);
            $sku = $this->resolveSku($data);
            $ukuranLabel = $this->resolveUkuranLabel($data);

            $attributes = [
                'order_date' => $orderDate->toDateString(),
                'priority' => $data['priority'],
                'description' => $data['description'],
                'estimated_delivery_time' => $estimatedDelivery->toDateString(),
                'work_estimated' => $workEstimated,
                'category_prefix_id' => $category?->id,
                'sku_id' => $sku?->id,
                'frame_id' => null,
                'supplier_id' => self::DEFAULT_SUPPLIER_ID,
                'qty' => (int) $data['qty'],
                'satuan' => $data['satuan'] ?? self::DEFAULT_UNIT,
                'status_order' => $this->statusOrder->code($sku?->id, $production->row_id),
                'diameter_length_ringsize' => $ukuranLabel,
                'gold_weight' => $data['gold_weight'],
                'gold_color' => $data['gold_color'],
                'gold_content' => ($data['gold_content'] ?? null) ?: null,
                'jwcad_3d' => $this->resolveJwcadFile($data, $sku),
                'notes' => ($data['notes'] ?? null) ?: null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ];

            if (blank($production->request_order_no)) {
                $attributes['item_id'] = null;
                $attributes['item_name'] = $category?->category;
            } else {
                $order = $this->requestOrders->findByDocNo((string) $production->request_order_no);

                if ($order !== null) {
                    $attributes['customer_name'] = $order['customer'];
                    $attributes['item_name'] = $order['item'];
                    $attributes['item_id'] = $order['itemId'];
                }
            }

            if ($file !== null) {
                $attributes['file_name'] = $this->storeProductionImage($file);
            }

            $production->update($attributes);
            $this->syncStones($production, $data['stones'] ?? [], $actor);

            return $production->refresh();
        });
    }

    /**
     * Upload gambar SPK ke GCS bucket system-mahakarya/produksi.
     * Nilai file_name disimpan sebagai nama file (kompatibel dengan URL legacy).
     */
    private function storeProductionImage(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));
        $filename = (string) time().($extension !== '' ? '.'.$extension : '');
        $folder = (string) config('gcs.folder', 'produksi');

        $this->gcs->uploadFile($file, $folder, $filename);

        return $filename;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{estimatedDelivery: Carbon, workEstimated: int}
     */
    public function resolveEstimatedSchedule(CarbonInterface $orderDate, array $data): array
    {
        if (filled($data['estimated_delivery_time'] ?? null)) {
            $estimatedDelivery = Carbon::parse((string) $data['estimated_delivery_time'])->startOfDay();

            return [
                'estimatedDelivery' => $estimatedDelivery,
                'workEstimated' => $this->countWorkingDaysBetween($orderDate, $estimatedDelivery),
            ];
        }

        $workEstimated = (int) ($data['work_estimated'] ?? 0);

        return [
            'estimatedDelivery' => $this->calculateEstimatedDelivery($orderDate, $workEstimated),
            'workEstimated' => $workEstimated,
        ];
    }

    /**
     * Hitung jumlah hari kerja (Senin–Jumat) dari tanggal permintaan ke tanggal estimasi selesai.
     */
    public function countWorkingDaysBetween(CarbonInterface $orderDate, CarbonInterface $deliveryDate): int
    {
        $start = Carbon::parse($orderDate)->startOfDay();
        $end = Carbon::parse($deliveryDate)->startOfDay();

        if ($end->lte($start)) {
            return 0;
        }

        $count = 0;
        $date = $start->copy();

        while ($date->lt($end)) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Hitung tanggal estimated delivery dari order date + jumlah hari kerja (Senin–Jumat).
     */
    public function calculateEstimatedDelivery(CarbonInterface $orderDate, int $workingDays): Carbon
    {
        $date = Carbon::parse($orderDate)->startOfDay();

        if ($workingDays <= 0) {
            return $date;
        }

        $added = 0;

        while ($added < $workingDays) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $added++;
            }
        }

        return $date;
    }

    public function markFrameUsed(int $frameId): void
    {
        DB::connection('third')
            ->table('trframe')
            ->where('row_id', $frameId)
            ->where('is_deleted', 0)
            ->update([
                'is_used' => 'YES',
                'modified_date' => now(),
                'modified_by' => 'system',
            ]);
    }

    public function softDelete(Production $production, string $actor): void
    {
        $production->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);
    }

    /**
     * Search unused frames for the form selector.
     *
     * @return list<array{rowId: int, docNo: string, name: string, itemId: int|null}>
     */
    public function searchFrames(string $search = '', int $limit = 25): array
    {
        $limit = max(1, min($limit, 50));

        $query = DB::connection('third')
            ->table('trframe')
            ->where('is_deleted', 0)
            ->where(function ($builder): void {
                $builder->whereNull('is_used')
                    ->orWhere('is_used', '')
                    ->orWhere('is_used', 'NO')
                    ->orWhere('is_used', 0);
            })
            ->orderByDesc('row_id')
            ->limit($limit)
            ->select(['row_id', 'doc_no', 'name', 'item_id']);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('doc_no', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        return $query->get()->map(fn ($row): array => [
            'rowId' => (int) $row->row_id,
            'docNo' => (string) $row->doc_no,
            'name' => filled($row->name) ? (string) $row->name : '—',
            'itemId' => $row->item_id !== null ? (int) $row->item_id : null,
        ])->all();
    }

    /**
     * Resolve Tipe Item dari sku_prefix_categories.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCategory(array $data): ?SkuPrefixCategory
    {
        if (! filled($data['category_prefix_id'] ?? null)) {
            return null;
        }

        return SkuPrefixCategory::query()
            ->active()
            ->where('id', (int) $data['category_prefix_id'])
            ->firstOrFail();
    }

    /**
     * Resolve SKU dari sku_master.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveSku(array $data): ?SkuMaster
    {
        if (! filled($data['sku_id'] ?? null)) {
            return null;
        }

        $query = SkuMaster::query()
            ->active()
            ->where('id', (int) $data['sku_id']);

        if (filled($data['category_prefix_id'] ?? null)) {
            $query->where('category_prefix_id', (int) $data['category_prefix_id']);
        }

        return $query->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveJwcadFile(array $data, ?SkuMaster $sku): ?string
    {
        $value = trim((string) ($data['jwcad_3d'] ?? ''));

        if ($value !== '') {
            return $value;
        }

        return $sku?->resolvedJwcadFile();
    }

    private function resolveSkuImageFileName(?SkuMaster $sku): ?string
    {
        return $sku?->resolvedImageFileName();
    }

    /**
     * Pecah label gabungan ukuran menjadi 3 slot.
     *
     * @return array{diameter: string|null, dimensi: string|null, ring_size: string|null}
     */
    public function parseUkuranLabel(?string $value): array
    {
        $raw = filled($value) ? trim((string) $value) : '';

        if ($raw === '') {
            return [
                'diameter' => null,
                'dimensi' => null,
                'ring_size' => null,
            ];
        }

        return $this->splitCombinedSizeLabel($raw);
    }

    /**
     * Normalisasi field ukuran untuk form / sync (perbaiki label gabungan di diameter).
     *
     * @return array{diameter: string|null, dimensi: string|null, ring_size: string|null}
     */
    public function normalizeUkuranInput(
        ?string $diameter,
        ?string $dimensi,
        ?string $ringSize,
    ): array {
        return $this->normalizeSizeParts($diameter, $dimensi, $ringSize);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveUkuranLabel(array $data): ?string
    {
        ['diameter' => $diameter, 'dimensi' => $dimensi, 'ring_size' => $ringSize] =
            $this->normalizeSizeParts(
                filled($data['diameter'] ?? null) ? (string) $data['diameter'] : null,
                filled($data['dimensi'] ?? null) ? (string) $data['dimensi'] : null,
                filled($data['ring_size'] ?? null) ? (string) $data['ring_size'] : null,
            );

        if ($diameter !== null || $dimensi !== null || $ringSize !== null) {
            return $this->combinedSizeLabel($diameter, $dimensi, $ringSize);
        }

        return filled($data['diameter_length_ringsize'] ?? null)
            ? (string) $data['diameter_length_ringsize']
            : null;
    }

    /**
     * Normalisasi 3 field ukuran. Menangani kasus diameter terisi label gabungan
     * (mis. " / Panjang 150 / ") akibat preload form yang salah.
     *
     * @return array{diameter: string|null, dimensi: string|null, ring_size: string|null}
     */
    private function normalizeSizeParts(
        ?string $diameter,
        ?string $dimensi,
        ?string $ringSize,
    ): array {
        $diameter = filled($diameter) ? trim($diameter) : null;
        $dimensi = filled($dimensi) ? trim($dimensi) : null;
        $ringSize = filled($ringSize) ? trim($ringSize) : null;

        if ($diameter !== null && $this->looksLikeCombinedSizeLabel($diameter)) {
            $split = $this->splitCombinedSizeLabel($diameter);
            $diameter = $split['diameter'];

            if ($dimensi === null) {
                $dimensi = $split['dimensi'];
            }

            if ($ringSize === null) {
                $ringSize = $split['ring_size'];
            }
        }

        return [
            'diameter' => $diameter,
            'dimensi' => $dimensi,
            'ring_size' => $ringSize,
        ];
    }

    private function looksLikeCombinedSizeLabel(string $value): bool
    {
        return substr_count($value, '/') >= 2;
    }

    /**
     * @return array{diameter: string|null, dimensi: string|null, ring_size: string|null}
     */
    private function splitCombinedSizeLabel(string $value): array
    {
        $parts = preg_split('/\s*\/\s*/', trim($value), 3) ?: [];
        $parts = array_pad(
            array_map(
                static fn (string $part): string => trim($part),
                $parts,
            ),
            3,
            '',
        );

        return [
            'diameter' => $parts[0] !== '' ? $parts[0] : null,
            'dimensi' => $parts[1] !== '' ? $parts[1] : null,
            'ring_size' => $parts[2] !== '' ? $parts[2] : null,
        ];
    }

    private function combinedSizeLabel(
        ?string $diameter,
        ?string $dimensi,
        ?string $ringSize,
    ): ?string {
        $parts = [
            filled($diameter) ? trim((string) $diameter) : '',
            filled($dimensi) ? trim((string) $dimensi) : '',
            filled($ringSize) ? trim((string) $ringSize) : '',
        ];

        if ($parts[0] === '' && $parts[1] === '' && $parts[2] === '') {
            return null;
        }

        // Tetap 3 slot agar posisi diameter / dimensi / ring size tidak bergeser.
        return implode(' / ', $parts);
    }

    /**
     * Replace SPK stones with the submitted list.
     *
     * @param  list<array<string, mixed>>  $stones
     */
    private function syncStones(Production $production, array $stones, string $actor): void
    {
        $now = now();

        $production->stones()
            ->notDeleted()
            ->update([
                'is_deleted' => 1,
                'deleted_date' => $now,
                'deleted_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        foreach ($stones as $stone) {
            if (! is_array($stone)) {
                continue;
            }

            $pcs = isset($stone['pcs']) && $stone['pcs'] !== null && $stone['pcs'] !== ''
                ? (int) $stone['pcs']
                : null;
            $caratPerPcs = $stone['carat_per_pcs'] ?? null;
            $totalCarat = MsItemVarianceStoneCalculator::totalCarat($pcs, $caratPerPcs);

            SpkStone::query()->create([
                'row_id' => $production->row_id,
                'shape_id' => filled($stone['shape_id'] ?? null) ? (int) $stone['shape_id'] : null,
                'position_id' => MsPosition::resolveId(
                    filled($stone['position_id'] ?? null) ? (int) $stone['position_id'] : null,
                    isset($stone['position_nama']) ? (string) $stone['position_nama'] : null,
                ),
                'pcs' => $pcs,
                'carat' => $totalCarat,
                'size' => filled($stone['size'] ?? null) ? $stone['size'] : null,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertHeader(array $attributes, string $actor): Production
    {
        return DB::connection('third')->transaction(function () use ($attributes, $actor): Production {
            $now = now();

            return Production::query()->create([
                ...$attributes,
                'spk_no' => $this->generateNumber($now),
                'order_date' => $now->toDateString(),
                'status' => '',
                'is_deleted' => 0,
                'is_coran' => 0,
                'is_finishinghandmade' => 0,
                'is_polishframe' => 0,
                'is_diamondmounting' => 0,
                'is_polishfinishedgood' => 0,
                'is_grafir' => 0,
                'is_inprocess' => 0,
                'is_from_new_system' => 1,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        });
    }
}
