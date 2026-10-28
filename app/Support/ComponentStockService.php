<?php

namespace App\Support;

use App\Models\MsComponent;
use App\Models\SpkComponent;
use App\Models\TrxComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ComponentStockService
{
    public const MAX_RECEIVE_PCS = 200;

    public function __construct(
        private readonly ComponentUnitNumberGenerator $unitNumbers,
    ) {}

    /**
     * @param  list<int>  $msComponentIds
     * @return array<int, array{stockIn: int, stockOut: int, balance: int, balanceLength: string}>
     */
    public function countsByMsComponent(array $msComponentIds): array
    {
        if ($msComponentIds === [] || ! $this->tableAvailable()) {
            return [];
        }

        $rows = TrxComponent::query()
            ->notDeleted()
            ->whereIn('mscomponent_id', $msComponentIds)
            ->select('mscomponent_id')
            ->selectRaw('SUM(CASE WHEN entry_date IS NOT NULL THEN 1 ELSE 0 END) as stock_in')
            ->selectRaw('SUM(CASE WHEN out_date IS NOT NULL THEN 1 ELSE 0 END) as stock_out')
            ->selectRaw(
                'SUM(CASE WHEN entry_date IS NOT NULL AND out_date IS NULL THEN 1 ELSE 0 END) as balance'
            )
            ->selectRaw(
                'SUM(CASE WHEN entry_date IS NOT NULL AND out_date IS NULL THEN COALESCE(component_size, 0) ELSE 0 END) as balance_length'
            )
            ->groupBy('mscomponent_id')
            ->get();

        $result = [];

        foreach ($msComponentIds as $id) {
            $result[$id] = [
                'stockIn' => 0,
                'stockOut' => 0,
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
        }

        foreach ($rows as $row) {
            $id = (int) $row->mscomponent_id;
            $result[$id] = [
                'stockIn' => (int) $row->stock_in,
                'stockOut' => (int) $row->stock_out,
                'balance' => (int) $row->balance,
                'balanceLength' => number_format((float) $row->balance_length, 2, '.', ''),
            ];
        }

        return $result;
    }

    /**
     * @return array{stockIn: int, stockOut: int, balance: int, balanceLength: string}
     */
    public function countsForMsComponent(MsComponent $msComponent): array
    {
        return $this->countsByMsComponent([(int) $msComponent->row_id])[(int) $msComponent->row_id]
            ?? [
                'stockIn' => 0,
                'stockOut' => 0,
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
    }

    /**
     * Unit in-stock yang masih di gudang, opsional difilter warna (selaras dengan alokasi).
     *
     * @return array{balance: int, balanceLength: string}
     */
    public function availableInStockCount(int $msComponentId, ?string $color = null): array
    {
        if (! $this->tableAvailable()) {
            return [
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
        }

        $query = TrxComponent::query()
            ->notDeleted()
            ->where('mscomponent_id', $msComponentId)
            ->whereNotNull('entry_date')
            ->whereNull('out_date');

        $trimmedColor = is_string($color) ? trim($color) : '';

        if ($trimmedColor !== '') {
            $query->where('color', $trimmedColor);
        }

        $stats = $query
            ->selectRaw('COUNT(*) as balance')
            ->selectRaw('COALESCE(SUM(COALESCE(component_size, 0)), 0) as balance_length')
            ->first();

        return [
            'balance' => (int) ($stats->balance ?? 0),
            'balanceLength' => number_format((float) ($stats->balance_length ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @return array{balance: int, balanceLength: string}
     */
    public function totalAvailableBalance(string $search = '', string $category = ''): array
    {
        if (! $this->tableAvailable()) {
            return [
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
        }

        $stats = TrxComponent::query()
            ->notDeleted()
            ->whereNotNull('entry_date')
            ->whereNull('out_date')
            ->whereHas('msComponent', function (Builder $query) use ($search, $category): void {
                $query->notDeleted();

                if ($category !== '') {
                    $query->where('component_category', $category);
                }

                if ($search !== '') {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('component_category', 'like', "%{$search}%")
                            ->orWhere('item', 'like', "%{$search}%")
                            ->orWhere('item_code', 'like', "%{$search}%")
                            ->orWhere('item_name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            })
            ->selectRaw('COUNT(*) as balance')
            ->selectRaw('COALESCE(SUM(COALESCE(component_size, 0)), 0) as balance_length')
            ->first();

        return [
            'balance' => (int) ($stats->balance ?? 0),
            'balanceLength' => number_format((float) ($stats->balance_length ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @param  array{entry_date: string, supplier: string, weight: string, purchase_price?: string|null, component_size?: string|null, color?: string|null, notes?: string|null}  $data
     */
    public function receiveFromSupplier(MsComponent $msComponent, array $data, string $actor): TrxComponent
    {
        return $this->receiveUnitsFromSupplier($msComponent, $data, $actor, 1)[0];
    }

    /**
     * @param  array{entry_date: string, supplier: string, weight: string, purchase_price?: string|null, component_size?: string|null, color?: string|null, notes?: string|null}  $data
     * @return list<TrxComponent>
     */
    public function receiveUnitsFromSupplier(
        MsComponent $msComponent,
        array $data,
        string $actor,
        int $pcs,
    ): array {
        if ($pcs < 1 || $pcs > self::MAX_RECEIVE_PCS) {
            throw new HttpException(422, 'Jumlah pcs tidak valid.');
        }

        return DB::connection('third')->transaction(function () use ($msComponent, $data, $actor, $pcs): array {
            $unitNumbers = $this->unitNumbers->reserveSequence($pcs);
            $units = [];

            foreach ($unitNumbers as $unitNo) {
                $units[] = $this->createPurchaseUnit($msComponent, $data, $actor, $unitNo);
            }

            return $units;
        });
    }

    /**
     * @param  array{entry_date: string, supplier: string, weight: string, purchase_price?: string|null, component_size?: string|null, color?: string|null, notes?: string|null}  $data
     */
    private function createPurchaseUnit(
        MsComponent $msComponent,
        array $data,
        string $actor,
        string $unitNo,
    ): TrxComponent {
        $componentSize = number_format((float) ($data['component_size'] ?? 0), 2, '.', '');
        $name = filled($msComponent->item_name)
            ? trim((string) $msComponent->item_name)
            : MsComponent::composeItemName($msComponent->item, $msComponent->item_code);

        return TrxComponent::query()->create([
            'unit_no' => $unitNo,
            'mscomponent_id' => (int) $msComponent->row_id,
            'source_type' => TrxComponent::SOURCE_PURCHASE,
            'parent_row_id' => null,
            'name' => $name,
            'item_code' => $msComponent->item_code,
            'component_size' => $componentSize,
            'color' => $data['color'] ?? null,
            'weight' => number_format((float) $data['weight'], 2, '.', ''),
            'purchase_price' => number_format((float) ($data['purchase_price'] ?? 0), 2, '.', ''),
            'supplier' => $data['supplier'],
            'entry_date' => $data['entry_date'],
            'out_date' => null,
            'spk_id' => null,
            'is_used' => 0,
            'notes' => $data['notes'] ?? null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);
    }

    public function issueManual(
        TrxComponent $trxKomponen,
        string $outDate,
        int $spkId,
        ?string $notes,
        string $actor,
        ?int $spkKomponenLineId = null,
    ): void {
        abort_if($trxKomponen->is_deleted === 1, 404);
        if ($trxKomponen->out_date !== null) {
            throw new HttpException(422, 'Unit komponen sudah keluar.');
        }

        $fulfillment = app(SpkComponentFulfillment::class);

        if ($spkKomponenLineId !== null && $spkKomponenLineId > 0) {
            $spkKomponen = SpkComponent::query()
                ->notDeleted()
                ->where('line_id', $spkKomponenLineId)
                ->first();

            if ($spkKomponen !== null) {
                $fulfillment->revokeAllocationIfAllocated($spkKomponen, $actor, replace: true);
            }
        }

        $trxKomponen->update([
            'out_date' => $outDate,
            'spk_id' => $spkId,
            'is_used' => 1,
            'notes' => $notes ?? $trxKomponen->notes,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        if ($spkKomponenLineId !== null && $spkKomponenLineId > 0) {
            $fulfillment->syncSpkComponentAllocation(
                $spkKomponenLineId,
                (int) $trxKomponen->mscomponent_id,
                $actor,
            );

            return;
        }

        app(SpkComponentFulfillment::class)->markAllocatedForSpkMaster(
            $spkId,
            (int) $trxKomponen->mscomponent_id,
            $actor,
        );
    }

    public function returnUnitToStock(TrxComponent $trxKomponen, string $actor): void
    {
        abort_if($trxKomponen->is_deleted === 1, 404);

        if ($trxKomponen->out_date === null) {
            return;
        }

        $trxKomponen->update([
            'out_date' => null,
            'spk_id' => null,
            'is_used' => 0,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginateAllUnits(string $search, int $perPage): LengthAwarePaginator
    {
        return TrxComponent::query()
            ->notDeleted()
            ->with(['production', 'msComponent'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($query) use ($search): void {
                            $query->where('spk_no', 'like', "%{$search}%");
                        })
                        ->orWhereHas('msComponent', function ($query) use ($search): void {
                            $query->notDeleted()
                                ->where(function ($query) use ($search): void {
                                    $query->where('item', 'like', "%{$search}%")
                                        ->orWhere('item_code', 'like', "%{$search}%")
                                        ->orWhere('component_category', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrxComponent $unit): array => $this->toTransactionRow($unit));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginateUnits(MsComponent $msComponent, int $perPage): LengthAwarePaginator
    {
        return TrxComponent::query()
            ->notDeleted()
            ->where('mscomponent_id', $msComponent->row_id)
            ->with('production')
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrxComponent $unit): array => $this->toUnitRow($unit));
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function listInStockUnits(MsComponent $msComponent, int $limit = 50, ?string $color = null): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        $query = TrxComponent::query()
            ->notDeleted()
            ->where('mscomponent_id', $msComponent->row_id)
            ->whereNotNull('entry_date')
            ->whereNull('out_date');

        if (filled($color)) {
            $query->where('color', $color);
        }

        return $query
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get()
            ->map(fn (TrxComponent $unit): array => $this->toUnitRow($unit))
            ->values()
            ->all();
    }

    /**
     * Unit in-stock untuk modal alokasi SPK: kategori sama; master terhubung di atas.
     *
     * @return list<array<string, bool|int|string|null>>
     */
    public function listInStockUnitsForSpkAllocation(SpkComponent $spkKomponen, int $limit = 150): array
    {
        if ($spkKomponen->mscomponent_id === null || ! $this->tableAvailable()) {
            return [];
        }

        $msComponent = $spkKomponen->msComponent;

        if ($msComponent === null || $msComponent->is_deleted === 1) {
            return [];
        }

        $linkedMsComponentId = (int) $spkKomponen->mscomponent_id;
        $category = trim((string) ($msComponent->component_category ?? $spkKomponen->category ?? ''));

        $rows = TrxComponent::query()
            ->notDeleted()
            ->with('msComponent')
            ->whereNotNull('entry_date')
            ->whereNull('out_date')
            ->when(
                $category !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'msComponent',
                    fn (Builder $masterQuery): Builder => $masterQuery
                        ->notDeleted()
                        ->where('component_category', $category),
                ),
                fn (Builder $query): Builder => $query->where(
                    'mscomponent_id',
                    $msComponent->row_id,
                ),
            )
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get()
            ->map(function (TrxComponent $unit) use ($linkedMsComponentId): array {
                $row = $this->toTransactionRow($unit);
                $row['matchesMasterComponent'] = (int) ($row['msComponentId'] ?? 0) === $linkedMsComponentId;

                return $row;
            });

        return $rows
            ->sort(function (array $left, array $right): int {
                $leftMaster = $left['matchesMasterComponent'] === true ? 1 : 0;
                $rightMaster = $right['matchesMasterComponent'] === true ? 1 : 0;

                if ($leftMaster !== $rightMaster) {
                    return $rightMaster <=> $leftMaster;
                }

                return ((int) $right['id']) <=> ((int) $left['id']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int|string|null>  $unitRow
     */
    public function unitMatchesSpkComponentRequest(SpkComponent $spkKomponen, array $unitRow): bool
    {
        $requiredColor = filled($spkKomponen->color) ? trim((string) $spkKomponen->color) : null;
        $unitColor = trim((string) ($unitRow['color'] ?? ''));

        if ($requiredColor !== null && $unitColor !== $requiredColor) {
            return false;
        }

        $requiredLength = $this->parseLengthCm($spkKomponen->size);
        $unitLength = $this->parseLengthCm($unitRow['componentSize'] ?? null);

        if ($requiredLength !== null && ($unitLength === null || $unitLength !== $requiredLength)) {
            return false;
        }

        return $requiredColor !== null || $requiredLength !== null;
    }

    /**
     * @return list<string>
     */
    public function distinctInStockColors(MsComponent $msComponent): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        return TrxComponent::query()
            ->notDeleted()
            ->where('mscomponent_id', $msComponent->row_id)
            ->whereNotNull('entry_date')
            ->whereNull('out_date')
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->orderBy('color')
            ->distinct()
            ->pluck('color')
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toTransactionRow(TrxComponent $unit): array
    {
        return [
            ...$this->toUnitRow($unit),
            'msComponentId' => (int) $unit->mscomponent_id,
            'componentCategory' => $unit->msComponent?->component_category,
            'masterItem' => $unit->msComponent?->item,
            'masterItemCode' => $unit->msComponent?->item_code ?? $unit->item_code,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function parseLengthCm(mixed $value): ?int
    {
        $text = trim((string) $value);

        if ($text === '' || $text === '-') {
            return null;
        }

        $lower = strtolower($text);

        if (str_ends_with($lower, 'cm')) {
            $text = trim(substr($text, 0, strlen($text) - 2));
        }

        $normalized = str_replace(',', '.', $text);

        if (! is_numeric($normalized)) {
            if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches) !== 1) {
                return null;
            }

            $normalized = $matches[1];
        }

        return (int) round((float) $normalized);
    }

    public function toUnitRow(TrxComponent $unit): array
    {
        return [
            'id' => (int) $unit->row_id,
            'unitNo' => $unit->unit_no,
            'name' => $unit->name,
            'sourceType' => $unit->source_type,
            'componentSize' => (string) $unit->component_size,
            'weight' => $unit->weight !== null ? (string) $unit->weight : null,
            'color' => $unit->color,
            'supplier' => $unit->supplier,
            'purchasePrice' => $unit->purchase_price !== null
                ? (string) $unit->purchase_price
                : null,
            'entryDate' => $unit->entry_date?->format('Y-m-d'),
            'outDate' => $unit->out_date?->format('Y-m-d'),
            'spkId' => $unit->spk_id,
            'spkNo' => $unit->production?->spk_no,
            'notes' => $unit->notes,
            'status' => $unit->out_date === null ? 'in_stock' : 'issued',
            'createdBy' => $unit->created_by,
            'createdDate' => $unit->created_date?->format('Y-m-d H:i:s'),
            'modifiedBy' => $unit->modified_by,
            'modifiedDate' => $unit->modified_date?->format('Y-m-d H:i:s'),
        ];
    }

    public function latestIssuedUnitForSpkComponent(int $spkRowId, int $msComponentId): ?TrxComponent
    {
        if (! $this->tableAvailable()) {
            return null;
        }

        return TrxComponent::query()
            ->notDeleted()
            ->where('spk_id', $spkRowId)
            ->where('mscomponent_id', $msComponentId)
            ->whereNotNull('out_date')
            ->orderByDesc('row_id')
            ->first();
    }

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('trxcomponent');
    }
}
