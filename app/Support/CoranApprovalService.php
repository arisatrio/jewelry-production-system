<?php

namespace App\Support;

use App\Models\Coran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoranApprovalService
{
    public const DOC_NAME = 'coran';

    public const STATUS_SUBMITTED = 'COR010';

    public const STATUS_MANAGER = 'COR020';

    public const STATUS_DONE = 'CORDONE';

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

    public function isDone(Coran $coran): bool
    {
        return strtoupper(trim((string) $coran->status)) === self::STATUS_DONE;
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
