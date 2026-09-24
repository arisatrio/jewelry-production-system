<?php

namespace App\Support;

use App\Models\SpkProcessSlaTarget;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SpkProcessSlaResolver
{
    public const CACHE_KEY = 'spk.process_sla_targets';

    public function __construct(
        private readonly SpkProcessMapper $processMapper = new SpkProcessMapper,
    ) {}

    /**
     * @return list<array{
     *     processKey: string,
     *     label: string,
     *     workingDays: int,
     *     source: 'database'|'config',
     *     updatedBy: string|null,
     *     updatedAt: string|null
     * }>
     */
    public function forSettings(): array
    {
        $stored = $this->storedByProcessKey();
        $defaults = $this->defaults();
        $rows = [];

        foreach ($this->processMapper->tabs() as $tab) {
            $processKey = $tab['key'];
            $record = $stored[$processKey] ?? null;
            $workingDays = $record?->working_days ?? ($defaults[$processKey] ?? 0);

            $rows[] = [
                'processKey' => $processKey,
                'label' => $tab['label'],
                'workingDays' => (int) $workingDays,
                'source' => $record !== null ? 'database' : 'config',
                'updatedBy' => $record?->updated_by,
                'updatedAt' => $record?->updated_at?->format('d/m/Y H:i'),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function targets(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $defaults = $this->defaults();
            $targets = [];

            foreach ($this->processMapper->tabs() as $tab) {
                $processKey = $tab['key'];
                $targets[$processKey] = (int) ($defaults[$processKey] ?? 0);
            }

            foreach ($this->storedByProcessKey() as $processKey => $record) {
                $targets[$processKey] = (int) $record->working_days;
            }

            return $targets;
        });
    }

    public function targetFor(string $processKey): ?int
    {
        $targets = $this->targets();

        if (! array_key_exists($processKey, $targets)) {
            return null;
        }

        return $targets[$processKey];
    }

    /**
     * Tanggal deadline SLA proses: hari kerja ke-N sejak mulai proses.
     */
    public function slaDeadlineDate(CarbonInterface $processStartedAt, int $workingDays): Carbon
    {
        return $this->addWorkingDays($processStartedAt, max(0, $workingDays));
    }

    /**
     * Tanggal H-1 SLA proses: hari kerja ke-(N-1) sejak mulai proses.
     * Mulai hari itu SPK masuk kategori "perlu perhatian".
     */
    public function h1ThresholdDate(CarbonInterface $processStartedAt, int $workingDays): Carbon
    {
        $daysUntilH1 = max(0, $workingDays - 1);

        return $this->addWorkingDays($processStartedAt, $daysUntilH1);
    }

    /**
     * True bila hari ini sudah di H-1 SLA proses atau sudah lewat.
     */
    public function isAtOrPastH1(
        CarbonInterface $processStartedAt,
        int $workingDays,
        ?CarbonInterface $today = null,
    ): bool {
        if ($workingDays <= 0) {
            return false;
        }

        $today = Carbon::parse(($today ?? now())->toDateTimeString())->startOfDay();
        $threshold = $this->h1ThresholdDate($processStartedAt, $workingDays)->startOfDay();

        return $today->gte($threshold);
    }

    /**
     * True bila hari ini sudah melewati deadline SLA proses (setelah hari deadline).
     */
    public function isPastSla(
        CarbonInterface $processStartedAt,
        int $workingDays,
        ?CarbonInterface $today = null,
    ): bool {
        if ($workingDays <= 0) {
            return false;
        }

        $today = Carbon::parse(($today ?? now())->toDateTimeString())->startOfDay();
        $deadline = $this->slaDeadlineDate($processStartedAt, $workingDays)->startOfDay();

        return $today->gt($deadline);
    }

    /**
     * Tambah N hari kerja (Senin–Jumat), sama seperti SpkService::calculateEstimatedDelivery.
     */
    private function addWorkingDays(CarbonInterface $start, int $workingDays): Carbon
    {
        $date = Carbon::parse($start)->startOfDay();

        if ($workingDays <= 0) {
            return $date;
        }

        $added = 0;

        while ($added < $workingDays) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $added++;
            }
        }

        return $date;
    }

    public function totalWorkingDays(): int
    {
        return array_sum($this->targets());
    }

    /**
     * @param  array<string, int>  $targets  process_key => working_days
     */
    public function sync(array $targets, ?string $actor = null): void
    {
        $allowedKeys = array_column($this->processMapper->tabs(), 'key');

        foreach ($allowedKeys as $processKey) {
            if (! array_key_exists($processKey, $targets)) {
                continue;
            }

            SpkProcessSlaTarget::query()->updateOrCreate(
                ['process_key' => $processKey],
                [
                    'working_days' => (int) $targets[$processKey],
                    'updated_by' => $actor,
                ],
            );
        }

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, int>
     */
    private function defaults(): array
    {
        /** @var array<string, int|string> $defaults */
        $defaults = config('spk_processes.sla_defaults', []);

        return array_map(
            static fn (mixed $days): int => (int) $days,
            $defaults,
        );
    }

    /**
     * @return array<string, SpkProcessSlaTarget>
     */
    private function storedByProcessKey(): array
    {
        if (! Schema::connection('third')->hasTable('spk_process_sla_targets')) {
            return [];
        }

        return SpkProcessSlaTarget::query()
            ->get()
            ->keyBy('process_key')
            ->all();
    }
}
