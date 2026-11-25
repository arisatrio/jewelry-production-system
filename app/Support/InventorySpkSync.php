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

    private ?bool $tableAvailable = null;

    /** @var array<string, bool> */
    private array $columnAvailability = [];

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

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function syncSpkNumbers(Collection $productions, string $actor): void
    {
        if (! $this->tableAvailable() || ! $this->hasColumn('spk_no') || ! $this->hasColumn('plu')) {
            return;
        }

        $now = now();

        foreach ($productions as $production) {
            $spkNo = trim((string) $production->spk_no);
            $plu = trim((string) $production->plu);

            if ($spkNo === '' || $plu === '') {
                continue;
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
    }
}
