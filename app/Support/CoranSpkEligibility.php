<?php

namespace App\Support;

use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CoranSpkEligibility
{
    public const PROCESS_KEY = 'Coran';

    /**
     * SPK yang sudah selesai Resin tetapi belum punya dokumen coran.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $builder) => app(ResinSpkEligibility::class)->applyCompletedScope($builder))
            ->whereNotIn('row_id', $this->assignedSpkIdsSubquery());
    }

    /**
     * Update proses terakhir SPK ke Coran saat SPK masuk dokumen coran.
     */
    public function markProcessStarted(Production $production, string $actor): Production
    {
        if ($this->alreadyInCoranProcess($production)) {
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
     * Salin berat hasil coran ke last_weight SPK.
     */
    public function syncLastWeight(Production $production, mixed $weight, string $actor): Production
    {
        if (! filled($weight) || ! is_numeric($weight)) {
            return $production;
        }

        $production->update([
            'last_weight' => number_format((float) $weight, 3, '.', ''),
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        return $production->refresh();
    }

    private function alreadyInCoranProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * SPK dengan coran berstatus open / in progress.
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
     * SPK dengan coran berstatus selesai.
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
     * @return array<int, array{coranId: int, docNo: string|null}>
     */
    public function coranRefsBySpkIds(array $spkIds): array
    {
        if ($spkIds === []) {
            return [];
        }

        $refs = [];

        $rows = CoranSpk::query()
            ->join('coran as documents', 'documents.row_id', '=', 'coranspk.row_id')
            ->where('coranspk.is_deleted', 0)
            ->where('documents.is_deleted', 0)
            ->whereIn('coranspk.spk_id', $spkIds)
            ->orderByDesc('documents.row_id')
            ->get([
                'coranspk.spk_id',
                'documents.row_id as coran_id',
                'documents.doc_no',
            ]);

        foreach ($rows as $row) {
            $spkId = (int) $row->spk_id;

            if (isset($refs[$spkId])) {
                continue;
            }

            $refs[$spkId] = [
                'coranId' => (int) $row->coran_id,
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
            $detailQuery = DB::connection('third')->table('coranspk')
                ->select('coranspk.spk_id')
                ->join('coran', 'coran.row_id', '=', 'coranspk.row_id')
                ->where('coranspk.is_deleted', 0)
                ->where('coran.is_deleted', 0);

            if ($scope === 'in_progress') {
                $detailQuery->where(function ($query): void {
                    $query->whereNull('coran.status')
                        ->orWhereRaw("TRIM(coran.status) = ''")
                        ->orWhereRaw("UPPER(TRIM(coran.status)) = 'DRAFT'")
                        ->orWhereRaw("UPPER(TRIM(coran.status)) = 'OPEN'")
                        ->orWhere('coran.status', CoranApprovalService::STATUS_SUBMITTED)
                        ->orWhere('coran.status', CoranApprovalService::STATUS_MANAGER);
                });
            }

            if ($scope === 'completed') {
                $detailQuery->where('coran.status', CoranApprovalService::STATUS_DONE);
            }

            $outerQuery->select('spk_id')->from($detailQuery, 'assigned_coran_spks');
        };
    }
}
