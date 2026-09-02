<?php

namespace App\Support;

use App\Models\Resin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ResinApprovalService
{
    public const DOC_NAME = 'resin';

    public const STATUS_SUBMITTED = 'RSN010';

    public const STATUS_MANAGER = 'RSN020';

    public const STATUS_DONE = 'RSNDONE';

    public const APPROVE_OK = 'OK';

    public function isDraft(Resin $resin): bool
    {
        $status = strtoupper(trim((string) $resin->status));

        return $status === ''
            || $status === 'DRAFT'
            || $status === Resin::STATUS_OPEN;
    }

    public function isPendingManager(Resin $resin): bool
    {
        return strtoupper(trim((string) $resin->status)) === self::STATUS_SUBMITTED;
    }

    public function isPendingComplete(Resin $resin): bool
    {
        return strtoupper(trim((string) $resin->status)) === self::STATUS_MANAGER;
    }

    public function isDone(Resin $resin): bool
    {
        $status = strtoupper(trim((string) $resin->status));

        return in_array($status, [self::STATUS_DONE, Resin::STATUS_DONE], true);
    }

    public function canEditForm(Resin $resin): bool
    {
        return $this->isDraft($resin) || $this->isPendingManager($resin);
    }

    /**
     * @return array{
     *     canSubmit: bool,
     *     canEdit: bool,
     *     canOpenEdit: bool,
     *     canDelete: bool,
     *     canManagerApprove: bool,
     *     canComplete: bool,
     *     status: string,
     *     statusLabel: string
     * }
     */
    public function abilitiesFor(Resin $resin, ?User $user): array
    {
        $isDraft = $this->isDraft($resin);
        $isPendingManager = $this->isPendingManager($resin);
        $isPendingComplete = $this->isPendingComplete($resin);
        $canEditDraft = SpkApprovalRoles::canEditDraft($user);

        return [
            'canSubmit' => $isDraft && $canEditDraft,
            'canEdit' => $this->canEditForm($resin) && $canEditDraft,
            'canOpenEdit' => $canEditDraft,
            'canDelete' => $canEditDraft,
            'canManagerApprove' => $isPendingManager && SpkApprovalRoles::canManagerApprove($user),
            'canComplete' => $isPendingComplete && $canEditDraft,
            'status' => $this->normalizedStatus($resin),
            'statusLabel' => $this->statusLabel($resin),
        ];
    }

    public function submit(Resin $resin, string $actor): Resin
    {
        if (! $this->isDraft($resin)) {
            throw new InvalidArgumentException('Hanya request berstatus Draft yang dapat dikirim ke Manager Produksi.');
        }

        $detailCount = $resin->details()->notDeleted()->count();

        if ($detailCount === 0 && ! filled($resin->spk_id)) {
            throw new InvalidArgumentException('Minimal harus ada satu SPK sebelum dikirim ke Manager Produksi.');
        }

        return DB::connection('third')->transaction(function () use ($resin, $actor): Resin {
            $this->writeApprovalLog(
                $resin,
                self::STATUS_SUBMITTED,
                self::APPROVE_OK,
                'Pengajuan Approval',
                $actor,
            );

            $resin->update([
                'status' => self::STATUS_SUBMITTED,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $resin->refresh();
        });
    }

    public function managerApprove(Resin $resin, string $actor, ?string $notes = null): Resin
    {
        if (! $this->isPendingManager($resin)) {
            throw new InvalidArgumentException('Request harus berstatus Pengajuan Approval sebelum di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($resin, $actor, $notes): Resin {
            $this->writeApprovalLog(
                $resin,
                self::STATUS_MANAGER,
                self::APPROVE_OK,
                $notes ?? 'Approved by Manager Produksi.',
                $actor,
            );

            $resin->update([
                'status' => self::STATUS_MANAGER,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $resin->refresh();
        });
    }

    public function complete(Resin $resin, string $actor, ?string $notes = null): Resin
    {
        if (! $this->isPendingComplete($resin)) {
            throw new InvalidArgumentException('Request harus berstatus Serahkan ke Resin sebelum diselesaikan.');
        }

        return DB::connection('third')->transaction(function () use ($resin, $actor, $notes): Resin {
            $this->writeApprovalLog(
                $resin,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes ?? 'Completed',
                $actor,
            );

            $resin->update([
                'status' => self::STATUS_DONE,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $resin->refresh();
        });
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    public function createFooterColumns(string $creatorName): array
    {
        $name = trim($creatorName);

        return [
            [
                'title' => 'Dibuat Oleh',
                'name' => $name !== '' ? $name : '-',
                'date' => now()->format('d/m/Y H:i'),
            ],
            [
                'title' => 'Manager Produksi',
                'name' => '-',
                'date' => '-',
            ],
        ];
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    public function footerColumns(Resin $resin, string $fallbackCreator = '-'): array
    {
        $history = $this->history($resin);

        $createdBy = filled($resin->created_by)
            ? (string) $resin->created_by
            : $fallbackCreator;
        $createdAt = $resin->created_date?->format('d/m/Y H:i') ?? '-';

        $submitted = collect($history)->last(
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_OK
                && strtoupper($row['status']) === self::STATUS_SUBMITTED,
        );

        if ($createdBy === '-' || $createdBy === '') {
            $createdBy = $this->historyActorName($submitted);
            $createdAt = $this->historyActorDate($submitted);
        }

        $managerApprove = collect($history)->last(
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_OK
                && strtoupper($row['status']) === self::STATUS_MANAGER,
        );

        return [
            [
                'title' => 'Dibuat Oleh',
                'name' => $createdBy !== '' ? $createdBy : '-',
                'date' => $createdAt,
            ],
            [
                'title' => 'Manager Produksi',
                'name' => $this->historyActorName($managerApprove),
                'date' => $this->historyActorDate($managerApprove),
            ],
        ];
    }

    /**
     * @return list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function history(Resin $resin): array
    {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', self::DOC_NAME)
            ->where('doc_id', $resin->row_id);

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
                'created_by',
                'created_date',
            ])
            ->map(function (object $row) use ($labels): array {
                $status = (string) ($row->status ?? '');

                return [
                    'status' => $status,
                    'statusLabel' => $labels[$status] ?? ($status !== '' ? $status : 'Draft'),
                    'approve' => (string) ($row->approve ?? ''),
                    'notes' => filled($row->notes ?? null) ? (string) $row->notes : null,
                    'createdBy' => filled($row->created_by ?? null) ? (string) $row->created_by : null,
                    'createdAt' => filled($row->created_date ?? null) ? (string) $row->created_date : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        if (! Schema::connection('third')->hasTable('sysstatus')) {
            return [
                self::STATUS_SUBMITTED => 'Pengajuan Approval',
                self::STATUS_MANAGER => 'Serahkan ke Resin',
                self::STATUS_DONE => 'Completed',
            ];
        }

        $query = DB::connection('third')
            ->table('sysstatus')
            ->where('doc_name', self::DOC_NAME);

        if (Schema::connection('third')->hasColumn('sysstatus', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $labels = $query
            ->pluck('current_status', 'code')
            ->map(fn (mixed $label): string => (string) $label)
            ->filter(fn (string $label, string $code): bool => $label !== '' && $label !== $code)
            ->all();

        return array_merge([
            self::STATUS_SUBMITTED => 'Pengajuan Approval',
            self::STATUS_MANAGER => 'Serahkan ke Resin',
            self::STATUS_DONE => 'Completed',
        ], $labels);
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

    private function formatFooterDate(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizedStatus(Resin $resin): string
    {
        if ($this->isDraft($resin)) {
            return 'DRAFT';
        }

        return strtoupper(trim((string) $resin->status));
    }

    private function statusLabel(Resin $resin): string
    {
        if ($this->isDraft($resin)) {
            return 'Draft';
        }

        $status = $this->normalizedStatus($resin);
        $labels = $this->statusLabels();

        return $labels[$status] ?? $status;
    }

    public function statusLabelFor(Resin $resin): string
    {
        return $this->statusLabel($resin);
    }

    private function writeApprovalLog(
        Resin $resin,
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
            'doc_id' => $resin->row_id,
            'doc_no' => $resin->doc_no,
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
}
