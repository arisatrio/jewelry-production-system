<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InventorySpkSync
{
    private const CONNECTION = 'second';

    private const TABLE = 'spk';

    private const INVENTORY_TABLE = 'inventory';

    private ?bool $tableAvailable = null;

    private ?bool $inventoryTableAvailable = null;

    /** @var array<string, bool> */
    private array $columnAvailability = [];

    /** @var array<string, bool> */
    private array $inventoryColumnAvailability = [];

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

    public function inventoryTableAvailable(): bool
    {
        if ($this->inventoryTableAvailable !== null) {
            return $this->inventoryTableAvailable;
        }

        $this->inventoryTableAvailable = Schema::connection(self::CONNECTION)
            ->hasTable(self::INVENTORY_TABLE);

        return $this->inventoryTableAvailable;
    }

    public function inventoryHasColumn(string $column): bool
    {
        if (isset($this->inventoryColumnAvailability[$column])) {
            return $this->inventoryColumnAvailability[$column];
        }

        if (! $this->inventoryTableAvailable()) {
            return $this->inventoryColumnAvailability[$column] = false;
        }

        $this->inventoryColumnAvailability[$column] = Schema::connection(self::CONNECTION)
            ->hasColumn(self::INVENTORY_TABLE, $column);

        return $this->inventoryColumnAvailability[$column];
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function syncSpkNumbers(Collection $productions, string $actor): void
    {
        $now = now();

        foreach ($productions as $production) {
            $spkNo = trim((string) $production->spk_no);
            $plu = trim((string) $production->plu);

            if ($spkNo === '' || $plu === '') {
                continue;
            }

            $this->syncLegacySpkTableRow($plu, $spkNo, $actor, $now);
            $this->syncInventoryTableRow($plu, $spkNo, $actor, $now);
        }
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function clearSpkNumbers(Collection $productions, string $actor): void
    {
        $now = now();

        foreach ($productions as $production) {
            $plu = trim((string) $production->plu);

            if ($plu === '') {
                continue;
            }

            $this->clearLegacySpkTableRow($plu, $actor, $now);
            $this->clearInventoryTableRow($plu, $actor, $now);
        }
    }

    private function syncLegacySpkTableRow(string $plu, string $spkNo, string $actor, mixed $now): void
    {
        if (! $this->tableAvailable() || ! $this->hasColumn('spk_no') || ! $this->hasColumn('plu')) {
            return;
        }

        $payload = ['spk_no' => $spkNo];

        if ($this->hasColumn('modified_date')) {
            $payload['modified_date'] = $now;
        }

        if ($this->hasColumn('modified_by')) {
            $payload['modified_by'] = $actor;
        }

        $query = DB::connection(self::CONNECTION)->table(self::TABLE)->where('plu', $plu);

        if ($this->hasColumn('is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $updated = $query->update($payload);

        if ($updated === 0) {
            Log::info('Import faktur barang jadi: tidak ada baris spk inventori (second) untuk PLU.', [
                'plu' => $plu,
                'spk_no' => $spkNo,
            ]);
        }
    }

    private function syncInventoryTableRow(string $plu, string $spkNo, string $actor, mixed $now): void
    {
        $pluColumn = $this->inventoryPluColumn();

        if (
            $pluColumn === null
            || ! $this->inventoryTableAvailable()
            || ! $this->inventoryHasColumn('spk')
        ) {
            return;
        }

        $payload = ['spk' => $spkNo];

        if ($this->inventoryHasColumn('modified_date')) {
            $payload['modified_date'] = $now;
        }

        if ($this->inventoryHasColumn('modified_by')) {
            $payload['modified_by'] = $actor;
        }

        $query = DB::connection(self::CONNECTION)
            ->table(self::INVENTORY_TABLE)
            ->where($pluColumn, $plu);

        if ($this->inventoryHasColumn('is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $query->update($payload);
    }

    private function clearLegacySpkTableRow(string $plu, string $actor, mixed $now): void
    {
        if (! $this->tableAvailable() || ! $this->hasColumn('spk_no') || ! $this->hasColumn('plu')) {
            return;
        }

        $payload = ['spk_no' => null];

        if ($this->hasColumn('modified_date')) {
            $payload['modified_date'] = $now;
        }

        if ($this->hasColumn('modified_by')) {
            $payload['modified_by'] = $actor;
        }

        $query = DB::connection(self::CONNECTION)->table(self::TABLE)->where('plu', $plu);

        if ($this->hasColumn('is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $query->update($payload);
    }

    private function clearInventoryTableRow(string $plu, string $actor, mixed $now): void
    {
        $pluColumn = $this->inventoryPluColumn();

        if (
            $pluColumn === null
            || ! $this->inventoryTableAvailable()
            || ! $this->inventoryHasColumn('spk')
        ) {
            return;
        }

        $payload = ['spk' => null];

        if ($this->inventoryHasColumn('modified_date')) {
            $payload['modified_date'] = $now;
        }

        if ($this->inventoryHasColumn('modified_by')) {
            $payload['modified_by'] = $actor;
        }

        $query = DB::connection(self::CONNECTION)
            ->table(self::INVENTORY_TABLE)
            ->where($pluColumn, $plu);

        if ($this->inventoryHasColumn('is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $query->update($payload);
    }

    private function inventoryPluColumn(): ?string
    {
        if ($this->inventoryHasColumn('plu')) {
            return 'plu';
        }

        if ($this->inventoryHasColumn('identity_code')) {
            return 'identity_code';
        }

        return null;
    }
}
