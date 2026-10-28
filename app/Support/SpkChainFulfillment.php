<?php

namespace App\Support;

use App\Models\SpkChain;
use App\Models\TrxChain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkChainFulfillment
{
    public const NOT_ALLOCATED = 'not_allocated';

    public const ALLOCATED = 'allocated';

    public const SENT_TO_WORKSHOP = 'sent_to_workshop';

    public const RECEIVED_BY_WORKSHOP = 'received_by_workshop';

    public const INSTALLED = 'installed';

    public const UNLINKED = 'unlinked';

    public const ACTION_ALLOCATE = 'allocate';

    public const ACTION_SEND_TO_WORKSHOP = 'send_to_workshop';

    public const ACTION_RECEIVE_WORKSHOP = 'receive_workshop';

    public const ACTION_MARK_INSTALLED = 'mark_installed';

    /**
     * @return list<string>
     */
    public static function orderedStatuses(): array
    {
        return [
            self::NOT_ALLOCATED,
            self::ALLOCATED,
            self::SENT_TO_WORKSHOP,
            self::RECEIVED_BY_WORKSHOP,
            self::INSTALLED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::NOT_ALLOCATED => 'Belum Dialokasikan',
            self::ALLOCATED => 'Sudah Dialokasikan',
            self::SENT_TO_WORKSHOP => 'Dikirim ke Workshop',
            self::RECEIVED_BY_WORKSHOP => 'Diterima Workshop',
            self::INSTALLED => 'Terpasang',
            self::UNLINKED => 'Master belum terhubung',
            default => '—',
        };
    }

    public function __construct(
        private readonly ChainStockService $chainStock,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function resolve(SpkChain $chain): array
    {
        if ($chain->mschain_id === null) {
            return $this->payload(self::UNLINKED, 0, null);
        }

        $allocatedUnits = $this->allocatedUnits($chain);
        $stored = $this->normalizeStoredStatus($chain->fulfillment_status);
        $status = $this->effectiveStatus($stored, $allocatedUnits);

        if ($this->columnAvailable() && $status !== $stored) {
            $this->persistStatus($chain, $status);
        }

        return $this->payload(
            $status,
            $allocatedUnits,
            $this->nextAction($status, $allocatedUnits, $chain),
        );
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function advance(SpkChain $chain, string $action, string $actor): array
    {
        abort_if($chain->is_deleted === 1, 404);

        $current = $this->resolve($chain);
        $expected = $current['nextAction'];

        if ($expected === null || $action !== $expected) {
            throw new HttpException(422, 'Langkah status tidak valid.');
        }

        $nextStatus = match ($action) {
            self::ACTION_SEND_TO_WORKSHOP => self::SENT_TO_WORKSHOP,
            self::ACTION_RECEIVE_WORKSHOP => self::RECEIVED_BY_WORKSHOP,
            self::ACTION_MARK_INSTALLED => self::INSTALLED,
            default => throw new HttpException(422, 'Aksi tidak dikenali.'),
        };

        $this->persistStatus($chain, $nextStatus, $actor);

        if ($action === self::ACTION_RECEIVE_WORKSHOP) {
            $this->recordWorkshopReceived($chain, $actor);
        }

        return $this->resolve($chain->refresh());
    }

    /**
     * @param  list<int>  $lineIds
     */
    public function markSentToWorkshopForChainLines(int $spkRowId, array $lineIds, string $actor): void
    {
        if (! $this->columnAvailable()) {
            return;
        }

        foreach ($this->resolveSendToWorkshopLineIds($spkRowId, $lineIds) as $lineId) {
            $chain = SpkChain::query()
                ->notDeleted()
                ->where('row_id', $spkRowId)
                ->where('line_id', $lineId)
                ->first();

            if ($chain === null) {
                continue;
            }

            $resolved = $this->resolve($chain);

            if ($resolved['nextAction'] !== self::ACTION_SEND_TO_WORKSHOP) {
                continue;
            }

            $this->advance($chain->refresh(), self::ACTION_SEND_TO_WORKSHOP, $actor);
        }
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<int>
     */
    private function resolveSendToWorkshopLineIds(int $spkRowId, array $lineIds): array
    {
        $chains = SpkChain::query()
            ->notDeleted()
            ->where('row_id', $spkRowId)
            ->orderBy('line_id')
            ->get();

        if ($chains->isEmpty()) {
            return [];
        }

        $requested = array_values(array_unique(array_filter(array_map(
            intval(...),
            $lineIds,
        ), fn (int $id): bool => $id > 0)));

        $matched = $chains->whereIn('line_id', $requested)->values();

        if ($requested !== [] && $matched->isNotEmpty()) {
            return $this->filterLineIdsReadyToSend($matched);
        }

        return $this->filterLineIdsReadyToSend($chains);
    }

    /**
     * @param  Collection<int, SpkChain>  $chains
     * @return list<int>
     */
    private function filterLineIdsReadyToSend(Collection $chains): array
    {
        $lineIds = [];

        foreach ($chains as $chain) {
            $resolved = $this->resolve($chain);

            if ($resolved['nextAction'] !== self::ACTION_SEND_TO_WORKSHOP) {
                continue;
            }

            $lineIds[] = (int) $chain->line_id;
        }

        return $lineIds;
    }

    public function markAllocatedForSpkMaster(int $spkId, int $msChainId, string $actor): void
    {
        if (! $this->columnAvailable()) {
            return;
        }

        $now = now();

        SpkChain::query()
            ->notDeleted()
            ->where('row_id', $spkId)
            ->where('mschain_id', $msChainId)
            ->where(function ($query): void {
                $query->whereNull('fulfillment_status')
                    ->orWhere('fulfillment_status', self::NOT_ALLOCATED);
            })
            ->update([
                'fulfillment_status' => self::ALLOCATED,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
    }

    public function markAllocatedForSpkChainLine(int $lineId, int $msChainId, string $actor): void
    {
        if (! $this->columnAvailable()) {
            return;
        }

        $now = now();

        SpkChain::query()
            ->notDeleted()
            ->where('line_id', $lineId)
            ->where(function ($query): void {
                $query->whereNull('fulfillment_status')
                    ->orWhere('fulfillment_status', self::NOT_ALLOCATED);
            })
            ->update([
                'mschain_id' => $msChainId,
                'fulfillment_status' => self::ALLOCATED,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
    }

    public function syncSpkChainAllocation(int $lineId, int $msChainId, string $actor): void
    {
        if (! $this->columnAvailable()) {
            return;
        }

        $chain = SpkChain::query()
            ->notDeleted()
            ->where('line_id', $lineId)
            ->first();

        if ($chain === null) {
            return;
        }

        $status = $this->normalizeStoredStatus($chain->fulfillment_status);

        if (in_array($status, [
            self::SENT_TO_WORKSHOP,
            self::RECEIVED_BY_WORKSHOP,
            self::INSTALLED,
        ], true)) {
            throw new HttpException(422, 'Alokasi rantai tidak dapat diubah pada status ini.');
        }

        $chain->update([
            'mschain_id' => $msChainId,
            'fulfillment_status' => self::ALLOCATED,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);
    }

    /**
     * Batalkan alokasi unit chain ke baris SPK (kembalikan stok).
     */
    public function revokeAllocation(SpkChain $chain, string $actor): void
    {
        abort_if($chain->is_deleted === 1, 404);

        $status = $this->normalizeStoredStatus($chain->fulfillment_status);

        if ($status !== self::ALLOCATED) {
            throw new HttpException(422, 'Hanya alokasi yang belum dikirim ke workshop yang dapat dibatalkan.');
        }

        $this->returnIssuedUnitForSpkChain($chain, $actor);
        $this->persistStatus($chain, self::NOT_ALLOCATED, $actor);
    }

    /**
     * Kembalikan unit terbit ke stok saat ganti alokasi (tanpa ubah status fulfillment).
     */
    public function revokeAllocationIfAllocated(SpkChain $chain, string $actor, bool $replace = false): void
    {
        if ($chain->mschain_id === null) {
            return;
        }

        $status = $this->normalizeStoredStatus($chain->fulfillment_status);

        if ($this->allocatedUnits($chain) === 0) {
            return;
        }

        if (! $replace && $status !== self::ALLOCATED) {
            throw new HttpException(422, 'Alokasi rantai tidak dapat dibatalkan pada status ini.');
        }

        if ($replace && in_array($status, [
            self::SENT_TO_WORKSHOP,
            self::RECEIVED_BY_WORKSHOP,
            self::INSTALLED,
        ], true)) {
            throw new HttpException(422, 'Alokasi rantai tidak dapat diubah pada status ini.');
        }

        $this->returnIssuedUnitForSpkChain($chain, $actor);

        if (! $replace) {
            $this->persistStatus($chain, self::NOT_ALLOCATED, $actor);
        }
    }

    private function returnIssuedUnitForSpkChain(SpkChain $chain, string $actor): void
    {
        if ($chain->mschain_id === null || ! $this->chainStock->tableAvailable()) {
            return;
        }

        $unit = $this->chainStock->latestIssuedUnitForSpkChain(
            (int) $chain->row_id,
            (int) $chain->mschain_id,
        );

        if ($unit === null) {
            return;
        }

        $this->chainStock->returnUnitToStock($unit, $actor);
    }

    private function allocatedUnits(SpkChain $chain): int
    {
        if (! $this->chainStock->tableAvailable()) {
            return 0;
        }

        return TrxChain::query()
            ->notDeleted()
            ->where('spk_id', $chain->row_id)
            ->where('mschain_id', $chain->mschain_id)
            ->whereNotNull('out_date')
            ->count();
    }

    private function normalizeStoredStatus(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';

        if ($status === '' || ! in_array($status, self::orderedStatuses(), true)) {
            return self::NOT_ALLOCATED;
        }

        return $status;
    }

    private function effectiveStatus(string $stored, int $allocatedUnits): string
    {
        if ($allocatedUnits === 0) {
            return self::NOT_ALLOCATED;
        }

        if ($stored === self::NOT_ALLOCATED) {
            return self::ALLOCATED;
        }

        return $stored;
    }

    private function canOfferAllocation(SpkChain $chain): bool
    {
        if ($chain->mschain_id === null) {
            return false;
        }

        return $this->chainStock->tableAvailable();
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    private function payload(string $status, int $allocatedUnits, ?string $nextAction): array
    {
        return [
            'status' => $status,
            'label' => self::label($status),
            'allocatedUnits' => $allocatedUnits,
            'nextAction' => $nextAction,
            'nextActionLabel' => $this->nextActionLabel($nextAction),
        ];
    }

    private function nextAction(string $status, int $allocatedUnits, SpkChain $chain): ?string
    {
        if ($status === self::UNLINKED) {
            return null;
        }

        if ($status === self::NOT_ALLOCATED || $allocatedUnits === 0) {
            return $this->canOfferAllocation($chain) ? self::ACTION_ALLOCATE : null;
        }

        return match ($status) {
            self::ALLOCATED => self::ACTION_SEND_TO_WORKSHOP,
            self::SENT_TO_WORKSHOP => self::ACTION_RECEIVE_WORKSHOP,
            self::RECEIVED_BY_WORKSHOP => self::ACTION_MARK_INSTALLED,
            default => null,
        };
    }

    private function nextActionLabel(?string $action): ?string
    {
        return match ($action) {
            self::ACTION_ALLOCATE => 'Alokasi Chain',
            self::ACTION_SEND_TO_WORKSHOP => 'Kirim ke Workshop',
            self::ACTION_RECEIVE_WORKSHOP => 'Terima di Workshop',
            self::ACTION_MARK_INSTALLED => 'Tandai Terpasang',
            default => null,
        };
    }

    private function columnAvailable(): bool
    {
        $schema = Schema::connection('third');

        foreach (['spk_chains', 'spkchains'] as $table) {
            if ($schema->hasTable($table) && $schema->hasColumn($table, 'fulfillment_status')) {
                return true;
            }
        }

        return false;
    }

    private function persistStatus(SpkChain $chain, string $status, ?string $actor = null): void
    {
        if (! $this->columnAvailable()) {
            return;
        }

        $attributes = [
            'fulfillment_status' => $status,
            'modified_date' => now(),
        ];

        if ($actor !== null) {
            $attributes['modified_by'] = $actor;
        }

        $chain->update($attributes);
    }

    private function recordWorkshopReceived(SpkChain $chain, string $actor): void
    {
        if (! $this->workshopReceivedColumnsAvailable()) {
            return;
        }

        $now = now();

        $chain->update([
            'workshop_received_at' => $now,
            'workshop_received_by' => $actor,
            'modified_date' => $now,
            'modified_by' => $actor,
        ]);
    }

    private function workshopReceivedColumnsAvailable(): bool
    {
        $schema = Schema::connection('third');

        foreach (['spk_chains', 'spkchains'] as $table) {
            if (
                $schema->hasTable($table)
                && $schema->hasColumn($table, 'workshop_received_at')
                && $schema->hasColumn($table, 'workshop_received_by')
            ) {
                return true;
            }
        }

        return false;
    }
}
