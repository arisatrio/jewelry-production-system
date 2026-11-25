<?php

namespace App\Support;

use App\Models\SkuMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryPluSkuLookup
{
    private const CONNECTION = 'second';

    private const TABLE = 'inventory';

    private ?bool $tableAvailable = null;

    /** @var array<string, bool> */
    private array $columnAvailability = [];

    public function __construct(
        private SkuMasterDescriptionExtractor $descriptionExtractor,
    ) {}

    /**
     * Ringkasan item SKU per PLU (sama bentuk dengan kolom Item SPK).
     *
     * @param  Collection<int, string|null>|list<string|null>  $plus
     * @return array<string, array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, skuId: int}>
     */
    public function presentationsByPlu(Collection|array $plus): array
    {
        $skuIdByPlu = $this->resolveSkuIdsByPlu($plus);

        if ($skuIdByPlu === []) {
            return [];
        }

        $skus = SkuMaster::query()
            ->whereIn('id', array_values($skuIdByPlu))
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

        $result = [];

        foreach ($skuIdByPlu as $plu => $skuId) {
            $sku = $skus->get($skuId);

            if ($sku === null) {
                continue;
            }

            $result[$plu] = $this->presentationForSku($sku);
        }

        return $result;
    }

    /**
     * @param  Collection<int, string|null>|list<string|null>  $plus
     * @return array<string, int>
     */
    private function resolveSkuIdsByPlu(Collection|array $plus): array
    {
        $pluColumn = $this->pluColumn();

        if ($pluColumn === null || ! $this->hasColumn('sku_id')) {
            return [];
        }

        $normalizedPlus = collect($plus)
            ->filter(fn (mixed $plu): bool => filled($plu))
            ->map(fn (mixed $plu): string => trim((string) $plu))
            ->filter(fn (string $plu): bool => $plu !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalizedPlus === []) {
            return [];
        }

        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->whereIn($pluColumn, $normalizedPlus)
            ->whereNotNull('sku_id');

        if ($this->hasColumn('is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $select = [$pluColumn, 'sku_id'];

        if ($this->hasColumn('row_id')) {
            $select[] = 'row_id';
            $query->orderByDesc('row_id');
        }

        /** @var array<string, int> $skuIdByPlu */
        $skuIdByPlu = [];

        foreach ($query->get($select) as $row) {
            $plu = trim((string) ($row->{$pluColumn} ?? ''));

            if ($plu === '' || isset($skuIdByPlu[$plu])) {
                continue;
            }

            $skuId = (int) ($row->sku_id ?? 0);

            if ($skuId > 0) {
                $skuIdByPlu[$plu] = $skuId;
            }
        }

        return $skuIdByPlu;
    }

    /**
     * @return array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, skuId: int}
     */
    private function presentationForSku(SkuMaster $sku): array
    {
        $typeCode = trim((string) ($sku->categoryPrefix?->prefix ?? ''));
        $skuCode = trim((string) ($sku->sku_code ?? ''));
        $parts = array_values(array_filter(
            [$typeCode, $skuCode],
            fn (string $part): bool => $part !== '',
        ));
        $typeSkuLabel = $parts !== [] ? implode(' | ', $parts) : null;

        $breakdown = trim($this->descriptionExtractor->extract($sku));
        $itemDescription = $breakdown !== '' ? $breakdown : null;

        return [
            'typeSkuLabel' => $typeSkuLabel,
            'itemDescription' => $itemDescription,
            'skuAssigned' => $typeSkuLabel !== null || $itemDescription !== null,
            'skuId' => (int) $sku->id,
        ];
    }

    public function tableAvailable(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        $this->tableAvailable = Schema::connection(self::CONNECTION)
            ->hasTable(self::TABLE);

        return $this->tableAvailable;
    }

    public function hasColumn(string $column): bool
    {
        if (isset($this->columnAvailability[$column])) {
            return $this->columnAvailability[$column];
        }

        if (! $this->tableAvailable()) {
            return $this->columnAvailability[$column] = false;
        }

        $this->columnAvailability[$column] = Schema::connection(self::CONNECTION)
            ->hasColumn(self::TABLE, $column);

        return $this->columnAvailability[$column];
    }

    private function pluColumn(): ?string
    {
        if ($this->hasColumn('plu')) {
            return 'plu';
        }

        if ($this->hasColumn('identity_code')) {
            return 'identity_code';
        }

        return null;
    }
}
