<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Support\InventoryPluSkuLookup;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkProductionListItemPresentation;
use App\Support\SpkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryBarangJadiController extends Controller
{
    /**
     * Daftar SPK produksi selesai (DONE Rangka & DONE Barang Jadi).
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $statusKinds = $this->resolveStatusKinds($request->input('status'));
        $pluStatus = $this->resolvePluStatus($request->input('plu_status'));
        $analytics = new SpkDashboardAnalytics;
        $pluSummary = $this->pluSummaryCounts($analytics);

        $page = $this->baseCompletedProductionQuery($analytics, $statusKinds)
            ->with(['sku', 'categoryPrefix'])
            ->tap(function (Builder $query) use ($pluStatus): void {
                if ($pluStatus === 'belum') {
                    $this->applyPluUnsetScope($query);
                } elseif ($pluStatus === 'sudah') {
                    $this->applyPluSetScope($query);
                }
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('plu', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString();

        $productions = $page->getCollection();
        $skuFromPluByPlu = app(InventoryPluSkuLookup::class)->presentationsByPlu(
            $productions->map(fn (Production $production): ?string => filled($production->plu)
                ? trim((string) $production->plu)
                : null),
        );

        $page->through(function (Production $production) use ($skuFromPluByPlu): array {
            $item = SpkProductionListItemPresentation::forProduction($production);

            $plu = filled($production->plu) ? trim((string) $production->plu) : null;
            $skuFromPlu = $plu !== null && $plu !== ''
                ? ($skuFromPluByPlu[$plu] ?? [
                    'typeSkuLabel' => null,
                    'itemDescription' => null,
                    'skuAssigned' => false,
                    'skuId' => 0,
                ])
                : null;

            return [
                'rowId' => (int) $production->row_id,
                'spkNo' => (string) ($production->spk_no ?? ''),
                'plu' => $plu,
                'skuFromPlu' => $skuFromPlu !== null
                    ? [
                        'typeSkuLabel' => $skuFromPlu['typeSkuLabel'],
                        'itemDescription' => $skuFromPlu['itemDescription'],
                        'skuAssigned' => $skuFromPlu['skuAssigned'],
                    ]
                    : null,
                'keterangan' => $this->resolvePluSkuKeterangan(
                    $item['skuAssigned'],
                    filled($production->sku_id) ? (int) $production->sku_id : null,
                    $skuFromPlu,
                ),
                'spkImageUrl' => $item['spkImageUrl'],
                'typeSkuLabel' => $item['typeSkuLabel'],
                'itemDescription' => $item['itemDescription'],
                'skuAssigned' => $item['skuAssigned'],
            ];
        });

        $doneStatusFilterOptions = [
            SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[SpkDashboardAnalytics::KEY_DONE_RANGKA],
            SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[SpkDashboardAnalytics::KEY_DONE_BARANG_JADI],
        ];

        return Inertia::render('inventory/barang-jadi/index', [
            'items' => $page,
            'pluSummary' => $pluSummary,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'plu_status' => $pluStatus,
                'status' => collect($statusKinds)
                    ->map(fn (string $kind): string => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$kind] ?? $kind)
                    ->values()
                    ->all(),
            ],
            'filterOptions' => [
                'status' => collect($doneStatusFilterOptions)
                    ->map(fn (string $label): array => ['value' => $label, 'label' => $label])
                    ->all(),
                'per_page' => collect([10, 25, 50, 100])
                    ->map(fn (int $size): array => ['value' => (string) $size, 'label' => (string) $size])
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array{belumDisetPlu: int, sudahDisetPlu: int}
     */
    private function pluSummaryCounts(SpkDashboardAnalytics $analytics): array
    {
        $base = $this->baseCompletedProductionQuery($analytics, []);

        return [
            'belumDisetPlu' => (clone $base)->tap(fn (Builder $query) => $this->applyPluUnsetScope($query))->count(),
            'sudahDisetPlu' => (clone $base)->tap(fn (Builder $query) => $this->applyPluSetScope($query))->count(),
        ];
    }

    /**
     * @param  list<string>  $statusKinds
     * @return Builder<Production>
     */
    private function baseCompletedProductionQuery(SpkDashboardAnalytics $analytics, array $statusKinds): Builder
    {
        return Production::query()
            ->notDeleted()
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->tap(function (Builder $query) use ($analytics, $statusKinds): void {
                if (count($statusKinds) === 1) {
                    $analytics->constrainToCompletedProductionKind($query, $statusKinds[0]);
                } else {
                    $analytics->constrainToCompletedProduction($query);
                }
            });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyPluSetScope(Builder $query): void
    {
        $query->whereNotNull('plu')
            ->where('plu', '!=', '')
            ->whereRaw("TRIM(plu) <> ''");
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyPluUnsetScope(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('plu')
                ->orWhere('plu', '')
                ->orWhereRaw("TRIM(plu) = ''");
        });
    }

    /**
     * @param  array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, skuId?: int}|null  $skuFromPlu
     */
    private function resolvePluSkuKeterangan(
        bool $spkSkuAssigned,
        ?int $spkSkuId,
        ?array $skuFromPlu,
    ): ?string {
        if (! $spkSkuAssigned || $spkSkuId === null || $spkSkuId <= 0 || $skuFromPlu === null) {
            return null;
        }

        $inventorySkuId = (int) ($skuFromPlu['skuId'] ?? 0);

        if ($inventorySkuId <= 0) {
            return null;
        }

        return $spkSkuId !== $inventorySkuId ? 'SKU Tidak Sama' : null;
    }

    private function resolvePluStatus(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $value = trim($input);

        return in_array($value, ['belum', 'sudah'], true) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function resolveStatusKinds(mixed $input): array
    {
        $values = match (true) {
            is_array($input) => $input,
            is_string($input) && trim($input) !== '' => [$input],
            default => [],
        };

        $kinds = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $kind = $this->resolveStatusKind((string) $value);

            if ($kind !== null) {
                $kinds[$kind] = true;
            }
        }

        return array_keys($kinds);
    }

    private function resolveStatusKind(string $status): ?string
    {
        $status = trim($status);

        if ($status === '') {
            return null;
        }

        if (array_key_exists($status, SpkDashboardAnalytics::BACKLOG_STATUS_LABELS)) {
            if (SpkDashboardAnalytics::isDoneStatusKey($status)) {
                return $status;
            }
        }

        $labels = SpkDashboardAnalytics::BACKLOG_STATUS_LABELS;

        foreach ([SpkDashboardAnalytics::KEY_DONE_RANGKA, SpkDashboardAnalytics::KEY_DONE_BARANG_JADI] as $key) {
            if (strcasecmp($status, $labels[$key]) === 0) {
                return $key;
            }
        }

        return null;
    }
}
