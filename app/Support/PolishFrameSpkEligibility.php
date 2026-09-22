<?php

namespace App\Support;

use App\Models\PolishFrame;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PolishFrameSpkEligibility
{
    public const PROCESS_KEY = 'Poles Rangka';

    /**
     * SPK yang sudah selesai Finishing tetapi belum punya dokumen Poles Rangka.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $builder) => app(FinishingSpkEligibility::class)->applyCompletedScope($builder))
            ->whereNotIn('row_id', $this->assignedSpkIdsSubquery());
    }

    /**
     * Update proses terakhir SPK ke Poles Rangka saat SPK masuk dokumen.
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

    private function alreadyInProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * SPK dengan Poles Rangka berstatus open / in progress.
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
     * SPK dengan Poles Rangka berstatus selesai.
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
     * @return array<int, array{polishFrameId: int, docNo: string|null}>
     */
    public function polishFrameRefsBySpkIds(array $spkIds): array
    {
        if ($spkIds === []) {
            return [];
        }

        $refs = [];

        $rows = PolishFrame::query()
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
                'polishFrameId' => (int) $row->row_id,
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
            $query = DB::connection('third')->table('polishframe')
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
                        ->orWhere('status', PolishFrameApprovalService::STATUS_SUBMITTED)
                        ->orWhere('status', PolishFrameApprovalService::STATUS_MANAGER)
                        ->orWhere('status', PolishFrame::STATUS_TO_CRAFTSMAN)
                        ->orWhere('status', PolishFrame::STATUS_FROM_CRAFTSMAN)
                        ->orWhere('status', PolishFrame::STATUS_TO_PPIC);
                });
            }

            if ($scope === 'completed') {
                $query->whereIn('status', [
                    PolishFrameApprovalService::STATUS_DONE,
                    PolishFrame::STATUS_DONE,
                    PolishFrame::STATUS_TO_JB,
                ]);
            }

            $outerQuery->select('spk_id')->from($query, 'assigned_polishframe_spks');
        };
    }
}
