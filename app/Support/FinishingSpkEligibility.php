<?php

namespace App\Support;

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinishingSpkEligibility
{
    public const PROCESS_KEY = 'Finishing';

    /**
     * SPK yang sudah selesai Coran tetapi belum punya dokumen finishing.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $builder) => app(CoranSpkEligibility::class)->applyCompletedScope($builder))
            ->whereNotIn('row_id', $this->assignedSpkIdsSubquery());
    }

    /**
     * Update proses terakhir SPK ke Finishing saat SPK masuk dokumen finishing.
     */
    public function markProcessStarted(Production $production, string $actor): Production
    {
        if ($this->alreadyInFinishingProcess($production)) {
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
     * Salin berat akhir finishing ke last_weight SPK.
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

    private function alreadyInFinishingProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * SPK dengan finishing berstatus open / in progress.
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
     * SPK dengan finishing berstatus selesai.
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
     * @return array<int, array{finishingId: int, docNo: string|null}>
     */
    public function finishingRefsBySpkIds(array $spkIds): array
    {
        if ($spkIds === []) {
            return [];
        }

        $refs = [];

        $rows = FinishingHandmade::query()
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
                'finishingId' => (int) $row->row_id,
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
            $query = DB::connection('third')->table('finishinghandmade')
                ->select('spk_id')
                ->where('is_deleted', 0)
                ->whereNotNull('spk_id');

            if ($scope === 'in_progress') {
                $query->where(function ($statusQuery): void {
                    $statusQuery->whereNull('status')
                        ->orWhereRaw("TRIM(status) = ''")
                        ->orWhereRaw("UPPER(TRIM(status)) = 'DRAFT'")
                        ->orWhereRaw("UPPER(TRIM(status)) = 'OPEN'")
                        ->orWhere('status', FinishingApprovalService::STATUS_SUBMITTED)
                        ->orWhere('status', FinishingApprovalService::STATUS_MANAGER)
                        ->orWhere('status', FinishingApprovalService::LEGACY_NEW_STATUS_SUBMITTED)
                        ->orWhere('status', FinishingApprovalService::LEGACY_NEW_STATUS_MANAGER)
                        ->orWhere('status', FinishingHandmade::STATUS_OPEN)
                        ->orWhere('status', FinishingHandmade::STATUS_TO_CRAFTSMAN)
                        ->orWhere('status', FinishingHandmade::STATUS_TO_PPIC)
                        ->orWhere('status', FinishingHandmade::STATUS_REPARATION_OPEN);
                });
            }

            if ($scope === 'completed') {
                $query->whereIn('status', [
                    FinishingApprovalService::STATUS_DONE,
                    FinishingApprovalService::LEGACY_NEW_STATUS_DONE,
                    FinishingHandmade::STATUS_DONE,
                    FinishingHandmade::STATUS_REPARATION_DONE,
                ]);
            }

            $outerQuery->select('spk_id')->from($query, 'assigned_finishing_spks');
        };
    }
}
