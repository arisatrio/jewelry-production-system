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
     *     canReject: bool,
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

        return [
            'canEdit' => $isDraft && SpkApprovalRoles::canEditDraft($user),
            'canSubmit' => $isDraft && SpkApprovalRoles::canSubmit($user),
            'canApprove' => $isPending && SpkApprovalRoles::canApprove($user),
            'canReject' => $isPending && SpkApprovalRoles::canReject($user),
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

    public function approve(Production $production, string $actor, ?string $notes = null): Production
    {
        if (! $this->isPendingManager($production)) {
            throw new InvalidArgumentException('Hanya SPK yang menunggu Manager yang dapat di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($production, $actor, $notes): Production {
            $this->writeApprovalLog(
                $production,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes,
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
            fn (array $row): bool => strtoupper($row['approve']) === self::APPROVE_SUBMIT,
        );

        $approve = collect($history)->last(
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
                'name' => is_array($submit) && filled($submit['createdBy'] ?? null)
                    ? (string) $submit['createdBy']
                    : '-',
                'date' => is_array($submit) && filled($submit['createdAt'] ?? null)
                    ? $this->formatFooterDate((string) $submit['createdAt'])
                    : '-',
            ],
            [
                'title' => 'Manager Produksi',
                'name' => is_array($approve) && filled($approve['createdBy'] ?? null)
                    ? (string) $approve['createdBy']
                    : '-',
                'date' => is_array($approve) && filled($approve['createdAt'] ?? null)
                    ? $this->formatFooterDate((string) $approve['createdAt'])
                    : '-',
            ],
        ];
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
                self::STATUS_PENDING => 'Menunggu Approval Manager Produksi',
                self::STATUS_DONE => 'Approved by Manager Produksi',
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
            return 'Menunggu Approval Manager Produksi';
        }

        $status = $this->normalizedStatus($production);
        $labels = $this->statusLabels();

        return $labels[$status] ?? $this->normalizeStatusLabel($status);
    }

    private function normalizeStatusLabel(string $label): string
    {
        $normalized = trim($label);

        return match (strtoupper($normalized)) {
            'APPROVED' => 'Approved by Manager Produksi',
            'MENUNGGU MANAGER', 'DRAFT' => 'Menunggu Approval Manager Produksi',
            default => $normalized,
        };
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
