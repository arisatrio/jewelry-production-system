<?php

namespace App\Support;

use App\Models\Resin;

class ResinStatusMapper
{
    public const KEY_DRAFT = 'draft';

    public const KEY_SUBMITTED = 'submitted';

    public const KEY_MANAGER = 'manager';

    public const KEY_DONE = 'done';

    /**
     * @var list<array{key: string, label: string}>
     */
    public const STAGES = [
        [
            'key' => self::KEY_DRAFT,
            'label' => 'Draft',
        ],
        [
            'key' => self::KEY_SUBMITTED,
            'label' => 'Pengajuan Approval',
        ],
        [
            'key' => self::KEY_MANAGER,
            'label' => 'Serahkan ke Resin',
        ],
        [
            'key' => self::KEY_DONE,
            'label' => 'Done',
        ],
    ];

    public function __construct(
        private readonly ResinApprovalService $approvalService,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     stageIndex: int,
     *     stages: list<array{key: string, label: string}>
     * }
     */
    public function map(Resin $resin): array
    {
        $key = $this->resolveKey($resin);

        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'stageIndex' => $this->stageIndexFor($key),
            'stages' => self::STAGES,
        ];
    }

    public function resolveKey(Resin $resin): string
    {
        if ($this->approvalService->isDone($resin)) {
            return self::KEY_DONE;
        }

        if ($this->approvalService->isPendingComplete($resin)) {
            return self::KEY_MANAGER;
        }

        if ($this->approvalService->isPendingManager($resin)) {
            return self::KEY_SUBMITTED;
        }

        return self::KEY_DRAFT;
    }

    public function stageIndexFor(string $key): int
    {
        return match ($key) {
            self::KEY_SUBMITTED => 1,
            self::KEY_MANAGER => 2,
            self::KEY_DONE => 3,
            default => 0,
        };
    }

    public function labelFor(string $key): string
    {
        foreach (self::STAGES as $stage) {
            if ($stage['key'] === $key) {
                return $stage['label'];
            }
        }

        return self::STAGES[0]['label'];
    }
}
