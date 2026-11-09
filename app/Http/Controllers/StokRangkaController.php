<?php

namespace App\Http\Controllers;

use App\Models\SkuMaster;
use App\Models\StokRangka;
use App\Support\SkuMasterDescriptionExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StokRangkaController extends Controller
{
    private const DEFAULT_SORT = 'updated_at';

    private const DEFAULT_DIRECTION = 'desc';

    private const DEFAULT_STATUS = 'READY';

    private const SORTABLE_COLUMNS = [
        'doc_no',
        'sku_id',
        'category',
        'item_name',
        'variant',
        'gold_weight',
        'gold_color',
        'status',
        'date_in',
        'date_out',
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * Display a listing of frame stock from stok_rangka.
     */
    public function index(
        Request $request,
        SkuMasterDescriptionExtractor $descriptionExtractor,
    ): Response {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = $this->resolveSort($request->string('sort')->toString());
        $direction = $this->resolveDirection($request->string('direction')->toString());
        $status = $this->resolveStatusFilter($request);
        $category = $request->string('category')->trim()->toString();
        $category = $category !== '' ? $category : null;
        $goldColor = $request->string('gold_color')->trim()->toString();
        $goldColor = $goldColor !== '' ? $goldColor : null;

        $items = $this->filteredQuery(
            $search,
            $status,
            $category,
            $goldColor,
        )
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $skusById = $this->loadSkusForRows($items->getCollection());

        $items->through(
            fn (StokRangka $item): array => $this->toListItem(
                $item,
                $this->resolveSkuForRow($item, $skusById),
                $descriptionExtractor,
            ),
        );

        return Inertia::render('inventory/rangka/index', [
            'items' => $items,
            'summary' => $this->summary($search, $status, $category, $goldColor),
            'stockSummaries' => $this->stockSummaries($search, $category, $goldColor),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status,
                'category' => $category,
                'gold_color' => $goldColor,
            ],
            'filterOptions' => [
                'sort' => $this->indexSortOptions(),
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z / terkecil'],
                    ['value' => 'desc', 'label' => 'Z–A / terbesar'],
                ],
            ],
            'categoryOptions' => $this->categoryOptions(),
            'goldColorOptions' => $this->goldColorOptions(),
            'statusOptions' => $this->statusOptions(),
            'lastUpdatedAt' => $this->formatTimestamp(
                StokRangka::query()->notDeleted()->max('updated_at'),
            ),
        ]);
    }

    /**
     * Display the specified stok rangka record.
     */
    public function show(
        StokRangka $stokRangka,
        SkuMasterDescriptionExtractor $descriptionExtractor,
    ): Response {
        if ((int) $stokRangka->is_deleted === 1) {
            abort(404);
        }

        $sku = $this->resolveSkuForRow(
            $stokRangka,
            $this->loadSkusForRows(collect([$stokRangka])),
        );

        return Inertia::render('inventory/rangka/show', [
            'item' => $this->toDetailItem($stokRangka, $sku, $descriptionExtractor),
        ]);
    }

    /**
     * @return array{count: int, weight: string}
     */
    private function summary(
        string $search,
        ?string $status,
        ?string $category,
        ?string $goldColor,
    ): array {
        $query = $this->filteredQuery($search, $status, $category, $goldColor);

        return [
            'count' => (int) (clone $query)->count(),
            'weight' => number_format((float) (clone $query)->sum('gold_weight'), 2, '.', ''),
        ];
    }

    /**
     * @return array{
     *     ready: array{count: int, weight: string},
     *     used: array{count: int, weight: string}
     * }
     */
    private function stockSummaries(
        string $search,
        ?string $category,
        ?string $goldColor,
    ): array {
        $base = $this->filteredQuery($search, null, $category, $goldColor);

        $readyQuery = (clone $base)->where('status', 'READY');
        $usedQuery = (clone $base)->where('status', 'USED');

        return [
            'ready' => $this->metricsFromQuery($readyQuery),
            'used' => $this->metricsFromQuery($usedQuery),
        ];
    }

    /**
     * @param  Builder<StokRangka>  $query
     * @return array{count: int, weight: string}
     */
    private function metricsFromQuery(Builder $query): array
    {
        return [
            'count' => (int) (clone $query)->count(),
            'weight' => number_format((float) (clone $query)->sum('gold_weight'), 2, '.', ''),
        ];
    }

    /**
     * @return Builder<StokRangka>
     */
    private function filteredQuery(
        string $search,
        ?string $status,
        ?string $category,
        ?string $goldColor,
    ): Builder {
        return StokRangka::query()
            ->notDeleted()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('sku_id', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('variant', 'like', "%{$search}%")
                        ->orWhere('gold_color', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($category !== null, fn (Builder $query): Builder => $query->where('category', $category))
            ->when($goldColor !== null, fn (Builder $query): Builder => $query->where('gold_color', $goldColor));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function indexSortOptions(): array
    {
        return [
            ['value' => 'updated_at', 'label' => 'Terakhir diperbarui'],
            ['value' => 'created_at', 'label' => 'Terakhir dibuat'],
            ['value' => 'id', 'label' => 'ID'],
            ['value' => 'doc_no', 'label' => 'ID'],
            ['value' => 'item_name', 'label' => 'Nama item'],
            ['value' => 'category', 'label' => 'Kategori'],
            ['value' => 'date_in', 'label' => 'Tanggal masuk'],
            ['value' => 'date_out', 'label' => 'Tanggal keluar'],
            ['value' => 'gold_weight', 'label' => 'Berat emas'],
            ['value' => 'status', 'label' => 'Status'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return StokRangka::query()
            ->notDeleted()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function goldColorOptions(): array
    {
        return StokRangka::query()
            ->notDeleted()
            ->whereNotNull('gold_color')
            ->where('gold_color', '!=', '')
            ->distinct()
            ->orderBy('gold_color')
            ->pluck('gold_color')
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return StokRangka::query()
            ->notDeleted()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StokRangka>  $rows
     * @return Collection<int, SkuMaster>
     */
    private function loadSkusForRows(Collection $rows): Collection
    {
        $skuIds = $rows
            ->map(fn (StokRangka $row): ?int => filled($row->sku_id) ? (int) $row->sku_id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skuIds === []) {
            return collect();
        }

        return SkuMaster::query()
            ->whereIn('id', $skuIds)
            ->with([
                'goldColorPrefix',
                'categoryPrefix',
                'namePrefix',
                'sizePrefix',
                'stoneShapePrefix',
                'stoneTypePrefix',
                'diamondTypePrefix',
            ])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, SkuMaster>  $skusById
     */
    private function resolveSkuForRow(StokRangka $item, Collection $skusById): ?SkuMaster
    {
        if (! filled($item->sku_id)) {
            return null;
        }

        return $skusById->get((int) $item->sku_id);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function toDetailItem(
        StokRangka $item,
        ?SkuMaster $sku,
        SkuMasterDescriptionExtractor $descriptionExtractor,
    ): array {
        return [
            ...$this->toListItem($item, $sku, $descriptionExtractor),
            'variant' => $item->variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(
        StokRangka $item,
        ?SkuMaster $sku,
        SkuMasterDescriptionExtractor $descriptionExtractor,
    ): array {
        $skuCode = filled($sku?->sku_code) ? (string) $sku->sku_code : null;
        $skuBreakdownName = $sku !== null
            ? trim($descriptionExtractor->extract($sku))
            : null;

        if ($skuBreakdownName === '') {
            $skuBreakdownName = null;
        }

        $spkNo = filled($item->prd) ? trim((string) $item->prd) : null;

        return [
            'id' => (int) $item->id,
            'docNo' => $item->doc_no,
            'skuCode' => $skuCode,
            'skuBreakdownName' => $skuBreakdownName,
            'category' => $this->displayCategory($item, $sku),
            'itemName' => $this->displayItemName($item, $sku),
            'spkNo' => $spkNo !== '' ? $spkNo : null,
            'dateIn' => $item->date_in?->format('Y-m-d'),
            'dateOut' => $item->date_out?->format('Y-m-d'),
            'goldWeight' => $item->gold_weight,
            'goldColor' => $item->gold_color,
            'status' => $item->status,
            'createdBy' => $item->created_by,
            'updatedBy' => $item->updated_by,
            'createdAt' => $item->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $item->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveSort(string $sort): string
    {
        return in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : self::DEFAULT_SORT;
    }

    private function resolveDirection(string $direction): string
    {
        $normalized = strtolower(trim($direction));

        if ($normalized === '') {
            return self::DEFAULT_DIRECTION;
        }

        return $normalized === 'asc' ? 'asc' : 'desc';
    }

    private function resolveStatusFilter(Request $request): ?string
    {
        if (! $request->has('status')) {
            return self::DEFAULT_STATUS;
        }

        $value = $request->string('status')->trim()->toString();

        if ($value === '' || $value === 'all') {
            return null;
        }

        return $value;
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function displayCategory(StokRangka $item, ?SkuMaster $sku): ?string
    {
        $fromStock = trim((string) ($item->category ?? ''));

        if ($fromStock !== '') {
            return $fromStock;
        }

        if ($sku === null) {
            return null;
        }

        $category = trim((string) ($sku->categoryPrefix?->category ?? ''));

        if ($category !== '') {
            return $category;
        }

        $prefix = trim((string) ($sku->categoryPrefix?->prefix ?? ''));

        return $prefix !== '' ? $prefix : null;
    }

    private function displayItemName(StokRangka $item, ?SkuMaster $sku): ?string
    {
        $fromStock = trim((string) ($item->item_name ?? ''));

        if ($fromStock !== '') {
            return $fromStock;
        }

        if ($sku === null) {
            return null;
        }

        $name = trim((string) ($sku->namePrefix?->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $itemOriginal = trim((string) ($sku->item_original ?? ''));

        return $itemOriginal !== '' ? $itemOriginal : null;
    }
}
