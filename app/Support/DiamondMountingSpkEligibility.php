<?php

namespace App\Support;

use App\Models\DiamondMounting;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DiamondMountingSpkEligibility
{
    public const PROCESS_KEY = 'Pasang Batu';

    /**
     * SPK yang sudah selesai Poles Rangka tetapi belum punya dokumen Pasang Batu.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $builder) => app(PolishFrameSpkEligibility::class)->applyCompletedScope($builder))
            ->whereNotIn('row_id', $this->assignedSpkIdsSubquery());
    }

    /**
     * Update proses terakhir SPK ke Pasang Batu saat SPK masuk dokumen.
     */
    public function markProcessStarted(Production $production, string $actor): Production
    {
        if ($this->alreadyInProcess($production)) {
            return $production;
        }

        $production->update([
            'last_process' => self::PROCESS_KEY,
            'is_inprocess' => 1,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        return $production->refresh();
    }

    /**
     * Salin berat akhir Pasang Batu ke last_weight SPK.
     */
    public function syncLastWeight(Production $production, mixed $weight, string $actor): Production
    {
        if (! filled($weight) || ! is_numeric($weight)) {
            return $production;
        }

        $production->update([
            'last_weight' => number_format((float) $weight, 2, '.', ''),
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        return $production->refresh();
    }

    private function alreadyInProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * SPK dengan Pasang Batu berstatus open / in progress.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyInProgressScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->whereIn('row_id', $this->assignedSpkIdsSubquery('in_progress'));
    }

    /**
     * SPK dengan Pasang Batu berstatus selesai.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyCompletedScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->whereIn('row_id', $this->assignedSpkIdsSubquery('completed'));
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, array{diamondMountingId: int, docNo: string|null}>
     */
    public function diamondMountingRefsBySpkIds(array $spkIds): array
    {
        if ($spkIds === []) {
            return [];
        }

        $refs = [];

        $rows = DiamondMounting::query()
            ->notDeleted()
            ->whereIn('spk_id', $spkIds)
            ->orderByDesc('row_id')
            ->get(['row_id', 'spk_id', 'doc_no']);

        foreach ($rows as $row) {
            $spkId = (int) $row->spk_id;

            if (isset($refs[$spkId])) {
                continue;
            }

            $refs[$spkId] = [
                'diamondMountingId' => (int) $row->row_id,
                'docNo' => filled($row->doc_no) ? (string) $row->doc_no : null,
            ];
        }

        return $refs;
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private function assignedSpkIdsSubquery(?string $scope = null): \Closure
    {
        return function ($outerQuery) use ($scope): void {
            $query = DB::connection('third')->table('diamondmounting')
                ->select('spk_id')
                ->where('is_deleted', 0)
                ->whereNotNull('spk_id');

            if ($scope === 'in_progress') {
                $query->where(function ($statusQuery): void {
                    $statusQuery->whereNull('status')
                        ->orWhereRaw("TRIM(status) = ''")
                        ->orWhereRaw("UPPER(TRIM(status)) = 'DRAFT'")
                        ->orWhereRaw("UPPER(TRIM(status)) = 'OPEN'")
                        ->orWhereRaw("TRIM(status) = '-'")
                        ->orWhere('status', DiamondMountingApprovalService::STATUS_SUBMITTED)
                        ->orWhere('status', DiamondMountingApprovalService::STATUS_MANAGER)
                        ->orWhere('status', DiamondMounting::STATUS_TO_CRAFTSMAN)
                        ->orWhere('status', DiamondMounting::STATUS_FROM_CRAFTSMAN)
                        ->orWhere('status', DiamondMounting::STATUS_TO_PPIC);
                });
            }

            if ($scope === 'completed') {
                $query->whereIn('status', [
                    DiamondMountingApprovalService::STATUS_DONE,
                    DiamondMounting::STATUS_DONE,
                ]);
            }

            $outerQuery->select('spk_id')->from($query, 'assigned_diamondmounting_spks');
        };
    }
}
