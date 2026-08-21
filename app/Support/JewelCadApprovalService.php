<?php

namespace App\Support;

use App\Models\JewelCadRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JewelCadApprovalService
{
    public const DOC_NAME = 'requestjwcad';

    public const STATUS_SUBMITTED = 'JWD010';

    public const STATUS_MANAGER = 'JWD020';

    public const STATUS_DONE = 'JWDDONE';

    public const APPROVE_OK = 'OK';

    /**
     * Footer create: Dibuat Oleh terisi, tahap Manager & Operator masih kosong.
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
            [
                'title' => 'Operator JewelCAD',
                'name' => '-',
                'date' => '-',
            ],
        ];
    }

    /**
     * Footer mengikuti flow: JWD010 (pengajuan) → JWD020 (manager) → JWDDONE (operator).
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

        $operatorComplete = collect($history)->last(
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
                'title' => 'Manager Produksi',
                'name' => $this->historyActorName($managerApprove),
                'date' => $this->historyActorDate($managerApprove),
            ],
            [
                'title' => 'Operator JewelCAD',
                'name' => $this->historyActorName($operatorComplete),
                'date' => $this->historyActorDate($operatorComplete),
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
}
