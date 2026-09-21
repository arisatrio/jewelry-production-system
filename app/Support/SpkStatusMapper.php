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

    public const KEY_DONE_RANGKA = SpkDashboardAnalytics::KEY_DONE_RANGKA;

    public const KEY_DONE_BARANG_JADI = SpkDashboardAnalytics::KEY_DONE_BARANG_JADI;

    /** Alias: menunggu Manager masih tampil sebagai Draft. */
    public const KEY_PENDING_MANAGER = self::KEY_DRAFT;

    /**
     * @var list<array{key: string, label: string}>
     */
    public const STAGES = [
        [
            'key' => self::KEY_DRAFT,
            'label' => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_DRAFT],
        ],
        [
            'key' => self::KEY_CONFIRMED,
            'label' => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_CONFIRMED],
        ],
        [
            'key' => self::KEY_IN_PROGRESS,
            'label' => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_IN_PROGRESS],
        ],
        [
            'key' => self::KEY_DONE,
            'label' => 'Done',
        ],
    ];

    /**
     * Map SPK ke status workflow tampilan agar sinkron dengan list SPK.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     stageIndex: int,
     *     isOverdue: bool,
     *     stages: list<array{key: string, label: string}>
     * }
     */
    public function map(Production $production, bool|string|null $completed = null): array
    {
        $key = $this->resolveKey($production, $completed);
        $stages = self::STAGES;

        if (SpkDashboardAnalytics::isDoneStatusKey($key)) {
            $stages[3] = [
                'key' => $key,
                'label' => $this->labelFor($key),
            ];
        }

        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'stageIndex' => $this->stageIndexFor($key),
            'isOverdue' => $this->isOverdue($production, $completed),
            'stages' => $stages,
        ];
    }

    /**
     * Resolve exclusive workflow key.
     */
    public function resolveKey(Production $production, bool|string|null $completed = null): string
    {
        $key = SpkDashboardAnalytics::backlogStatusKey($production, $completed);

        // Menunggu approval manager tetap di stage Draft pada pipeline SPK.
        if ($key === 'pendingManager') {
            return self::KEY_DRAFT;
        }

        return $key;
    }

    /**
     * Manager sudah approve untuk backlog list/detail (SPKDONE, belum proses).
     */
    public function isManagerApproved(Production $production, bool|string|null $completed = null): bool
    {
        return $this->resolveKey($production, $completed) === self::KEY_CONFIRMED;
    }

    /**
     * Done produksi terpisah dari approve dokumen.
     * SPKDONE = Confirmed; stage Done menunggu sinyal selesai produksi.
     */
    public function isDone(Production $production, bool|string|null $completed = null): bool
    {
        return SpkDashboardAnalytics::isDoneStatusKey(
            $this->resolveKey($production, $completed),
        );
    }

    /**
     * Confirmed: sudah di-approve Manager (SPKDONE), belum masuk pengerjaan.
     */
    public function isConfirmed(Production $production, bool|string|null $completed = null): bool
    {
        return $this->resolveKey($production, $completed) === self::KEY_CONFIRMED;
    }

    /**
     * Menunggu approval manager produksi.
     */
    public function isPendingManager(Production $production, bool|string|null $completed = null): bool
    {
        return $this->resolveKey($production, $completed) === self::KEY_DRAFT;
    }

    /**
     * In Progress: ada jejak proses / flag in-process, namun belum selesai produksi.
     */
    public function isInProgress(Production $production, bool|string|null $completed = null): bool
    {
        return $this->resolveKey($production, $completed) === self::KEY_IN_PROGRESS;
    }

    /**
     * Overdue: Approved atau In Progress dengan estimasi delivery sudah lewat hari ini.
     */
    public function isOverdue(Production $production, bool|string|null $completed = null): bool
    {
        $key = $this->resolveKey($production, $completed);

        if (! in_array($key, [self::KEY_CONFIRMED, self::KEY_IN_PROGRESS], true)) {
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
        if (array_key_exists($key, SpkDashboardAnalytics::BACKLOG_STATUS_LABELS)) {
            return SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$key];
        }

        foreach (self::STAGES as $stage) {
            if ($stage['key'] === $key) {
                return $stage['label'];
            }
        }

        return SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_DRAFT];
    }

    public function stageIndexFor(string $key): int
    {
        if (SpkDashboardAnalytics::isDoneStatusKey($key) || $key === self::KEY_DONE) {
            return 3;
        }

        foreach (self::STAGES as $index => $stage) {
            if ($stage['key'] === $key) {
                return $index;
            }
        }

        return 0;
    }
}
