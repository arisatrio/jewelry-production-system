<?php

namespace App\Support;

use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;

class JewelCadSpkEligibility
{
    public const PROCESS_KEY = 'JewelCAD';

    /**
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyEligibleScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->where('status', SpkApprovalService::STATUS_DONE)
            ->where('is_inprocess', 0)
            ->where(function (Builder $innerQuery): void {
                $innerQuery->whereNull('last_process')
                    ->orWhereRaw("TRIM(last_process) = ''");
            });
    }

    public function isEligible(Production $production): bool
    {
        if ($production->is_deleted !== 0 || blank($production->spk_no)) {
            return false;
        }

        if (strtoupper(trim((string) $production->status)) !== SpkApprovalService::STATUS_DONE) {
            return false;
        }

        if ((int) ($production->is_inprocess ?? 0) !== 0) {
            return false;
        }

        return blank($production->last_process);
    }

    public function assertEligible(Production $production): void
    {
        if ($this->isEligible($production)) {
            return;
        }

        throw new InvalidArgumentException(
            'SPK harus sudah di-approve Manager Produksi dan belum masuk proses produksi.',
        );
    }

    public function markProcessStarted(Production $production, string $actor): Production
    {
        if ($this->alreadyInJewelCadProcess($production)) {
            return $production;
        }

        $this->assertEligible($production);

        $production->update([
            'last_process' => self::PROCESS_KEY,
            'is_inprocess' => 1,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        return $production->refresh();
    }

    /**
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applySelectableScope(Builder $query, ?int $jewelCadRequestId = null): Builder
    {
        return $query->where(function (Builder $innerQuery) use ($jewelCadRequestId): void {
            $this->applyEligibleScope($innerQuery);

            if ($jewelCadRequestId !== null) {
                $innerQuery->orWhereIn('row_id', function ($subQuery) use ($jewelCadRequestId): void {
                    $subQuery->select('spk_id')
                        ->from('requestjwcaddetails')
                        ->where('row_id', $jewelCadRequestId)
                        ->where('is_deleted', 0);
                });
            }
        });
    }

    /**
     * SPK yang sudah masuk request JewelCAD tetapi belum selesai.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyInProgressScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->whereIn('row_id', $this->detailSpkIdsForRequestStatuses(completed: false));
    }

    /**
     * SPK pada request JewelCAD berstatus selesai.
     *
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function applyCompletedScope(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', 0)
            ->whereNotNull('spk_no')
            ->whereIn('row_id', $this->detailSpkIdsForRequestStatuses(completed: true));
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, array{requestId: int, docNo: string|null}>
     */
    public function requestRefsBySpkIds(array $spkIds, bool $completed): array
    {
        if ($spkIds === []) {
            return [];
        }

        $rows = JewelCadRequestDetail::query()
            ->join('requestjwcad as requests', 'requests.row_id', '=', 'requestjwcaddetails.row_id')
            ->whereIn('requestjwcaddetails.spk_id', $spkIds)
            ->where('requestjwcaddetails.is_deleted', 0)
            ->where('requests.is_deleted', 0)
            ->when(
                $completed,
                fn ($query) => $query->where(
                    'requests.status',
                    JewelCadApprovalService::STATUS_DONE,
                ),
                fn ($query) => $query->where(function ($statusQuery): void {
                    $statusQuery->whereNull('requests.status')
                        ->orWhereRaw("TRIM(requests.status) = ''")
                        ->orWhereRaw("UPPER(TRIM(requests.status)) = 'DRAFT'")
                        ->orWhere('requests.status', JewelCadApprovalService::STATUS_SUBMITTED)
                        ->orWhere('requests.status', JewelCadApprovalService::STATUS_MANAGER);
                }),
            )
            ->orderByDesc('requests.row_id')
            ->get([
                'requestjwcaddetails.spk_id',
                'requests.row_id as request_id',
                'requests.doc_no',
            ]);

        $refs = [];

        foreach ($rows as $row) {
            $spkId = (int) $row->spk_id;

            if (isset($refs[$spkId])) {
                continue;
            }

            $refs[$spkId] = [
                'requestId' => (int) $row->request_id,
                'docNo' => filled($row->doc_no) ? (string) $row->doc_no : null,
            ];
        }

        return $refs;
    }

    private function alreadyInJewelCadProcess(Production $production): bool
    {
        return strcasecmp(trim((string) $production->last_process), self::PROCESS_KEY) === 0
            && (int) ($production->is_inprocess ?? 0) === 1;
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private function detailSpkIdsForRequestStatuses(bool $completed): \Closure
    {
        return function ($subQuery) use ($completed): void {
            $subQuery->select('details.spk_id')
                ->from('requestjwcaddetails as details')
                ->join('requestjwcad as requests', 'requests.row_id', '=', 'details.row_id')
                ->where('details.is_deleted', 0)
                ->where('requests.is_deleted', 0);

            if ($completed) {
                $subQuery->where(
                    'requests.status',
                    JewelCadApprovalService::STATUS_DONE,
                );
            } else {
                $subQuery->where(function ($statusQuery): void {
                    $statusQuery->whereNull('requests.status')
                        ->orWhereRaw("TRIM(requests.status) = ''")
                        ->orWhereRaw("UPPER(TRIM(requests.status)) = 'DRAFT'")
                        ->orWhere('requests.status', JewelCadApprovalService::STATUS_SUBMITTED)
                        ->orWhere('requests.status', JewelCadApprovalService::STATUS_MANAGER);
                });
            }
        };
    }
}
