<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FakturBarangJadiReceiptService
{
    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi');
    }

    public function openReceiptCount(): int
    {
        if (! $this->tableAvailable()) {
            return 0;
        }

        return SerahTerimaFakturBarangJadi::query()->count();
    }

    /**
     * @return array{issued: array<int, true>, statuses: array<int, string>}
     */
    public function spkRowFakturWorkflowMaps(): array
    {
        if (! $this->tableAvailable()) {
            return ['issued' => [], 'statuses' => []];
        }

        $issued = [];
        $statuses = [];
        $columns = ['spk_row_ids'];

        if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
            $columns[] = 'workflow_status';
        }

        SerahTerimaFakturBarangJadi::query()
            ->orderByDesc('id')
            ->get($columns)
            ->each(function (SerahTerimaFakturBarangJadi $receipt) use (&$issued, &$statuses): void {
                $workflow = FakturBarangJadiWorkflowStatus::normalize(
                    FakturBarangJadiWorkflowStatus::columnAvailable()
                        ? $receipt->workflow_status
                        : FakturBarangJadiWorkflowStatus::CREATED,
                );

                foreach ($receipt->spk_row_ids ?? [] as $rowId) {
                    $rowId = (int) $rowId;

                    if ($rowId <= 0) {
                        continue;
                    }

                    $issued[$rowId] = true;

                    if (! isset($statuses[$rowId])) {
                        $statuses[$rowId] = $workflow;
                    }
                }
            });

        return ['issued' => $issued, 'statuses' => $statuses];
    }

    /**
     * @return array<int, true>
     */
    public function spkRowIdsOnOpenReceipts(): array
    {
        return $this->spkRowFakturWorkflowMaps()['issued'];
    }

    /**
     * @return list<int>
     */
    public function spkRowIdsWithLatestWorkflowStatus(string $status): array
    {
        if (! $this->tableAvailable() || ! FakturBarangJadiWorkflowStatus::columnAvailable()) {
            return [];
        }

        $target = FakturBarangJadiWorkflowStatus::normalize($status);
        $statuses = $this->spkRowFakturWorkflowMaps()['statuses'];

        return array_values(array_map(
            intval(...),
            array_keys(array_filter(
                $statuses,
                fn (string $value): bool => $value === $target,
            )),
        ));
    }

    /**
     * @param  list<int>  $rowIds
     * @return array<int, array{
     *     receiptId: int,
     *     docNo: string,
     *     tanggal: string,
     *     dari: string|null,
     *     untuk: string|null,
     *     workflowStatus: string,
     *     workflowStatusLabel: string,
     * }>
     */
    public function latestFakturDetailsBySpkRowIds(array $rowIds): array
    {
        if (! $this->tableAvailable() || $rowIds === []) {
            return [];
        }

        /** @var array<int, true> $wanted */
        $wanted = array_fill_keys(array_map(intval(...), $rowIds), true);
        $details = [];
        $columns = ['id', 'doc_no', 'tanggal', 'dari', 'untuk', 'spk_row_ids'];

        if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
            $columns[] = 'workflow_status';
        }

        SerahTerimaFakturBarangJadi::query()
            ->orderByDesc('id')
            ->get($columns)
            ->each(function (SerahTerimaFakturBarangJadi $receipt) use (&$details, $wanted): void {
                $workflow = FakturBarangJadiWorkflowStatus::normalize(
                    FakturBarangJadiWorkflowStatus::columnAvailable()
                        ? $receipt->workflow_status
                        : FakturBarangJadiWorkflowStatus::CREATED,
                );

                foreach ($receipt->spk_row_ids ?? [] as $rowId) {
                    $rowId = (int) $rowId;

                    if ($rowId <= 0 || ! isset($wanted[$rowId]) || isset($details[$rowId])) {
                        continue;
                    }

                    $details[$rowId] = [
                        'receiptId' => (int) $receipt->id,
                        'docNo' => $receipt->doc_no,
                        'tanggal' => $receipt->tanggal?->format('d-M-Y') ?? '-',
                        'dari' => $receipt->dari,
                        'untuk' => $receipt->untuk,
                        'workflowStatus' => $workflow,
                        'workflowStatusLabel' => FakturBarangJadiWorkflowStatus::label($workflow),
                    ];
                }
            });

        return $details;
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function assertEligibleForReceipt(
        Collection $productions,
        ?string $fromLocation,
        string $actor = 'system',
    ): void {
        app(FakturBarangJadiProductionCompletion::class)
            ->completeForFakturIfNeeded($productions, $actor);

        $blockedIds = $this->spkRowIdsOnOpenReceipts();
        $from = trim((string) ($fromLocation ?? Production::DEFAULT_LOCATION));

        foreach ($productions as $production) {
            $rowId = (int) $production->row_id;

            if (SpkDashboardAnalytics::completedProductionKind($rowId) === null) {
                throw new HttpException(
                    422,
                    "SPK {$production->spk_no} belum dapat ditandai selesai untuk faktur barang jadi.",
                );
            }

            if ($production->spk_type === SpkService::REPARATION_TYPE) {
                throw new HttpException(
                    422,
                    "SPK {$production->spk_no} bertipe Reparasi tidak bisa difakturkan di sini.",
                );
            }

            if (isset($blockedIds[$rowId])) {
                throw new HttpException(
                    422,
                    "SPK {$production->spk_no} sudah tercantum pada faktur barang jadi aktif.",
                );
            }

            if (
                Schema::connection('third')->hasColumn('spk', 'foto_brj')
                && blank($production->foto_brj)
            ) {
                throw new HttpException(
                    422,
                    "SPK {$production->spk_no} wajib memiliki foto barang jadi.",
                );
            }

            $currentLocation = trim((string) ($production->location ?? Production::DEFAULT_LOCATION));

            if ($from !== '' && strcasecmp($currentLocation, $from) !== 0) {
                throw new HttpException(
                    422,
                    "SPK {$production->spk_no} tidak berada di lokasi {$from}.",
                );
            }
        }
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function relocateProductionsTo(
        Collection $productions,
        ?string $toLocation,
        string $actor,
    ): void {
        $to = trim((string) $toLocation);

        if ($to === '' || ! Schema::connection('third')->hasColumn('spk', 'location')) {
            return;
        }

        $now = now();

        foreach ($productions as $production) {
            $payload = [
                'location' => $to,
            ];

            if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
                $payload['modified_date'] = $now;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
                $payload['modified_by'] = $actor;
            }

            $production->update($payload);
        }
    }

    /**
     * @return array{
     *     spkRowId: int,
     *     spkNo: string,
     *     type: string,
     *     status: string,
     *     item: string,
     *     plu: string|null,
     *     location: string
     * }
     */
    public function receiptItem(Production $production): array
    {
        $kind = SpkDashboardAnalytics::completedProductionKind((int) $production->row_id);

        return [
            'spkRowId' => (int) $production->row_id,
            'spkNo' => filled($production->spk_no) ? (string) $production->spk_no : '-',
            'type' => filled($production->spk_type) ? (string) $production->spk_type : '-',
            'status' => $kind !== null
                ? (SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$kind] ?? SpkDashboardAnalytics::backlogStatusLabel($production, $kind))
                : SpkDashboardAnalytics::backlogStatusLabel($production, true),
            'item' => filled($production->item_name) ? (string) $production->item_name : '-',
            'plu' => filled($production->plu) ? trim((string) $production->plu) : null,
            'location' => filled($production->location)
                ? trim((string) $production->location)
                : Production::DEFAULT_LOCATION,
        ];
    }

    public function applyCompletedScope(Builder $query): void
    {
        (new SpkDashboardAnalytics)->constrainToCompletedProduction($query);
    }

    /**
     * @param  array{
     *     doc_no: string|null,
     *     tanggal: string,
     *     dari: string,
     *     untuk: string,
     *     workflow: string,
     *     diserahkan_oleh: string|null,
     *     diketahui_oleh: string|null,
     *     diterima_oleh: string|null,
     *     productions: Collection<int, Production>,
     *     plu_by_row_id?: array<int, string>,
     * }  $group
     */
    public function createReceiptFromImport(
        array $group,
        string $actor,
        SerahTerimaFakturBarangJadiDocNumberGenerator $docNumberGenerator,
    ): SerahTerimaFakturBarangJadi {
        $receiptDate = Carbon::createFromFormat('Y-m-d', $group['tanggal'])->startOfDay();

        app(FakturBarangJadiProductionCompletion::class)
            ->completeForFakturIfNeeded($group['productions'], $actor);

        /** @var array<int, string> $pluByRowId */
        $pluByRowId = $group['plu_by_row_id'] ?? [];

        $this->syncPluFromImport($group['productions'], $pluByRowId, $actor);
        $this->relocateProductionsTo($group['productions'], $group['untuk'], $actor);
        $group['productions']->each->refresh();

        $items = $group['productions']
            ->map(fn (Production $production): array => $this->receiptItem($production))
            ->all();

        $docNo = filled($group['doc_no'])
            ? (string) $group['doc_no']
            : $docNumberGenerator->generate($receiptDate, $group['dari'], $group['untuk']);

        $workflow = FakturBarangJadiWorkflowStatus::normalize($group['workflow']);

        $payload = [
            'doc_no' => $docNo,
            'tanggal' => $receiptDate->toDateString(),
            'dari' => $group['dari'],
            'untuk' => $group['untuk'],
            'diserahkan_oleh' => $group['diserahkan_oleh'],
            'diterima_oleh' => $workflow === FakturBarangJadiWorkflowStatus::RECEIVED
                ? $group['diterima_oleh']
                : null,
            'diketahui_oleh' => $group['diketahui_oleh'],
            'jumlah_spk' => count($items),
            'spk_row_ids' => array_column($items, 'spkRowId'),
            'items' => $items,
            'created_by' => $actor,
        ];

        if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
            $payload['workflow_status'] = $workflow;
        }

        $receipt = SerahTerimaFakturBarangJadi::query()->create($payload);

        $this->syncSendToMerchDates($group['productions'], $receiptDate, $actor);

        return $receipt;
    }

    /**
     * @param  Collection<int, Production>  $productions
     * @param  array<int, string>  $pluByRowId
     */
    public function syncPluFromImport(
        Collection $productions,
        array $pluByRowId,
        string $actor,
    ): void {
        if ($pluByRowId === [] || ! Schema::connection('third')->hasColumn('spk', 'plu')) {
            return;
        }

        $now = now();

        foreach ($productions as $production) {
            $rowId = (int) $production->row_id;
            $plu = $pluByRowId[$rowId] ?? null;

            if (! filled($plu)) {
                continue;
            }

            $payload = [
                'plu' => trim((string) $plu),
            ];

            if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
                $payload['modified_date'] = $now;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
                $payload['modified_by'] = $actor;
            }

            $production->update($payload);
            $production->setAttribute('plu', $payload['plu']);
        }
    }

    /**
     * @param  Collection<int, Production>|iterable<int, Production>  $productions
     */
    public function syncSendToMerchDates(
        iterable $productions,
        Carbon|string $date,
        string $actor,
    ): void {
        if (! Schema::connection('third')->hasColumn('spk', 'send_to_merch_date')) {
            return;
        }

        $dateString = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        $now = now();

        foreach ($productions as $production) {
            $payload = [
                'send_to_merch_date' => $dateString,
            ];

            if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
                $payload['modified_date'] = $now;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
                $payload['modified_by'] = $actor;
            }

            $production->update($payload);
        }
    }
}
