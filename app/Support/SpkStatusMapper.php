<?php

namespace App\Support;

use App\Models\Production;
use Carbon\Carbon;

class SpkStatusMapper
{
    public const KEY_DRAFT = 'draft';

    public const KEY_CONFIRMED = 'confirmed';

    public const KEY_IN_PROGRESS = 'inProgress';

    public const KEY_DONE = 'done';

    /** Alias: menunggu Manager masih tampil sebagai Draft. */
    public const KEY_PENDING_MANAGER = self::KEY_DRAFT;

    /**
     * @var list<array{key: string, label: string}>
     */
    public const STAGES = [
        ['key' => self::KEY_DRAFT, 'label' => 'Draft'],
        ['key' => self::KEY_CONFIRMED, 'label' => 'Confirmed'],
        ['key' => self::KEY_IN_PROGRESS, 'label' => 'In Progress'],
        ['key' => self::KEY_DONE, 'label' => 'Done'],
    ];

    /**
     * Map SPK ke status workflow tampilan (Draft → Confirmed → In Progress → Done).
     * Draft sampai Manager approve; SPKDONE = Confirmed.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     stageIndex: int,
     *     isOverdue: bool,
     *     stages: list<array{key: string, label: string}>
     * }
     */
    public function map(Production $production): array
    {
        $key = $this->resolveKey($production);

        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'stageIndex' => $this->stageIndexFor($key),
            'isOverdue' => $this->isOverdue($production),
            'stages' => self::STAGES,
        ];
    }

    /**
     * Resolve exclusive workflow key: draft | confirmed | inProgress | done.
     */
    public function resolveKey(Production $production): string
    {
        // Belum approve Manager → selalu Draft (termasuk SPK010 / status kosong).
        if (! $this->isManagerApproved($production)) {
            return self::KEY_DRAFT;
        }

        if ($this->isInProgress($production)) {
            return self::KEY_IN_PROGRESS;
        }

        // SPKDONE tanpa jejak proses = Confirmed (bukan Done).
        return self::KEY_CONFIRMED;
    }

    /**
     * Manager sudah approve dokumen (SPKDONE).
     */
    public function isManagerApproved(Production $production): bool
    {
        return strtoupper(trim((string) $production->status)) === SpkApprovalService::STATUS_DONE;
    }

    /**
     * Done produksi terpisah dari approve dokumen.
     * SPKDONE = Confirmed; stage Done menunggu sinyal selesai produksi.
     */
    public function isDone(Production $production): bool
    {
        return $this->resolveKey($production) === self::KEY_DONE;
    }

    /**
     * Confirmed: sudah di-approve Manager (SPKDONE), belum masuk pengerjaan.
     */
    public function isConfirmed(Production $production): bool
    {
        return $this->resolveKey($production) === self::KEY_CONFIRMED;
    }

    /**
     * Menunggu Manager: SPK010 (Pengajuan Approval) — masih Draft di UI.
     */
    public function isPendingManager(Production $production): bool
    {
        return strtoupper(trim((string) $production->status)) === SpkApprovalService::STATUS_PENDING;
    }

    /**
     * In Progress: sudah approve Manager dan ada jejak proses / flag in-process.
     */
    public function isInProgress(Production $production): bool
    {
        if (! $this->isManagerApproved($production)) {
            return false;
        }

        if (filled($production->last_process)) {
            return true;
        }

        return (int) ($production->is_inprocess ?? 0) !== 0;
    }

    /**
     * Overdue: belum SPKDONE dan estimasi delivery sudah lewat hari ini.
     */
    public function isOverdue(Production $production): bool
    {
        if ($this->isManagerApproved($production)) {
            return false;
        }

        if (blank($production->estimated_delivery_time)) {
            return false;
        }

        $estimated = $production->estimated_delivery_time instanceof Carbon
            ? $production->estimated_delivery_time
            : Carbon::parse((string) $production->estimated_delivery_time);

        return $estimated->lt(now()->startOfDay());
    }

    public function labelFor(string $key): string
    {
        foreach (self::STAGES as $stage) {
            if ($stage['key'] === $key) {
                return $stage['label'];
            }
        }

        return 'Draft';
    }

    public function stageIndexFor(string $key): int
    {
        foreach (self::STAGES as $index => $stage) {
            if ($stage['key'] === $key) {
                return $index;
            }
        }

        return 0;
    }
}
