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
            'label' => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_DONE],
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
    public function map(Production $production, ?bool $hasCompletedProduction = null): array
    {
        $key = $this->resolveKey($production, $hasCompletedProduction);

        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'stageIndex' => $this->stageIndexFor($key),
            'isOverdue' => $this->isOverdue($production, $hasCompletedProduction),
            'stages' => self::STAGES,
        ];
    }

    /**
     * Resolve exclusive workflow key: draft | confirmed | inProgress | done.
     */
    public function resolveKey(Production $production, ?bool $hasCompletedProduction = null): string
    {
        return SpkDashboardAnalytics::backlogStatusKey($production, $hasCompletedProduction);
    }

    /**
     * Manager sudah approve untuk backlog list/detail (SPKDONE, belum proses).
     */
    public function isManagerApproved(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        return $this->resolveKey($production, $hasCompletedProduction) === self::KEY_CONFIRMED;
    }

    /**
     * Done produksi terpisah dari approve dokumen.
     * SPKDONE = Confirmed; stage Done menunggu sinyal selesai produksi.
     */
    public function isDone(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        return $this->resolveKey($production, $hasCompletedProduction) === self::KEY_DONE;
    }

    /**
     * Confirmed: sudah di-approve Manager (SPKDONE), belum masuk pengerjaan.
     */
    public function isConfirmed(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        return $this->resolveKey($production, $hasCompletedProduction) === self::KEY_CONFIRMED;
    }

    /**
     * Menunggu approval manager produksi.
     */
    public function isPendingManager(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        return $this->resolveKey($production, $hasCompletedProduction) === self::KEY_DRAFT;
    }

    /**
     * In Progress: ada jejak proses / flag in-process, namun belum selesai produksi.
     */
    public function isInProgress(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        return $this->resolveKey($production, $hasCompletedProduction) === self::KEY_IN_PROGRESS;
    }

    /**
     * Overdue: status masih backlog dan estimasi delivery sudah lewat hari ini.
     */
    public function isOverdue(Production $production, ?bool $hasCompletedProduction = null): bool
    {
        if ($this->resolveKey($production, $hasCompletedProduction) === self::KEY_DONE) {
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

        return SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[self::KEY_DRAFT];
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
