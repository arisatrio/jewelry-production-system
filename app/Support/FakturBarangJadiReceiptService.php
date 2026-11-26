<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
    public function spkRowFakturWorkflowMaps(?int $exceptReceiptId = null): array
    {
        if (! $this->tableAvailable()) {
            return ['issued' => [], 'statuses' => []];
        }

        $issued = [];
        $statuses = [];
        $columns = ['id', 'spk_row_ids'];

        if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
            $columns[] = 'workflow_status';
        }

        SerahTerimaFakturBarangJadi::query()
            ->orderByDesc('id')
            ->get($columns)
            ->each(function (SerahTerimaFakturBarangJadi $receipt) use (&$issued, &$statuses, $exceptReceiptId): void {
                if ($exceptReceiptId !== null && (int) $receipt->id === $exceptReceiptId) {
                    return;
                }
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
    public function spkRowIdsOnOpenReceipts(?int $exceptReceiptId = null): array
    {
        return $this->spkRowFakturWorkflowMaps($exceptReceiptId)['issued'];
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
        ?int $exceptReceiptId = null,
    ): void {
        app(FakturBarangJadiProductionCompletion::class)
            ->completeForFakturIfNeeded($productions, $actor);

        $blockedIds = $this->spkRowIdsOnOpenReceipts($exceptReceiptId);
        $from = trim((string) ($fromLocation ?? Production::DEFAULT_LOCATION));

        foreach ($productions as $production) {
            $rowId = (int) $production->row_id;

            if (SpkDashboardAnalytics::completedProductionKind($rowId) === null) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} belum dapat ditandai selesai untuk faktur barang jadi.",
                    ],
                ]);
            }

            if ($production->spk_type === SpkService::REPARATION_TYPE) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} bertipe Reparasi tidak bisa difakturkan di sini.",
                    ],
                ]);
            }

            if (isset($blockedIds[$rowId])) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} sudah tercantum pada faktur barang jadi aktif.",
                    ],
                ]);
            }

            if (
                Schema::connection('third')->hasColumn('spk', 'foto_brj')
                && blank($production->foto_brj)
            ) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} wajib memiliki foto barang jadi.",
                    ],
                ]);
            }

            $currentLocation = trim((string) ($production->location ?? Production::DEFAULT_LOCATION));

            if ($from !== '' && strcasecmp($currentLocation, $from) !== 0) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} tidak berada di lokasi {$from}.",
                    ],
                ]);
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
     *     last_weight_by_row_id?: array<int, string>,
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

        /** @var array<int, string> $lastWeightByRowId */
        $lastWeightByRowId = $group['last_weight_by_row_id'] ?? [];

        $this->syncLastWeightFromImport($group['productions'], $lastWeightByRowId, $actor);
        $this->relocateProductionsTo($group['productions'], $group['untuk'], $actor);
        $group['productions']->each->refresh();

        app(InventorySpkSync::class)->syncSpkNumbers($group['productions'], $actor);

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
     * @param  Collection<int, Production>  $productions
     * @param  array<int, string>  $lastWeightByRowId
     */
    public function syncLastWeightFromImport(
        Collection $productions,
        array $lastWeightByRowId,
        string $actor,
    ): void {
        if ($lastWeightByRowId === [] || ! Schema::connection('third')->hasColumn('spk', 'last_weight')) {
            return;
        }

        $now = now();

        foreach ($productions as $production) {
            $rowId = (int) $production->row_id;
            $lastWeight = $lastWeightByRowId[$rowId] ?? null;

            if (! filled($lastWeight)) {
                continue;
            }

            $payload = [
                'last_weight' => number_format((float) $lastWeight, 2, '.', ''),
            ];

            if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
                $payload['modified_date'] = $now;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
                $payload['modified_by'] = $actor;
            }

            $production->update($payload);
            $production->setAttribute('last_weight', (float) $payload['last_weight']);
        }
    }

    /**
     * @param  Collection<int, Production>|iterable<int, Production>  $productions
     */
    public function assertReceiptAllowsSpkChanges(SerahTerimaFakturBarangJadi $receipt): void
    {
        if (FakturBarangJadiWorkflowStatus::allowsSpkChanges($receipt->workflow_status)) {
            return;
        }

        throw ValidationException::withMessages([
            'receipt' => ['Daftar SPK tidak dapat diubah pada faktur ini.'],
        ]);
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function attachProductionsToReceipt(
        SerahTerimaFakturBarangJadi $receipt,
        Collection $productions,
        string $actor,
    ): void {
        $this->assertReceiptAllowsSpkChanges($receipt);

        /** @var array<int, true> $existingIds */
        $existingIds = array_fill_keys(
            array_map(intval(...), $receipt->spk_row_ids ?? []),
            true,
        );

        foreach ($productions as $production) {
            $rowId = (int) $production->row_id;

            if (isset($existingIds[$rowId])) {
                throw ValidationException::withMessages([
                    'ids' => [
                        "SPK {$production->spk_no} sudah tercantum pada faktur ini.",
                    ],
                ]);
            }
        }

        $this->assertEligibleForReceipt(
            $productions,
            $receipt->dari,
            $actor,
            (int) $receipt->id,
        );

        $this->relocateProductionsTo($productions, $receipt->untuk, $actor);
        $productions->each->refresh();

        $newItems = $productions
            ->map(fn (Production $production): array => $this->receiptItem($production))
            ->all();

        /** @var list<array<string, mixed>> $mergedItems */
        $mergedItems = [...($receipt->items ?? []), ...$newItems];

        $receipt->forceFill([
            'items' => $mergedItems,
            'spk_row_ids' => array_column($mergedItems, 'spkRowId'),
            'jumlah_spk' => count($mergedItems),
        ])->save();

        $this->syncSendToMerchDates($productions, $receipt->tanggal, $actor);
    }

    public function detachProductionFromReceipt(
        SerahTerimaFakturBarangJadi $receipt,
        Production $production,
        string $actor,
    ): void {
        $this->assertReceiptAllowsSpkChanges($receipt);

        $rowId = (int) $production->row_id;
        /** @var list<int> $currentIds */
        $currentIds = array_values(array_filter(
            array_map(intval(...), $receipt->spk_row_ids ?? []),
            fn (int $id): bool => $id > 0,
        ));

        if (! in_array($rowId, $currentIds, true)) {
            throw ValidationException::withMessages([
                'spk' => ['SPK tidak tercantum pada faktur ini.'],
            ]);
        }

        if (count($currentIds) <= 1) {
            throw ValidationException::withMessages([
                'spk' => ['Faktur harus memuat minimal satu SPK.'],
            ]);
        }

        /** @var list<array<string, mixed>> $remainingItems */
        $remainingItems = collect($receipt->items ?? [])
            ->reject(fn (array $item): bool => (int) ($item['spkRowId'] ?? 0) === $rowId)
            ->values()
            ->all();

        $receipt->forceFill([
            'items' => $remainingItems,
            'spk_row_ids' => array_column($remainingItems, 'spkRowId'),
            'jumlah_spk' => count($remainingItems),
        ])->save();

        $productions = collect([$production]);

        app(InventorySpkSync::class)->clearSpkNumbers($productions, $actor);
        $this->clearPluAndSendToMerchDate($productions, $actor);
        $this->relocateProductionsTo($productions, $receipt->dari, $actor);
    }

    /**
     * @param  Collection<int, Production>  $productions
     */
    public function clearPluAndSendToMerchDate(Collection $productions, string $actor): void
    {
        $hasPlu = Schema::connection('third')->hasColumn('spk', 'plu');
        $hasSendToMerchDate = Schema::connection('third')->hasColumn('spk', 'send_to_merch_date');

        if (! $hasPlu && ! $hasSendToMerchDate) {
            return;
        }

        $now = now();

        foreach ($productions as $production) {
            $payload = [];

            if ($hasPlu) {
                $payload['plu'] = null;
            }

            if ($hasSendToMerchDate) {
                $payload['send_to_merch_date'] = null;
            }

            if ($payload === []) {
                continue;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_date')) {
                $payload['modified_date'] = $now;
            }

            if (Schema::connection('third')->hasColumn('spk', 'modified_by')) {
                $payload['modified_by'] = $actor;
            }

            $production->update($payload);
        }
    }

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
