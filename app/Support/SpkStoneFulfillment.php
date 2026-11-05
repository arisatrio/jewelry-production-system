<?php

namespace App\Support;

use App\Models\SpkStone;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkStoneFulfillment
{
    public const NOT_ALLOCATED = 'not_allocated';

    public const ORDERING = 'ordering';

    public const ORDERED = 'ordered';

    public const SHIPPING = 'shipping';

    public const ALLOCATED = 'allocated';

    public const ACTION_MARK_ORDERING = 'mark_ordering';

    /**
     * @return list<string>
     */
    public static function orderedStatuses(): array
    {
        return [
            self::NOT_ALLOCATED,
            self::ORDERING,
            self::ORDERED,
            self::SHIPPING,
            self::ALLOCATED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function purchaseStatuses(): array
    {
        return [
            self::ORDERING,
            self::ORDERED,
            self::SHIPPING,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::NOT_ALLOCATED => 'Belum Dialokasikan',
            self::ORDERING => 'Sedang Diorder',
            self::ORDERED => 'Selesai',
            self::SHIPPING => 'Sedang Dikirim Supplier',
            self::ALLOCATED => 'Sudah Dialokasikan',
            default => '—',
        };
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function resolve(SpkStone $stone): array
    {
        $stored = $this->normalizeStoredStatus($stone->fulfillment_status);
        $allocatedPcs = $this->allocatedPcs($stone);
        $status = $this->effectiveStatus($stored, $allocatedPcs);

        if ($this->columnAvailable() && $status !== $stored) {
            $this->persistStatus($stone, $status);
        }

        return $this->payload(
            $status,
            $allocatedPcs,
            $this->nextAction($status, $allocatedPcs),
        );
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function advance(SpkStone $stone, string $action, string $actor): array
    {
        abort_if($stone->is_deleted === 1, 404);

        $current = $this->resolve($stone);
        $expected = $current['nextAction'];

        if ($expected === null || $action !== $expected) {
            throw new HttpException(422, 'Langkah status tidak valid.');
        }

        $nextStatus = match ($action) {
            self::ACTION_MARK_ORDERING => self::ORDERING,
            default => throw new HttpException(422, 'Aksi tidak dikenali.'),
        };

        $this->persistStatus($stone, $nextStatus, $actor);

        return $this->resolve($stone->refresh());
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function updatePurchaseStatus(SpkStone $stone, string $status, string $actor): array
    {
        abort_if($stone->is_deleted === 1, 404);

        if (! in_array($status, self::purchaseStatuses(), true)) {
            throw new HttpException(422, 'Status tidak valid.');
        }

        $stored = $this->normalizeStoredStatus($stone->fulfillment_status);

        if (! in_array($stored, self::purchaseStatuses(), true)) {
            throw new HttpException(422, 'Baris batu tidak ada di tahap pembelian.');
        }

        if ($this->allocatedPcs($stone) > 0) {
            throw new HttpException(422, 'Status tidak dapat diubah setelah batu dialokasikan.');
        }

        if ($stored !== $status) {
            $this->persistStatus($stone, $status, $actor);
        }

        return $this->resolve($stone->refresh());
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function cancelPurchase(SpkStone $stone, string $actor): array
    {
        abort_if($stone->is_deleted === 1, 404);

        $this->assertPurchaseRowManageable($stone);

        $this->persistStatus($stone, self::NOT_ALLOCATED, $actor);

        return $this->resolve($stone->refresh());
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    public function restockPurchase(SpkStone $stone, string $actor): array
    {
        abort_if($stone->is_deleted === 1, 404);

        $this->assertPurchaseRowManageable($stone);

        $stored = $this->normalizeStoredStatus($stone->fulfillment_status);

        if ($stored !== self::SHIPPING) {
            throw new HttpException(422, 'Restok hanya untuk batu dengan status sedang dikirim.');
        }

        $stone->loadMissing('production');

        $ledger = app(StoneLedger::class);
        $activePeriod = $ledger->activePeriod();

        if ($activePeriod === null) {
            throw new HttpException(422, 'Periode stok batu aktif tidak ditemukan.');
        }

        $match = app(SpkStoneStockChecker::class)->matchingStones($stone);
        $microRows = is_array($match['micro']['rows'] ?? null) ? $match['micro']['rows'] : [];
        $msStoneId = (int) ($microRows[0]['id'] ?? 0);

        if ($msStoneId <= 0) {
            throw new HttpException(422, 'Master batu mikro yang cocok tidak ditemukan.');
        }

        $pcs = (int) ($stone->pcs ?? 0);

        if ($pcs <= 0) {
            throw new HttpException(422, 'Jumlah butir batu SPK tidak valid.');
        }

        $spkNo = filled($stone->production?->spk_no ?? null)
            ? (string) $stone->production->spk_no
            : null;

        $ledger->store(
            $activePeriod['id'],
            'addition',
            $msStoneId,
            (string) $pcs,
            filled($stone->carat ?? null) ? (string) $stone->carat : null,
            $spkNo !== null ? 'Restok pembelian batu SPK '.$spkNo : 'Restok pembelian batu',
            $actor,
        );

        $this->persistStatus($stone, self::NOT_ALLOCATED, $actor);

        return $this->resolve($stone->refresh());
    }

    private function assertPurchaseRowManageable(SpkStone $stone): void
    {
        $stored = $this->normalizeStoredStatus($stone->fulfillment_status);

        if (! in_array($stored, self::purchaseStatuses(), true)) {
            throw new HttpException(422, 'Baris batu tidak ada di tahap pembelian.');
        }

        if ($this->allocatedPcs($stone) > 0) {
            throw new HttpException(422, 'Tindakan tidak dapat dilakukan setelah batu dialokasikan.');
        }
    }

    private function allocatedPcs(SpkStone $stone): int
    {
        return app(SpkStoneAllocationService::class)->allocatedPcs($stone);
    }

    private function normalizeStoredStatus(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';

        if ($status === '' || ! in_array($status, self::orderedStatuses(), true)) {
            return self::NOT_ALLOCATED;
        }

        return $status;
    }

    private function effectiveStatus(string $stored, int $allocatedPcs): string
    {
        if ($allocatedPcs > 0) {
            return $stored === self::ORDERING || $stored === self::ORDERED
                ? $stored
                : self::ALLOCATED;
        }

        if ($stored === self::ORDERING || $stored === self::ORDERED || $stored === self::SHIPPING) {
            return $stored;
        }

        return self::NOT_ALLOCATED;
    }

    private function nextAction(string $status, int $allocatedPcs): ?string
    {
        if ($allocatedPcs > 0) {
            return null;
        }

        return match ($status) {
            self::NOT_ALLOCATED => self::ACTION_MARK_ORDERING,
            default => null,
        };
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }
     */
    private function payload(string $status, int $allocatedPcs, ?string $nextAction): array
    {
        return [
            'status' => $status,
            'label' => self::label($status),
            'allocatedPcs' => $allocatedPcs,
            'nextAction' => $nextAction,
            'nextActionLabel' => $this->nextActionLabel($nextAction),
        ];
    }

    private function nextActionLabel(?string $action): ?string
    {
        return match ($action) {
            self::ACTION_MARK_ORDERING => 'Order',
            default => null,
        };
    }

    private function columnAvailable(): bool
    {
        $schema = Schema::connection('third');

        return $schema->hasTable('spkstone')
            && $schema->hasColumn('spkstone', 'fulfillment_status');
    }

    private function persistStatus(SpkStone $stone, string $status, ?string $actor = null): void
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

        $stone->update($attributes);
    }
}
