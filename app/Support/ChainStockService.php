<?php

namespace App\Support;

use App\Models\MsChain;
use App\Models\SpkChain;
use App\Models\TrxChain;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChainStockService
{
    public function __construct(
        private readonly ChainUnitNumberGenerator $unitNumbers,
    ) {}

    /**
     * @param  list<int>  $msChainIds
     * @return array<int, array{stockIn: int, stockOut: int, balance: int, balanceLength: string}>
     */
    public function countsByMsChain(array $msChainIds): array
    {
        if ($msChainIds === [] || ! $this->tableAvailable()) {
            return [];
        }

        $rows = TrxChain::query()
            ->notDeleted()
            ->whereIn('mschain_id', $msChainIds)
            ->select('mschain_id')
            ->selectRaw('SUM(CASE WHEN entry_date IS NOT NULL THEN 1 ELSE 0 END) as stock_in')
            ->selectRaw('SUM(CASE WHEN out_date IS NOT NULL THEN 1 ELSE 0 END) as stock_out')
            ->selectRaw(
                'SUM(CASE WHEN entry_date IS NOT NULL AND out_date IS NULL THEN 1 ELSE 0 END) as balance'
            )
            ->selectRaw(
                'SUM(CASE WHEN entry_date IS NOT NULL AND out_date IS NULL THEN COALESCE(chain_length, 0) ELSE 0 END) as balance_length'
            )
            ->groupBy('mschain_id')
            ->get();

        $result = [];

        foreach ($msChainIds as $id) {
            $result[$id] = [
                'stockIn' => 0,
                'stockOut' => 0,
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
        }

        foreach ($rows as $row) {
            $id = (int) $row->mschain_id;
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
    public function countsForMsChain(MsChain $msChain): array
    {
        return $this->countsByMsChain([(int) $msChain->row_id])[(int) $msChain->row_id]
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
    public function availableInStockCount(int $msChainId, ?string $color = null): array
    {
        if (! $this->tableAvailable()) {
            return [
                'balance' => 0,
                'balanceLength' => '0.00',
            ];
        }

        $query = TrxChain::query()
            ->notDeleted()
            ->where('mschain_id', $msChainId)
            ->whereNotNull('entry_date')
            ->whereNull('out_date');

        $trimmedColor = is_string($color) ? trim($color) : '';

        if ($trimmedColor !== '') {
            $query->where('color', $trimmedColor);
        }

        $stats = $query
            ->selectRaw('COUNT(*) as balance')
            ->selectRaw('COALESCE(SUM(COALESCE(chain_length, 0)), 0) as balance_length')
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

        $stats = TrxChain::query()
            ->notDeleted()
            ->whereNotNull('entry_date')
            ->whereNull('out_date')
            ->whereHas('msChain', function (Builder $query) use ($search, $category): void {
                $query->notDeleted();

                if ($category !== '') {
                    $query->where('chain_category', $category);
                }

                if ($search !== '') {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('chain_category', 'like', "%{$search}%")
                            ->orWhere('item', 'like', "%{$search}%")
                            ->orWhere('item_code', 'like', "%{$search}%")
                            ->orWhere('item_name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            })
            ->selectRaw('COUNT(*) as balance')
            ->selectRaw('COALESCE(SUM(COALESCE(chain_length, 0)), 0) as balance_length')
            ->first();

        return [
            'balance' => (int) ($stats->balance ?? 0),
            'balanceLength' => number_format((float) ($stats->balance_length ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @param  array{entry_date: string, supplier: string, weight: string, purchase_price: string, color?: string|null, notes?: string|null}  $data
     */
    public function receiveFromSupplier(MsChain $msChain, array $data, string $actor): TrxChain
    {
        $length = number_format((float) $msChain->length, 2, '.', '');
        $name = filled($msChain->item_name)
            ? trim((string) $msChain->item_name)
            : MsChain::composeItemName($msChain->item, $msChain->item_code, $length);

        return TrxChain::query()->create([
            'unit_no' => $this->unitNumbers->generate(),
            'mschain_id' => (int) $msChain->row_id,
            'source_type' => TrxChain::SOURCE_PURCHASE,
            'parent_row_id' => null,
            'name' => $name,
            'item_code' => $msChain->item_code,
            'chain_length' => $length,
            'color' => $data['color'] ?? null,
            'weight' => number_format((float) $data['weight'], 2, '.', ''),
            'purchase_price' => number_format((float) $data['purchase_price'], 2, '.', ''),
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
        TrxChain $trxChain,
        string $outDate,
        int $spkId,
        ?string $notes,
        string $actor,
        ?int $spkChainLineId = null,
    ): void {
        abort_if($trxChain->is_deleted === 1, 404);
        if ($trxChain->out_date !== null) {
            throw new HttpException(422, 'Unit chain sudah keluar.');
        }

        $fulfillment = app(SpkChainFulfillment::class);

        if ($spkChainLineId !== null && $spkChainLineId > 0) {
            $spkChain = SpkChain::query()
                ->notDeleted()
                ->where('line_id', $spkChainLineId)
                ->first();

            if ($spkChain !== null) {
                $fulfillment->revokeAllocationIfAllocated($spkChain, $actor, replace: true);
            }
        }

        $trxChain->update([
            'out_date' => $outDate,
            'spk_id' => $spkId,
            'is_used' => 1,
            'notes' => $notes ?? $trxChain->notes,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        if ($spkChainLineId !== null && $spkChainLineId > 0) {
            $fulfillment->syncSpkChainAllocation(
                $spkChainLineId,
                (int) $trxChain->mschain_id,
                $actor,
            );

            return;
        }

        app(SpkChainFulfillment::class)->markAllocatedForSpkMaster(
            $spkId,
            (int) $trxChain->mschain_id,
            $actor,
        );
    }

    public function returnUnitToStock(TrxChain $trxChain, string $actor): void
    {
        abort_if($trxChain->is_deleted === 1, 404);

        if ($trxChain->out_date === null) {
            return;
        }

        $trxChain->update([
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
        return TrxChain::query()
            ->notDeleted()
            ->with(['production', 'msChain'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($query) use ($search): void {
                            $query->where('spk_no', 'like', "%{$search}%");
                        })
                        ->orWhereHas('msChain', function ($query) use ($search): void {
                            $query->notDeleted()
                                ->where(function ($query) use ($search): void {
                                    $query->where('item', 'like', "%{$search}%")
                                        ->orWhere('item_code', 'like', "%{$search}%")
                                        ->orWhere('chain_category', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrxChain $unit): array => $this->toTransactionRow($unit));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginateUnits(MsChain $msChain, int $perPage): LengthAwarePaginator
    {
        return TrxChain::query()
            ->notDeleted()
            ->where('mschain_id', $msChain->row_id)
            ->with('production')
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrxChain $unit): array => $this->toUnitRow($unit));
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function listInStockUnits(MsChain $msChain, int $limit = 50, ?string $color = null): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        $query = TrxChain::query()
            ->notDeleted()
            ->where('mschain_id', $msChain->row_id)
            ->whereNotNull('entry_date')
            ->whereNull('out_date');

        if (filled($color)) {
            $query->where('color', $color);
        }

        return $query
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get()
            ->map(fn (TrxChain $unit): array => $this->toUnitRow($unit))
            ->values()
            ->all();
    }

    /**
     * Unit in-stock untuk modal alokasi SPK: kategori sama; master terhubung di atas.
     *
     * @return list<array<string, bool|int|string|null>>
     */
    public function listInStockUnitsForSpkAllocation(SpkChain $spkChain, int $limit = 150): array
    {
        if ($spkChain->mschain_id === null || ! $this->tableAvailable()) {
            return [];
        }

        $msChain = $spkChain->msChain;

        if ($msChain === null || $msChain->is_deleted === 1) {
            return [];
        }

        $linkedMsChainId = (int) $spkChain->mschain_id;
        $category = trim((string) ($msChain->chain_category ?? $spkChain->category ?? ''));

        $rows = TrxChain::query()
            ->notDeleted()
            ->with('msChain')
            ->whereNotNull('entry_date')
            ->whereNull('out_date')
            ->when(
                $category !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'msChain',
                    fn (Builder $masterQuery): Builder => $masterQuery
                        ->notDeleted()
                        ->where('chain_category', $category),
                ),
                fn (Builder $query): Builder => $query->where(
                    'mschain_id',
                    $msChain->row_id,
                ),
            )
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get()
            ->map(function (TrxChain $unit) use ($linkedMsChainId): array {
                $row = $this->toTransactionRow($unit);
                $row['matchesMasterChain'] = (int) ($row['msChainId'] ?? 0) === $linkedMsChainId;

                return $row;
            });

        return $rows
            ->sort(function (array $left, array $right): int {
                $leftMaster = $left['matchesMasterChain'] === true ? 1 : 0;
                $rightMaster = $right['matchesMasterChain'] === true ? 1 : 0;

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
    public function unitMatchesSpkChainRequest(SpkChain $spkChain, array $unitRow): bool
    {
        $requiredColor = filled($spkChain->color) ? trim((string) $spkChain->color) : null;
        $unitColor = trim((string) ($unitRow['color'] ?? ''));

        if ($requiredColor !== null && $unitColor !== $requiredColor) {
            return false;
        }

        $requiredLength = $this->parseLengthCm($spkChain->size);
        $unitLength = $this->parseLengthCm($unitRow['chainLength'] ?? null);

        if ($requiredLength !== null && ($unitLength === null || $unitLength !== $requiredLength)) {
            return false;
        }

        return $requiredColor !== null || $requiredLength !== null;
    }

    /**
     * @return list<string>
     */
    public function distinctInStockColors(MsChain $msChain): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        return TrxChain::query()
            ->notDeleted()
            ->where('mschain_id', $msChain->row_id)
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
    public function toTransactionRow(TrxChain $unit): array
    {
        return [
            ...$this->toUnitRow($unit),
            'msChainId' => (int) $unit->mschain_id,
            'chainCategory' => $unit->msChain?->chain_category,
            'masterItem' => $unit->msChain?->item,
            'masterItemCode' => $unit->msChain?->item_code ?? $unit->item_code,
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

    public function toUnitRow(TrxChain $unit): array
    {
        return [
            'id' => (int) $unit->row_id,
            'unitNo' => $unit->unit_no,
            'name' => $unit->name,
            'sourceType' => $unit->source_type,
            'chainLength' => (string) $unit->chain_length,
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

    public function latestIssuedUnitForSpkChain(int $spkRowId, int $msChainId): ?TrxChain
    {
        if (! $this->tableAvailable()) {
            return null;
        }

        return TrxChain::query()
            ->notDeleted()
            ->where('spk_id', $spkRowId)
            ->where('mschain_id', $msChainId)
            ->whereNotNull('out_date')
            ->orderByDesc('row_id')
            ->first();
    }

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('trxchain');
    }
}
