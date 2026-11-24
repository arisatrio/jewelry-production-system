<?php

namespace App\Support;

use App\Models\CoranSpk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkProcessWeightSyncGuard
{
    /**
     * Urutan proses untuk sinkronisasi {@see Production::$last_weight}.
     * Proses yang lebih awal tidak boleh menimpa berat jika sudah ada dokumen proses berikutnya.
     *
     * @var list<string>
     */
    private const PROCESS_SOURCES = [
        'coran',
        'finishinghandmade',
        'polishframe',
        'diamondmounting',
        'polishfinishedgood',
        'grafir',
    ];

    public function shouldSyncLastWeight(int $spkId, string $sourceKey): bool
    {
        if ($spkId <= 0) {
            return false;
        }

        $sourceIndex = array_search($sourceKey, self::PROCESS_SOURCES, true);

        if ($sourceIndex === false) {
            return true;
        }

        for ($index = $sourceIndex + 1; $index < count(self::PROCESS_SOURCES); $index++) {
            if ($this->hasProcessDocument($spkId, self::PROCESS_SOURCES[$index])) {
                return false;
            }
        }

        return true;
    }

    private function hasProcessDocument(int $spkId, string $sourceKey): bool
    {
        if ($sourceKey === 'coran') {
            return CoranSpk::query()
                ->notDeleted()
                ->where('spk_id', $spkId)
                ->exists();
        }

        if (! Schema::connection('third')->hasTable($sourceKey)) {
            return false;
        }

        $query = DB::connection('third')
            ->table($sourceKey)
            ->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($sourceKey, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->exists();
    }
}
