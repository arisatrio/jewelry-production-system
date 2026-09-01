<?php

namespace App\Support;

use App\Models\JewelCadRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class JewelCadApprovalService
{
    public const DOC_NAME = 'requestjwcad';

    public const STATUS_SUBMITTED = 'JWD010';

    public const STATUS_MANAGER = 'JWD020';

    public const STATUS_DONE = 'JWDDONE';

    public const APPROVE_OK = 'OK';

    public function isDraft(JewelCadRequest $request): bool
    {
        $status = strtoupper(trim((string) $request->status));

        return $status === '' || $status === 'DRAFT';
    }

    public function isPendingManager(JewelCadRequest $request): bool
    {
        return strtoupper(trim((string) $request->status)) === self::STATUS_SUBMITTED;
    }

    public function isPendingComplete(JewelCadRequest $request): bool
    {
        return strtoupper(trim((string) $request->status)) === self::STATUS_MANAGER;
    }

    public function isDone(JewelCadRequest $request): bool
    {
        return strtoupper(trim((string) $request->status)) === self::STATUS_DONE;
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
    public function abilitiesFor(JewelCadRequest $request, ?User $user): array
    {
        $isDraft = $this->isDraft($request);
        $isPendingManager = $this->isPendingManager($request);
        $isPendingComplete = $this->isPendingComplete($request);
        $canEditDraft = SpkApprovalRoles::canEditDraft($user);

        return [
            'canSubmit' => $isDraft && $canEditDraft,
            'canEdit' => $isDraft && $canEditDraft,
            'canOpenEdit' => $canEditDraft,
            'canDelete' => $canEditDraft,
            'canManagerApprove' => $isPendingManager && SpkApprovalRoles::canManagerApprove($user),
            'canComplete' => $isPendingComplete && $canEditDraft,
            'status' => $this->normalizedStatus($request),
            'statusLabel' => $this->statusLabel($request),
        ];
    }

    public function submit(JewelCadRequest $request, string $actor): JewelCadRequest
    {
        if (! $this->isDraft($request)) {
            throw new InvalidArgumentException('Hanya request berstatus Draft yang dapat dikirim ke Manager Produksi.');
        }

        $detailCount = $request->details()->notDeleted()->count();

        if ($detailCount === 0) {
            throw new InvalidArgumentException('Minimal harus ada satu SPK sebelum dikirim ke Manager Produksi.');
        }

        return DB::connection('third')->transaction(function () use ($request, $actor): JewelCadRequest {
            $this->writeApprovalLog(
                $request,
                self::STATUS_SUBMITTED,
                self::APPROVE_OK,
                'Pengajuan Approval',
                $actor,
            );

            $request->update([
                'status' => self::STATUS_SUBMITTED,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $request->refresh();
        });
    }

    /**
     * Manager Produksi meng-approve request — status berubah ke JWD020.
     */
    public function managerApprove(JewelCadRequest $request, string $actor, ?string $notes = null): JewelCadRequest
    {
        if (! $this->isPendingManager($request)) {
            throw new InvalidArgumentException('Request harus berstatus Pengajuan Approval sebelum di-approve.');
        }

        return DB::connection('third')->transaction(function () use ($request, $actor, $notes): JewelCadRequest {
            $this->writeApprovalLog(
                $request,
                self::STATUS_MANAGER,
                self::APPROVE_OK,
                $notes ?? 'Approved by Manager Produksi.',
                $actor,
            );

            $request->update([
                'status' => self::STATUS_MANAGER,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $request->refresh();
        });
    }

    /**
     * Menyelesaikan request — status berubah ke JWDDONE.
     */
    public function complete(JewelCadRequest $request, string $actor, ?string $notes = null): JewelCadRequest
    {
        if (! $this->isPendingComplete($request)) {
            throw new InvalidArgumentException('Request harus berstatus Serahkan ke JWCAD sebelum diselesaikan.');
        }

        return DB::connection('third')->transaction(function () use ($request, $actor, $notes): JewelCadRequest {
            $this->writeApprovalLog(
                $request,
                self::STATUS_DONE,
                self::APPROVE_OK,
                $notes ?? 'Completed',
                $actor,
            );

            $request->update([
                'status' => self::STATUS_DONE,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            return $request->refresh();
        });
    }

    /**
     * Footer create: Dibuat Oleh terisi, tahap Manager masih kosong.
     *
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
     * Footer mengikuti flow: JWD010 (pengajuan) → JWD020 (manager).
     *
     * @return list<array{title: string, name: string, date: string}>
     */
    public function footerColumns(JewelCadRequest $request, string $fallbackCreator = '-'): array
    {
        $history = $this->history($request);

        $createdBy = filled($request->created_by)
            ? (string) $request->created_by
            : $fallbackCreator;
        $createdAt = $request->created_date?->format('d/m/Y H:i') ?? '-';

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
    public function history(JewelCadRequest $request): array
    {
        if (! Schema::connection('third')->hasTable('sysapproval')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', self::DOC_NAME)
            ->where('doc_id', $request->row_id);

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
                self::STATUS_MANAGER => 'Serahkan ke JWCAD',
                self::STATUS_DONE => 'Completed',
            ];
        }

        $query = DB::connection('third')
            ->table('sysstatus')
            ->where('doc_name', self::DOC_NAME);

        if (Schema::connection('third')->hasColumn('sysstatus', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->pluck('current_status', 'code')
            ->map(fn (mixed $label): string => (string) $label)
            ->all();
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

    private function normalizedStatus(JewelCadRequest $request): string
    {
        if ($this->isDraft($request)) {
            return 'DRAFT';
        }

        return strtoupper(trim((string) $request->status));
    }

    private function statusLabel(JewelCadRequest $request): string
    {
        if ($this->isDraft($request)) {
            return 'Draft';
        }

        $status = $this->normalizedStatus($request);
        $labels = $this->statusLabels();

        return $labels[$status] ?? $status;
    }

    public function statusLabelFor(JewelCadRequest $request): string
    {
        return $this->statusLabel($request);
    }

    private function writeApprovalLog(
        JewelCadRequest $request,
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
            'doc_id' => $request->row_id,
            'doc_no' => $request->doc_no,
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
