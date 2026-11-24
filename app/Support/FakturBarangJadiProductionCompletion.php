<?php

namespace App\Support;

use App\Models\Coran;
use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Models\Resin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FakturBarangJadiProductionCompletion
{
    /**
     * @param  Collection<int, Production>  $productions
     */
    public function completeForFakturIfNeeded(Collection $productions, string $actor): void
    {
        if ($productions->isEmpty()) {
            return;
        }

        foreach ($productions as $production) {
            $this->completeOne($production, $actor);
            $production->refresh();
        }
    }

    private function completeOne(Production $production, string $actor): void
    {
        $spkId = (int) $production->row_id;
        $type = trim((string) ($production->spk_type ?? ''));
        $now = Carbon::now();

        $this->completeUpstreamProcesses($spkId, $type, $actor, $now);

        if (in_array($type, ['Exchange', 'Refund'], true)) {
            $this->ensureDoneProcess(
                'polishfinishedgood',
                $spkId,
                SpkDashboardAnalytics::POLES_BARANG_JADI_REF_DONE_STATUSES,
                [
                    'process_name' => 'Poles Barang Jadi',
                    'doc_no' => $this->docNo('FBJ-RPFDONE', $spkId),
                    'status' => 'RPFDONE',
                ],
                $actor,
                $now,
            );
            $this->updateSpkTerminalState($production, 'Poles Barang Jadi', $actor, $now);

            return;
        }

        $this->ensureDoneProcess(
            'polishframe',
            $spkId,
            SpkDashboardAnalytics::POLES_RANGKA_DONE_STATUSES,
            [
                'doc_no' => $this->docNo('FBJ-PRKDONE', $spkId),
                'status' => 'PRKDONE',
            ],
            $actor,
            $now,
        );

        $this->ensurePasangBatuDone($spkId, $actor, $now);

        $this->ensureDoneProcess(
            'polishfinishedgood',
            $spkId,
            SpkDashboardAnalytics::POLES_CHROME_DONE_STATUSES,
            [
                'process_name' => 'Poles Chrome',
                'doc_no' => $this->docNo('FBJ-PFGDONE', $spkId),
                'status' => 'PFGDONE',
            ],
            $actor,
            $now,
        );

        $this->updateSpkTerminalState($production, 'Poles Chrome', $actor, $now);
    }

    private function completeUpstreamProcesses(int $spkId, string $spkType, string $actor, Carbon $now): void
    {
        $this->ensureParentChildDone(
            'requestjwcaddetails',
            'requestjwcad',
            $spkId,
            [JewelCadApprovalService::STATUS_DONE],
            JewelCadApprovalService::STATUS_DONE,
            'FBJ-JWD',
            $actor,
            $now,
        );

        $this->ensureParentChildDone(
            'resindetails',
            'resin',
            $spkId,
            [Resin::STATUS_DONE],
            Resin::STATUS_DONE,
            'FBJ-RSN',
            $actor,
            $now,
        );

        $this->ensureParentChildDone(
            'coranspk',
            'coran',
            $spkId,
            [Coran::STATUS_DONE],
            Coran::STATUS_DONE,
            'FBJ-COR',
            $actor,
            $now,
        );

        $finishingDone = $this->finishingDoneConfig($spkType);

        $this->ensureDoneProcess(
            'finishinghandmade',
            $spkId,
            $finishingDone['statuses'],
            [
                'process_name' => $finishingDone['process_name'],
                'doc_no' => $this->docNo('FBJ-FHM', $spkId),
                'status' => $finishingDone['status'],
            ],
            $actor,
            $now,
        );
    }

    /**
     * @return array{statuses: list<string>, status: string, process_name: string}
     */
    private function finishingDoneConfig(string $spkType): array
    {
        if (in_array($spkType, ['Reparasi', 'Exchange', 'Refund'], true)) {
            return [
                'statuses' => [FinishingHandmade::STATUS_REPARATION_DONE],
                'status' => FinishingHandmade::STATUS_REPARATION_DONE,
                'process_name' => 'Finishing',
            ];
        }

        return [
            'statuses' => [FinishingHandmade::STATUS_DONE],
            'status' => FinishingHandmade::STATUS_DONE,
            'process_name' => 'Finishing',
        ];
    }

    /**
     * @param  list<string>  $doneStatuses
     */
    private function ensureParentChildDone(
        string $detailTable,
        string $parentTable,
        int $spkId,
        array $doneStatuses,
        string $targetStatus,
        string $docPrefix,
        string $actor,
        Carbon $now,
    ): void {
        if (! Schema::connection('third')->hasTable($detailTable)) {
            return;
        }

        $detailQuery = DB::connection('third')->table($detailTable)->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($detailTable, 'is_deleted')) {
            $detailQuery->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        $detail = $detailQuery->orderByDesc(
            Schema::connection('third')->hasColumn($detailTable, 'line_id') ? 'line_id' : 'row_id',
        )->first();

        if ($detail !== null && Schema::connection('third')->hasTable($parentTable)) {
            $parentId = (int) ($detail->row_id ?? 0);

            if ($parentId > 0) {
                $this->ensureParentDone($parentTable, $parentId, $doneStatuses, $targetStatus, $actor, $now);

                return;
            }
        }

        if (! Schema::connection('third')->hasTable($parentTable)) {
            return;
        }

        $parentId = $this->insertParentHeader(
            $parentTable,
            $targetStatus,
            $this->docNo($docPrefix, $spkId),
            $actor,
            $now,
        );

        if ($parentId <= 0) {
            return;
        }

        $detailPayload = [
            'row_id' => $parentId,
            'spk_id' => $spkId,
        ];

        if (Schema::connection('third')->hasColumn($detailTable, 'is_deleted')) {
            $detailPayload['is_deleted'] = 0;
        }

        if (Schema::connection('third')->hasColumn($detailTable, 'is_from_new_system')) {
            $detailPayload['is_from_new_system'] = 0;
        }

        if (Schema::connection('third')->hasColumn($detailTable, 'created_date')) {
            $detailPayload['created_date'] = $now;
        }

        if (Schema::connection('third')->hasColumn($detailTable, 'created_by')) {
            $detailPayload['created_by'] = $actor;
        }

        if ($detailTable === 'coranspk' && Schema::connection('third')->hasColumn($detailTable, 'status')) {
            $detailPayload['status'] = 'OK';
        }

        DB::connection('third')->table($detailTable)->insert($detailPayload);
    }

    /**
     * @param  list<string>  $doneStatuses
     */
    private function ensureParentDone(
        string $parentTable,
        int $parentId,
        array $doneStatuses,
        string $targetStatus,
        string $actor,
        Carbon $now,
    ): void {
        $parent = DB::connection('third')->table($parentTable)->where('row_id', $parentId)->first();

        if ($parent === null) {
            return;
        }

        $current = strtoupper(trim((string) ($parent->status ?? '')));

        if (in_array($current, $doneStatuses, true)) {
            return;
        }

        $payload = ['status' => $targetStatus];

        if (Schema::connection('third')->hasColumn($parentTable, 'modified_date')) {
            $payload['modified_date'] = $now;
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'modified_by')) {
            $payload['modified_by'] = $actor;
        }

        DB::connection('third')->table($parentTable)->where('row_id', $parentId)->update($payload);
    }

    private function insertParentHeader(
        string $parentTable,
        string $status,
        string $docNo,
        string $actor,
        Carbon $now,
    ): int {
        $payload = [
            'doc_no' => $docNo,
            'status' => $status,
        ];

        if (Schema::connection('third')->hasColumn($parentTable, 'trans_date')) {
            $payload['trans_date'] = $now->toDateString();
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'operator')) {
            $payload['operator'] = $actor;
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'is_from_new_system')) {
            $payload['is_from_new_system'] = 0;
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'is_deleted')) {
            $payload['is_deleted'] = 0;
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'created_date')) {
            $payload['created_date'] = $now;
        }

        if (Schema::connection('third')->hasColumn($parentTable, 'created_by')) {
            $payload['created_by'] = $actor;
        }

        return (int) DB::connection('third')->table($parentTable)->insertGetId($payload, 'row_id');
    }

    private function ensurePasangBatuDone(int $spkId, string $actor, Carbon $now): void
    {
        $defaults = [
            'process_name' => 'Pasang Batu',
            'doc_no' => $this->docNo('FBJ-DMTDONE', $spkId),
            'status' => 'DMTDONE',
        ];

        $this->ensureDoneProcess(
            'diamondmounting',
            $spkId,
            SpkDashboardAnalytics::PASANG_BATU_DONE_STATUSES,
            $defaults,
            $actor,
            $now,
        );

        if ($this->hasActiveProcessRow('diamondunload', $spkId)) {
            $this->ensureDoneProcess(
                'diamondunload',
                $spkId,
                SpkDashboardAnalytics::PASANG_BATU_DONE_STATUSES,
                [
                    'doc_no' => $this->docNo('FBJ-DUL', $spkId),
                    'status' => 'DMTDONE',
                ],
                $actor,
                $now,
            );
        }
    }

    private function hasActiveProcessRow(string $table, int $spkId): bool
    {
        if (! Schema::connection('third')->hasTable($table)) {
            return false;
        }

        $query = DB::connection('third')->table($table)->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        return $query->exists();
    }

    /**
     * @param  list<string>  $doneStatuses
     * @param  array<string, mixed>  $defaults
     */
    private function ensureDoneProcess(
        string $table,
        int $spkId,
        array $doneStatuses,
        array $defaults,
        string $actor,
        Carbon $now,
    ): void {
        if (! Schema::connection('third')->hasTable($table)) {
            return;
        }

        $query = DB::connection('third')->table($table)->where('spk_id', $spkId);

        if (Schema::connection('third')->hasColumn($table, 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        $existing = $query->orderByDesc('row_id')->first();

        if ($existing !== null) {
            if (Schema::connection('third')->hasColumn($table, 'status')) {
                $currentStatus = strtoupper(trim((string) ($existing->status ?? '')));

                if (in_array($currentStatus, $doneStatuses, true)) {
                    return;
                }

                $payload = [
                    'status' => (string) ($defaults['status'] ?? $doneStatuses[0]),
                ];

                if (Schema::connection('third')->hasColumn($table, 'modified_date')) {
                    $payload['modified_date'] = $now;
                }

                if (Schema::connection('third')->hasColumn($table, 'modified_by')) {
                    $payload['modified_by'] = $actor;
                }

                DB::connection('third')->table($table)->where('row_id', $existing->row_id)->update($payload);
            }

            return;
        }

        $payload = $this->filterInsertPayload($table, [
            ...$defaults,
            'spk_id' => $spkId,
            'is_deleted' => 0,
            'is_from_new_system' => 0,
            'created_date' => $now,
            'created_by' => $actor,
        ]);

        if ($payload === []) {
            return;
        }

        DB::connection('third')->table($table)->insert($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterInsertPayload(string $table, array $payload): array
    {
        return array_filter(
            $payload,
            fn (mixed $value, string $column): bool => Schema::connection('third')->hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function updateSpkTerminalState(
        Production $production,
        string $lastProcess,
        string $actor,
        Carbon $now,
    ): void {
        $payload = [
            'last_process' => $lastProcess,
        ];

        if (Schema::connection('third')->hasColumn('spk', 'is_inprocess')) {
            $payload['is_inprocess'] = 0;
        }

        if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
            $payload['modified_date'] = $now;
        }

        if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
            $payload['modified_by'] = $actor;
        }

        $production->update($payload);
        $production->setAttribute('last_process', $lastProcess);
    }

    private function docNo(string $prefix, int $spkId): string
    {
        return sprintf('%s-%d-%s', $prefix, $spkId, Str::lower(Str::random(6)));
    }
}
