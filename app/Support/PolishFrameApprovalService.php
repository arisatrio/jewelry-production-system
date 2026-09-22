<?php

namespace App\Support;

use App\Models\PolishFrame;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PolishFrameApprovalService
{
    public const DOC_NAME = 'polishframe';

    public const STATUS_SUBMITTED = 'PRK010';

    public const STATUS_MANAGER = 'PRK030';

    public const STATUS_DONE = 'PRKDONE';

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

    public function isOpen(PolishFrame $document): bool
    {
        $status = strtoupper(trim((string) $document->status));

        return $status === '' || $status === 'DRAFT' || $status === 'OPEN' || $status === '-';
    }

    public function isPendingManager(PolishFrame $document): bool
    {
        $status = strtoupper(trim((string) $document->status));

        return in_array($status, [
            self::STATUS_SUBMITTED,
            PolishFrame::STATUS_TO_CRAFTSMAN,
            PolishFrame::STATUS_FROM_CRAFTSMAN,
        ], true);
    }

    public function isPendingComplete(PolishFrame $document): bool
    {
        $status = strtoupper(trim((string) $document->status));

        return in_array($status, [
            self::STATUS_MANAGER,
            PolishFrame::STATUS_TO_PPIC,
        ], true);
    }

    public function isDone(PolishFrame $document): bool
    {
        $status = strtoupper(trim((string) $document->status));

        return in_array($status, [
            self::STATUS_DONE,
            PolishFrame::STATUS_DONE,
            PolishFrame::STATUS_TO_JB,
        ], true);
    }

    public function canEditForm(PolishFrame $document): bool
    {
        return $document->is_deleted !== 1 && ! $this->isDone($document);
    }

    public function canDelete(PolishFrame $document): bool
    {
        return $this->isOpen($document) || $this->isDone($document);
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
    public function abilitiesFor(PolishFrame $document, ?User $user): array
    {
        $isOpen = $this->isOpen($document);
        $isPendingManager = $this->isPendingManager($document);
        $isPendingComplete = $this->isPendingComplete($document);
        $canEditDraft = SpkApprovalRoles::canEditDraft($user);
        $canEditForm = $this->canEditForm($document) && $canEditDraft;
        $canDelete = $this->canDelete($document) && $canEditDraft;

        return [
            'canSubmit' => $isOpen && $canEditDraft,
            'canEdit' => $canEditForm,
            'canOpenEdit' => $canEditDraft && $this->canEditForm($document),
            'canDelete' => $canDelete,
            'canManagerApprove' => $isPendingManager && SpkApprovalRoles::canManagerApprove($user),
            'canComplete' => $isPendingComplete && $canEditDraft,
            'status' => $this->normalizedStatus($document),
            'statusLabel' => $this->statusLabelFor($document),
        ];
    }

    public function submit(PolishFrame $document, string $actor): PolishFrame
    {
        if (! $this->isOpen($document)) {
            throw new InvalidArgumentException('Hanya dokumen berstatus Open yang dapat dikirim ke Manager Produksi.');
        }

        if (! filled($document->spk_id)) {
            throw new InvalidArgumentException('Minimal harus ada satu SPK sebelum dikirim ke Manager Produksi.');
        }

        return DB::connection('third')->transaction(function () use ($document, $actor): PolishFrame {
            $this->writeApprovalLog(
                $document,
                self::STATUS_SUBMITTED,
                self::APPROVE_OK,
                'Pengajuan Approval',
                $actor,
            );

            $document->update([
                'status' => self::STATUS_SUBMITTED,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $document->refresh();
        });
    }

    public function managerApprove(PolishFrame $document, string $actor, ?string $notes = null): PolishFrame
    {
        if (! $this->isPendingManager($document)) {
            throw new InvalidArgumentException('Dokumen harus berstatus Pengajuan sebelum di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($document, $actor, $notes): PolishFrame {
            $this->writeApprovalLog(
                $document,
                self::STATUS_MANAGER,
                self::APPROVE_OK,
                $notes ?? 'Approved by Manager Produksi.',
                $actor,
            );

            $document->update([
                'status' => self::STATUS_MANAGER,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $document->refresh();
        });
    }

    public function complete(PolishFrame $document, string $actor, ?string $notes = null): PolishFrame
    {
        if (! $this->isPendingComplete($document)) {
            throw new InvalidArgumentException('Dokumen harus berstatus Serahkan ke PPIC sebelum diselesaikan.');
        }

        return DB::connection('third')->transaction(function () use ($document, $actor, $notes): PolishFrame {
            $this->writeApprovalLog(
                $document,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes ?? 'Completed',
                $actor,
            );

            $document->update([
                'status' => self::STATUS_DONE,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $document->refresh();
        });
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    public function footerColumns(PolishFrame $document, string $fallbackCreator = '-'): array
    {
        $history = $this->history($document);

        $createdBy = filled($document->created_by)
            ? (string) $document->created_by
            : $fallbackCreator;
        $createdAt = $document->created_date?->format('d/m/Y H:i') ?? '-';

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
    public function map(PolishFrame $document): array
    {
        $key = $this->resolveKey($document);

        return [
            'key' => $key,
            'label' => $this->labelFor($key, $document),
            'stageIndex' => $this->stageIndexFor($key),
            'stages' => self::STAGES,
        ];
    }

    public function resolveKey(PolishFrame $document): string
    {
        if ($this->isDone($document)) {
            return self::KEY_DONE;
        }

        if ($this->isPendingComplete($document)) {
            return self::KEY_MANAGER;
        }

        if ($this->isPendingManager($document)) {
            return self::KEY_SUBMITTED;
        }

        return self::KEY_OPEN;
    }

    public function statusLabelFor(PolishFrame $document): string
    {
        if ($this->isOpen($document)) {
            return 'Open';
        }

        $status = strtoupper(trim((string) $document->status));
        $labels = $this->statusLabels();

        return $labels[$status] ?? ($status !== '' ? $status : 'Open');
    }

    /**
     * @return list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>
     */
    public function history(PolishFrame $document): array
    {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', self::DOC_NAME)
            ->where('doc_id', $document->row_id);

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
            PolishFrame::STATUS_TO_CRAFTSMAN => 'Pengajuan',
            PolishFrame::STATUS_FROM_CRAFTSMAN => 'Pengajuan',
            PolishFrame::STATUS_TO_PPIC => 'Serahkan ke PPIC',
            PolishFrame::STATUS_TO_JB => 'Completed',
            PolishFrame::STATUS_DONE => 'Completed',
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

        return array_merge($defaults, $labels, [
            self::STATUS_SUBMITTED => 'Pengajuan',
            self::STATUS_MANAGER => 'Serahkan ke PPIC',
            self::STATUS_DONE => 'Completed',
            PolishFrame::STATUS_TO_CRAFTSMAN => 'Pengajuan',
            PolishFrame::STATUS_FROM_CRAFTSMAN => 'Pengajuan',
            PolishFrame::STATUS_TO_PPIC => 'Serahkan ke PPIC',
            PolishFrame::STATUS_TO_JB => 'Completed',
            PolishFrame::STATUS_DONE => 'Completed',
        ]);
    }

    private function writeApprovalLog(
        PolishFrame $document,
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
            'doc_id' => $document->row_id,
            'doc_no' => $document->doc_no,
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

    private function normalizedStatus(PolishFrame $document): string
    {
        if ($this->isOpen($document)) {
            return 'OPEN';
        }

        return strtoupper(trim((string) $document->status));
    }

    private function labelFor(string $key, PolishFrame $document): string
    {
        return match ($key) {
            self::KEY_DONE => 'Done',
            self::KEY_MANAGER => 'Serahkan ke PPIC',
            self::KEY_SUBMITTED => 'Pengajuan',
            default => $this->statusLabelFor($document),
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
