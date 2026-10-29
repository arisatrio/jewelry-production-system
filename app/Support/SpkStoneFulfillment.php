<?php

namespace App\Support;

use App\Models\SpkStone;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkStoneFulfillment
{
    public const NOT_ALLOCATED = 'not_allocated';

    public const ORDERING = 'ordering';

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
            self::ALLOCATED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::NOT_ALLOCATED => 'Belum Dialokasikan',
            self::ORDERING => 'Sedang Diorder',
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

    private function allocatedPcs(SpkStone $stone): int
    {
        return 0;
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
            return $stored === self::ORDERING ? self::ORDERING : self::ALLOCATED;
        }

        return $stored;
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
