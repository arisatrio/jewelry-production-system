<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;

class SpkAllocationSpkEligibility
{
    /**
     * SPK yang sudah di-approve Manager Produksi (status dokumen SPKDONE).
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyManagerApprovedScope(Builder $query): Builder
    {
        return $query->where('status', SpkApprovalService::STATUS_DONE);
    }

    public function isManagerApproved(Production $production): bool
    {
        if ($production->is_deleted !== 0 || blank($production->spk_no)) {
            return false;
        }

        return strtoupper(trim((string) $production->status)) === SpkApprovalService::STATUS_DONE;
    }
}
