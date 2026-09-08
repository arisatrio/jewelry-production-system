<?php

namespace App\Support;

use App\Models\Coran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CoranApprovalService
{
    public const DOC_NAME = 'coran';

    public const STATUS_SUBMITTED = 'COR010';

    public const STATUS_MANAGER = 'COR020';

    public const STATUS_DONE = 'CORDONE';

    public const APPROVE_OK = 'OK';

    public const KEY_OPEN = 'open';

    public const KEY_SUBMITTED = 'submitted';

    public const KEY_MANAGER = 'manager';

    public const KEY_DONE = 'done';

    /**
     * @var list<array{key: string, label: string}>
     */
    public const STAGES = [
        [
            'key' => self::KEY_OPEN,
            'label' => 'Open',
        ],
        [
            'key' => self::KEY_SUBMITTED,
            'label' => 'Pengajuan',
        ],
        [
            'key' => self::KEY_MANAGER,
            'label' => 'Serahkan ke PPIC',
        ],
        [
            'key' => self::KEY_DONE,
            'label' => 'Done',
        ],
    ];

    public function isOpen(Coran $coran): bool
    {
        $status = strtoupper(trim((string) $coran->status));

        return $status === '' || $status === 'DRAFT' || $status === 'OPEN';
    }

    public function isPendingManager(Coran $coran): bool
    {
        return strtoupper(trim((string) $coran->status)) === self::STATUS_SUBMITTED;
    }

    public function isPendingComplete(Coran $coran): bool
    {
        return strtoupper(trim((string) $coran->status)) === self::STATUS_MANAGER;
    }

    public function isDone(Coran $coran): bool
    {
        return strtoupper(trim((string) $coran->status)) === self::STATUS_DONE;
    }

    public function canEditForm(Coran $coran): bool
    {
        return $this->isOpen($coran) || $this->isDone($coran);
    }

    public function canDelete(Coran $coran): bool
    {
        return $this->isOpen($coran) || $this->isDone($coran);
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
    public function abilitiesFor(Coran $coran, ?User $user): array
    {
        $isOpen = $this->isOpen($coran);
        $isPendingManager = $this->isPendingManager($coran);
        $isPendingComplete = $this->isPendingComplete($coran);
        $canEditDraft = SpkApprovalRoles::canEditDraft($user);
        $canEditForm = $this->canEditForm($coran) && $canEditDraft;
        $canDelete = $this->canDelete($coran) && $canEditDraft;

        return [
            'canSubmit' => $isOpen && $canEditDraft,
            'canEdit' => $canEditForm,
            'canOpenEdit' => $canEditForm,
            'canDelete' => $canDelete,
            'canManagerApprove' => $isPendingManager && SpkApprovalRoles::canManagerApprove($user),
            'canComplete' => $isPendingComplete && $canEditDraft,
            'status' => $this->normalizedStatus($coran),
            'statusLabel' => $this->statusLabelFor($coran),
        ];
    }

    public function submit(Coran $coran, string $actor): Coran
    {
        if (! $this->isOpen($coran)) {
            throw new InvalidArgumentException('Hanya dokumen berstatus Open yang dapat dikirim ke Manager Produksi.');
        }

        $detailCount = $coran->details()->notDeleted()->count();

        if ($detailCount === 0) {
            throw new InvalidArgumentException('Minimal harus ada satu SPK sebelum dikirim ke Manager Produksi.');
        }

        return DB::connection('third')->transaction(function () use ($coran, $actor): Coran {
            $this->writeApprovalLog(
                $coran,
                self::STATUS_SUBMITTED,
                self::APPROVE_OK,
                'Pengajuan Approval',
                $actor,
            );

            $coran->update([
                'status' => self::STATUS_SUBMITTED,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $coran->refresh();
        });
    }

    public function managerApprove(Coran $coran, string $actor, ?string $notes = null): Coran
    {
        if (! $this->isPendingManager($coran)) {
            throw new InvalidArgumentException('Dokumen harus berstatus Pengajuan sebelum di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($coran, $actor, $notes): Coran {
            $this->writeApprovalLog(
                $coran,
                self::STATUS_MANAGER,
                self::APPROVE_OK,
                $notes ?? 'Approved by Manager Produksi.',
                $actor,
            );

            $coran->update([
                'status' => self::STATUS_MANAGER,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $coran->refresh();
        });
    }

    public function complete(Coran $coran, string $actor, ?string $notes = null): Coran
    {
        if (! $this->isPendingComplete($coran)) {
            throw new InvalidArgumentException('Dokumen harus berstatus Serahkan ke PPIC sebelum diselesaikan.');
        }

        return DB::connection('third')->transaction(function () use ($coran, $actor, $notes): Coran {
            $this->writeApprovalLog(
                $coran,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes ?? 'Completed',
                $actor,
            );

            $coran->update([
                'status' => self::STATUS_DONE,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $coran->refresh();
        });
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    public function footerColumns(Coran $coran, string $fallbackCreator = '-'): array
    {
        $history = $this->history($coran);

        $createdBy = filled($coran->created_by)
            ? (string) $coran->created_by
            : $fallbackCreator;
        $createdAt = $coran->created_date?->format('d/m/Y H:i') ?? '-';

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
     * @return array{
     *     key: string,
     *     label: string,
     *     stageIndex: int,
     *     stages: list<array{key: string, label: string}>
     * }
     */
    public function map(Coran $coran): array
    {
        $key = $this->resolveKey($coran);

        return [
            'key' => $key,
            'label' => $this->labelFor($key, $coran),
            'stageIndex' => $this->stageIndexFor($key),
            'stages' => self::STAGES,
        ];
    }

    public function resolveKey(Coran $coran): string
    {
        $status = strtoupper(trim((string) $coran->status));

        return match ($status) {
            self::STATUS_DONE => self::KEY_DONE,
            self::STATUS_MANAGER => self::KEY_MANAGER,
            self::STATUS_SUBMITTED => self::KEY_SUBMITTED,
            default => self::KEY_OPEN,
        };
    }

    public function statusLabelFor(Coran $coran): string
    {
        if ($this->isOpen($coran)) {
            return 'Open';
        }

        $status = strtoupper(trim((string) $coran->status));
        $labels = $this->statusLabels();

        return $labels[$status] ?? ($status !== '' ? $status : 'Open');
    }

    /**
     * @return list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function history(Coran $coran): array
    {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', self::DOC_NAME)
            ->where('doc_id', $coran->row_id);

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
                    'statusLabel' => $labels[$status] ?? ($status !== '' ? $status : 'Open'),
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
    public function statusLabels(): array
    {
        $defaults = [
            self::STATUS_SUBMITTED => 'Pengajuan',
            self::STATUS_MANAGER => 'Serahkan ke PPIC',
            self::STATUS_DONE => 'Completed',
        ];

        if (! Schema::connection('third')->hasTable('sysstatus')) {
            return $defaults;
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
            ->filter(fn (string $label, mixed $code): bool => $label !== '' && $label !== (string) $code)
            ->all();

        return array_merge($defaults, $labels);
    }

    private function writeApprovalLog(
        Coran $coran,
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
            'doc_id' => $coran->row_id,
            'doc_no' => $coran->doc_no,
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

    private function normalizedStatus(Coran $coran): string
    {
        if ($this->isOpen($coran)) {
            return 'OPEN';
        }

        return strtoupper(trim((string) $coran->status));
    }

    private function labelFor(string $key, Coran $coran): string
    {
        return match ($key) {
            self::KEY_DONE => 'Done',
            self::KEY_MANAGER => 'Serahkan ke PPIC',
            self::KEY_SUBMITTED => 'Pengajuan',
            default => $this->statusLabelFor($coran),
        };
    }

    private function stageIndexFor(string $key): int
    {
        foreach (self::STAGES as $index => $stage) {
            if ($stage['key'] === $key) {
                return $index;
            }
        }

        return 0;
    }
}
