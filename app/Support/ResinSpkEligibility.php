<?php

namespace App\Support;

use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ResinSpkEligibility
{
    public const PROCESS_KEY = 'Resin';

    /**
     * SPK yang sudah selesai JewelCAD tetapi belum punya dokumen resin.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $builder) => app(JewelCadSpkEligibility::class)->applyCompletedScope($builder))
            ->whereNotIn('row_id', $this->assignedSpkIdsSubquery());
    }

    /**
     * Update proses terakhir SPK ke Resin saat SPK masuk dokumen resin.
     */
    public function markProcessStarted(Production $production, string $actor): Production
    {
        if ($this->alreadyInResinProcess($production)) {
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

    private function alreadyInResinProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * SPK dengan resin berstatus open.
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
     * SPK dengan resin berstatus selesai.
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
     * @return array<int, array{resinId: int, docNo: string|null}>
     */
    public function resinRefsBySpkIds(array $spkIds): array
    {
        if ($spkIds === []) {
            return [];
        }

        $refs = [];

        $detailRows = ResinDetail::query()
            ->join('resin as documents', 'documents.row_id', '=', 'resindetails.row_id')
            ->where('resindetails.is_deleted', 0)
            ->where('documents.is_deleted', 0)
            ->whereIn('resindetails.spk_id', $spkIds)
            ->orderByDesc('documents.row_id')
            ->get([
                'resindetails.spk_id',
                'documents.row_id as resin_id',
                'documents.doc_no',
            ]);

        foreach ($detailRows as $row) {
            $spkId = (int) $row->spk_id;

            if (isset($refs[$spkId])) {
                continue;
            }

            $refs[$spkId] = [
                'resinId' => (int) $row->resin_id,
                'docNo' => filled($row->doc_no) ? (string) $row->doc_no : null,
            ];
        }

        $legacySpkIds = array_values(array_diff(
            $spkIds,
            array_map('intval', array_keys($refs)),
        ));

        if ($legacySpkIds === []) {
            return $refs;
        }

        $legacyRows = Resin::query()
            ->notDeleted()
            ->whereIn('spk_id', $legacySpkIds)
            ->whereDoesntHave('details', fn ($query) => $query->notDeleted())
            ->orderByDesc('row_id')
            ->get(['row_id', 'spk_id', 'doc_no']);

        foreach ($legacyRows as $row) {
            $spkId = (int) $row->spk_id;

            if (isset($refs[$spkId])) {
                continue;
            }

            $refs[$spkId] = [
                'resinId' => (int) $row->row_id,
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
            $detailQuery = DB::connection('third')->table('resindetails')
                ->select('resindetails.spk_id')
                ->join('resin', 'resin.row_id', '=', 'resindetails.row_id')
                ->where('resindetails.is_deleted', 0)
                ->where('resin.is_deleted', 0);

            if ($scope === 'in_progress') {
                $detailQuery->where(function ($query): void {
                    $query->whereNull('resin.status')
                        ->orWhereRaw("TRIM(resin.status) = ''")
                        ->orWhereRaw("UPPER(TRIM(resin.status)) = 'DRAFT'")
                        ->orWhere('resin.status', ResinApprovalService::STATUS_SUBMITTED)
                        ->orWhere('resin.status', ResinApprovalService::STATUS_MANAGER)
                        ->orWhere('resin.status', Resin::STATUS_OPEN);
                });
            }

            if ($scope === 'completed') {
                $detailQuery->whereIn('resin.status', [
                    ResinApprovalService::STATUS_DONE,
                    Resin::STATUS_DONE,
                ]);
            }

            $legacyQuery = DB::connection('third')->table('resin')
                ->select('spk_id')
                ->where('is_deleted', 0)
                ->whereNotNull('spk_id')
                ->whereNotExists(function ($subQuery): void {
                    $subQuery->select(DB::raw(1))
                        ->from('resindetails')
                        ->whereColumn('resindetails.row_id', 'resin.row_id')
                        ->where('resindetails.is_deleted', 0);
                });

            if ($scope === 'in_progress') {
                $legacyQuery->where(function ($query): void {
                    $query->whereNull('status')
                        ->orWhereRaw("TRIM(status) = ''")
                        ->orWhereRaw("UPPER(TRIM(status)) = 'DRAFT'")
                        ->orWhere('status', ResinApprovalService::STATUS_SUBMITTED)
                        ->orWhere('status', ResinApprovalService::STATUS_MANAGER)
                        ->orWhere('status', Resin::STATUS_OPEN);
                });
            }

            if ($scope === 'completed') {
                $legacyQuery->whereIn('status', [
                    ResinApprovalService::STATUS_DONE,
                    Resin::STATUS_DONE,
                ]);
            }

            $outerQuery->select('spk_id')->from(
                $detailQuery->union($legacyQuery),
                'assigned_spks',
            );
        };
    }
}
