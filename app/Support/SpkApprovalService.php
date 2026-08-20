<?php

namespace App\Support;

use App\Models\Production;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SpkApprovalService
{
    public const DOC_NAME = 'spk';

    public const STATUS_PENDING = 'SPK010';

    public const STATUS_DONE = 'SPKDONE';

    public const APPROVE_SUBMIT = 'SEND';

    public const APPROVE_OK = 'OK';

    public const APPROVE_NOK = 'NOK';

    public function __construct(
        private RequestOrderRepository $requestOrders,
    ) {}

    public function isDraft(Production $production): bool
    {
        $status = strtoupper(trim((string) $production->status));

        return $status === '' || $status === '0' || $status === 'DRAFT';
    }

    public function isPendingManager(Production $production): bool
    {
        return strtoupper(trim((string) $production->status)) === self::STATUS_PENDING;
    }

    public function isApproved(Production $production): bool
    {
        return strtoupper(trim((string) $production->status)) === self::STATUS_DONE;
    }

    /**
     * @return array{
     *     canEdit: bool,
     *     canSubmit: bool,
     *     canApprove: bool,
     *     canManagerApprove: bool,
     *     canReject: bool,
     *     canDelete: bool,
     *     status: string,
     *     statusLabel: string,
     *     history: list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>,
     *     role: string,
     *     permissions: list<string>
     * }
     */
    public function abilitiesFor(Production $production, ?User $user): array
    {
        $isDraft = $this->isDraft($production);
        $isPending = $this->isPendingManager($production);
        $isApproved = $this->isApproved($production);

        return [
            'canEdit' => SpkApprovalRoles::canEditDraft($user),
            'canSubmit' => $isDraft && SpkApprovalRoles::canSubmit($user),
            'canApprove' => $isDraft && SpkApprovalRoles::canApprove($user),
            'canManagerApprove' => $isPending && SpkApprovalRoles::canManagerApprove($user),
            'canReject' => $isPending && SpkApprovalRoles::canReject($user),
            'canDelete' => ! $isApproved && SpkApprovalRoles::canEditDraft($user),
            'status' => $this->normalizedStatus($production),
            'statusLabel' => $this->statusLabel($production),
            'history' => $this->history($production),
            'role' => SpkApprovalRoles::roleLabel($user),
            'permissions' => SpkApprovalRoles::permissionNames($user),
        ];
    }

    public function submit(Production $production, string $actor): Production
    {
        if (! $this->isDraft($production)) {
            throw new InvalidArgumentException('Hanya SPK berstatus Draft yang dapat dikirim ke Manager.');
        }

        return DB::connection('third')->transaction(function () use ($production, $actor): Production {
            $this->writeApprovalLog(
                $production,
                self::STATUS_PENDING,
                self::APPROVE_SUBMIT,
                'Dikirim ke Manager Produksi.',
                $actor,
            );

            $production->update([
                'status' => self::STATUS_PENDING,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $production->refresh();
        });
    }

    /**
     * "Kirim ke Produksi" — mengisi Disetujui Oleh, status Draft → SPK010.
     */
    public function approve(Production $production, string $actor, ?string $notes = null): Production
    {
        if (! $this->isDraft($production)) {
            throw new InvalidArgumentException('Hanya SPK berstatus Draft yang dapat dikirim ke Produksi.');
        }

        return DB::connection('third')->transaction(function () use ($production, $actor, $notes): Production {
            $this->writeApprovalLog(
                $production,
                self::STATUS_PENDING,
                self::APPROVE_OK,
                $notes ?? 'Dikirim ke Produksi, menunggu approval Manager Produksi.',
                $actor,
            );

            $production->update([
                'status' => self::STATUS_PENDING,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $production->refresh();
        });
    }

    /**
     * Manager Produksi meng-approve SPK — status berubah ke SPKDONE.
     */
    public function managerApprove(Production $production, string $actor, ?string $notes = null): Production
    {
        if ($this->isApproved($production)) {
            throw new InvalidArgumentException('SPK ini sudah di-approve oleh Manager Produksi.');
        }

        if (! $this->isPendingManager($production)) {
            throw new InvalidArgumentException('SPK harus berstatus menunggu Manager sebelum di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($production, $actor, $notes): Production {
            $this->writeApprovalLog(
                $production,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes ?? 'Approved by Manager Produksi.',
                $actor,
            );

            $production->update([
                'status' => self::STATUS_DONE,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->markRequestOrderOngoing($production);

            return $production->refresh();
        });
    }

    public function reject(Production $production, string $actor, string $notes): Production
    {
        if (! $this->isPendingManager($production)) {
            throw new InvalidArgumentException('Hanya SPK yang menunggu Manager yang dapat di-reject.');
        }

        if (trim($notes) === '') {
            throw new InvalidArgumentException('Catatan reject wajib diisi.');
        }

        return DB::connection('third')->transaction(function () use ($production, $actor, $notes): Production {
            $this->writeApprovalLog(
                $production,
                '',
                self::APPROVE_NOK,
                $notes,
                $actor,
            );

            $production->update([
                'status' => '',
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $production->refresh();
        });
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    public function footerColumns(Production $production, string $fallbackCreator = '-'): array
    {
        $history = $this->history($production);
        $createdBy = filled($production->created_by)
            ? (string) $production->created_by
            : $fallbackCreator;
        $createdAt = $production->created_date?->format('d/m/Y H:i') ?? '-';

        $submit = collect($history)->last(
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_OK
                && strtoupper($row['status']) === self::STATUS_PENDING,
        );

        $managerApprove = collect($history)->last(
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_OK
                && strtoupper($row['status']) === self::STATUS_DONE,
        );

        return [
            [
                'title' => 'Dibuat Oleh',
                'name' => $createdBy !== '' ? $createdBy : '-',
                'date' => $createdAt,
            ],
            [
                'title' => 'Disetujui Oleh',
                'name' => $this->historyActorName($submit),
                'date' => $this->historyActorDate($submit),
            ],
            [
                'title' => 'Manager Produksi',
                'name' => $this->historyActorName($managerApprove),
                'date' => $this->historyActorDate($managerApprove),
            ],
        ];
    }

    /**
     * Gabungan log sysapproval dokumen SPK dan approval tiap proses produksi, urut waktu.
     *
     * @param  list<array{label?: string, sources?: list<array{records?: list<array<string, mixed>>}>}>  $processes
     * @return list<array{source: string, status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function timeline(Production $production, array $processes): array
    {
        return $this->mergeTimeline($this->history($production), $processes);
    }

    /**
     * @param  list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>  $spkHistory
     * @param  list<array{label?: string, sources?: list<array{records?: list<array<string, mixed>>}>}>  $processes
     * @return list<array{source: string, status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function mergeTimeline(array $spkHistory, array $processes): array
    {
        $events = [];

        foreach ($spkHistory as $row) {
            $events[] = [
                ...$row,
                'source' => 'SPK',
            ];
        }

        foreach ($processes as $tab) {
            $sourceLabel = filled($tab['label'] ?? null) ? (string) $tab['label'] : 'Proses';

            foreach ($tab['sources'] ?? [] as $source) {
                foreach ($source['records'] ?? [] as $record) {
                    foreach ($record['approvals'] ?? [] as $approval) {
                        if (! is_array($approval)) {
                            continue;
                        }

                        $events[] = [
                            'source' => $sourceLabel,
                            'status' => (string) ($approval['status'] ?? ''),
                            'statusLabel' => (string) ($approval['statusLabel'] ?? $approval['status'] ?? ''),
                            'approve' => (string) ($approval['approve'] ?? '—'),
                            'notes' => filled($approval['notes'] ?? null) ? (string) $approval['notes'] : null,
                            'createdBy' => filled($approval['createdBy'] ?? null) ? (string) $approval['createdBy'] : null,
                            'createdAt' => filled($approval['createdAt'] ?? null) ? (string) $approval['createdAt'] : null,
                        ];
                    }
                }
            }
        }

        return collect($events)
            ->sortBy(fn (array $row): string => $this->timelineSortKey($row['createdAt'] ?? null))
            ->values()
            ->all();
    }

    /**
     * @return list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function history(Production $production): array
    {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', self::DOC_NAME)
            ->where('doc_id', $production->row_id);

        if (Schema::connection('third')->hasColumn('sysapproval', 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        $labels = $this->statusLabels();

        return $query
            ->orderBy('created_date')
            ->orderBy('row_id')
            ->get([
                'status',
                'approve',
                'notes',
                'created_date',
                'created_by',
            ])
            ->map(function (object $row) use ($labels): array {
                $status = (string) ($row->status ?? '');

                return [
                    'status' => $status,
                    'statusLabel' => $labels[$status] ?? ($status !== '' ? $status : 'Draft'),
                    'approve' => filled($row->approve ?? null) ? (string) $row->approve : '—',
                    'notes' => filled($row->notes ?? null) ? (string) $row->notes : null,
                    'createdBy' => filled($row->created_by ?? null) ? (string) $row->created_by : null,
                    'createdAt' => filled($row->created_date ?? null) ? (string) $row->created_date : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Cek apakah SPK sudah pernah disetujui (ada log APPROVE_OK) tapi belum DONE.
     */
    public function hasBeenApproved(Production $production): bool
    {
        $history = $this->history($production);

        return collect($history)->contains(
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_OK,
        );
    }

    private function writeApprovalLog(
        Production $production,
        string $status,
        string $approve,
        ?string $notes,
        string $actor,
    ): void {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return;
        }

        $now = now();

        DB::connection('third')->table('sysapproval')->insert([
            'doc_id' => $production->row_id,
            'doc_no' => $production->spk_no,
            'doc_name' => self::DOC_NAME,
            'status' => $status,
            'approve' => $approve,
            'notes' => $notes,
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => $actor,
            'modified_date' => $now,
            'modified_by' => $actor,
            'deleted_date' => null,
            'deleted_by' => null,
        ]);
    }

    private function markRequestOrderOngoing(Production $production): void
    {
        if (! filled($production->request_order_no)) {
            return;
        }

        $this->requestOrders->markOngoing((string) $production->request_order_no);
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        if (! Schema::connection('third')->hasTable('sysstatus')) {
            return [
                self::STATUS_PENDING => 'Menunggu Approval',
                self::STATUS_DONE => 'Approved',
            ];
        }

        $query = DB::connection('third')
            ->table('sysstatus')
            ->where('doc_name', self::DOC_NAME);

        if (Schema::connection('third')->hasColumn('sysstatus', 'is_deleted')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            });
        }

        return $query
            ->pluck('current_status', 'code')
            ->map(fn (mixed $label): string => $this->normalizeStatusLabel((string) $label))
            ->all();
    }

    private function normalizedStatus(Production $production): string
    {
        if ($this->isDraft($production)) {
            return 'DRAFT';
        }

        return strtoupper(trim((string) $production->status));
    }

    private function statusLabel(Production $production): string
    {
        if ($this->isDraft($production)) {
            return 'Draft';
        }

        $status = $this->normalizedStatus($production);
        $labels = $this->statusLabels();

        return $labels[$status] ?? $this->normalizeStatusLabel($status);
    }

    private function normalizeStatusLabel(string $label): string
    {
        $normalized = trim($label);

        return match (strtoupper($normalized)) {
            'APPROVED' => 'Approved',
            'MENUNGGU MANAGER' => 'Menunggu Approval',
            'DRAFT' => 'Draft',
            default => $normalized,
        };
    }

    /**
     * @param  array{createdBy?: string|null, createdAt?: string|null}|false|null  $row
     */
    private function historyActorName(array|false|null $row): string
    {
        if (! is_array($row) || blank($row['createdBy'] ?? null)) {
            return '-';
        }

        return (string) $row['createdBy'];
    }

    /**
     * @param  array{createdBy?: string|null, createdAt?: string|null}|false|null  $row
     */
    private function historyActorDate(array|false|null $row): string
    {
        if (! is_array($row) || blank($row['createdAt'] ?? null)) {
            return '-';
        }

        return $this->formatFooterDate((string) $row['createdAt']);
    }

    private function timelineSortKey(?string $createdAt): string
    {
        if (! filled($createdAt)) {
            return '9999-12-31 23:59:59';
        }

        try {
            return Carbon::parse($createdAt)->format('Y-m-d H:i:s.u');
        } catch (\Throwable) {
            return $createdAt;
        }
    }

    private function formatFooterDate(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }
}
