<?php

namespace App\Support;

use App\Models\FinishingHandmade;
use App\Models\FinishingShrinkAllowance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinishingShrinkAllowanceSettings
{
    public const CACHE_KEY = 'finishing.shrink_allowances';

    /**
     * Persentase jatah susut bawaan, urut mengikuti kategori barang finishing.
     *
     * @var array<string, list<float>>
     */
    private const DEFAULT_PERCENTS = [
        'Pasang / Ganti Chain' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Setting Stopper' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Setting Engsel' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Pasang Batu' => [1.5, 1.5, 1.5, 1.5, 1.5, 1.5, 1.5, 1.5],
        'Finishing 1' => [5, 5, 5, 5, 4, 4, 3, 2],
        'Finishing 2' => [5, 5, 5, 5, 4, 4, 3, 2],
        'Finishing 3' => [5, 5, 5, 5, 4, 4, 3, 2],
        'Finishing Komponen' => [5, 5, 5, 5, 4, 4, 3, 2],
        'Finishing Rangka' => [5, 5, 5, 5, 4, 4, 3, 2],
        'Poles / Doff / Permukaan' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Bolong' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Patah / Putus / Longgar' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Bentuk / Konstruksi' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Kuncian' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair / Ganti Kuku' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Umum' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Komponen' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Repair Berat / Ketebalan' => [3, 3, 3, 3, 2, 2, 2, 2],
        'General Check Up' => [1, 1, 1, 1, 0.5, 0.5, 0.5, 0.5],
        'Resize Ukuran (HK)' => [2, 2, 2, 2, 1, 1, 1, 1],
        'Ubah Panjang / Extension' => [2, 2, 2, 2, 1, 1, 1, 1],
        'Potong / Tambah Butir' => [0.5, 0.5, 0.5, 0.5, 0.5, 0.5, 0.5, 0.5],
        'Lainnya' => [0, 0, 0, 0, 0, 0, 0, 0],
    ];

    /**
     * @return list<string>
     */
    public function itemCategories(): array
    {
        return FinishingHandmade::itemCategoryOptions();
    }

    /**
     * @return list<array{workCategory: string, workType: string, percents: array<string, string>}>
     */
    public function rows(): array
    {
        $stored = $this->storedByKey();
        $rows = [];

        foreach (FinishingHandmade::workTypesByCategory() as $workCategory => $workTypes) {
            foreach ($workTypes as $workType) {
                $percents = [];

                foreach ($this->itemCategories() as $itemCategory) {
                    $record = $stored[$this->key($workType, $itemCategory)] ?? null;
                    $percent = $record !== null
                        ? (string) $record->allowance_percent
                        : $this->defaultPercent($workType, $itemCategory);

                    $percents[$itemCategory] = $percent ?? '';
                }

                $rows[] = [
                    'workCategory' => $workCategory,
                    'workType' => $workType,
                    'percents' => $percents,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{work_type: string, item_category: string, allowance_percent: string}>
     */
    public function formCells(): array
    {
        $cells = [];

        foreach ($this->rows() as $row) {
            foreach ($this->itemCategories() as $itemCategory) {
                $cells[] = [
                    'work_type' => $row['workType'],
                    'item_category' => $itemCategory,
                    'allowance_percent' => $row['percents'][$itemCategory] ?? '',
                ];
            }
        }

        return $cells;
    }

    /**
     * @return array{by: string|null, at: string}|null
     */
    public function lastUpdate(): ?array
    {
        $latest = collect($this->storedByKey())
            ->sortByDesc(fn (FinishingShrinkAllowance $row): int => $row->updated_at?->getTimestamp() ?? 0)
            ->first();

        if ($latest?->updated_at === null) {
            return null;
        }

        return [
            'by' => $latest->updated_by,
            'at' => $latest->updated_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * Lookup jatah susut per jenis pekerjaan lalu kategori barang.
     *
     * @return array<string, array<string, string>>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach ($this->allowances() as $key => $percent) {
            [$workType, $itemCategory] = explode('|', $key, 2);
            $matrix[$workType][$itemCategory] = $percent;
        }

        return $matrix;
    }

    public function percentFor(string $workType, string $itemCategory): ?string
    {
        return $this->allowances()[$this->key($workType, $itemCategory)] ?? null;
    }

    public function defaultPercent(string $workType, string $itemCategory): ?string
    {
        $percents = self::DEFAULT_PERCENTS[$workType] ?? null;

        if ($percents === null) {
            return null;
        }

        $index = array_search($itemCategory, $this->itemCategories(), true);

        if ($index === false || ! array_key_exists($index, $percents)) {
            return null;
        }

        return $this->formatPercent($percents[$index]);
    }

    /**
     * @param  list<array{work_type: string, item_category: string, allowance_percent: float|string}>  $cells
     */
    public function sync(array $cells, string $actor): void
    {
        $categoryByType = $this->categoryByWorkType();
        $now = now();
        $records = [];

        foreach ($cells as $cell) {
            $workType = (string) $cell['work_type'];
            $itemCategory = (string) $cell['item_category'];

            if (! isset($categoryByType[$workType])) {
                continue;
            }

            $records[] = [
                'work_category' => $categoryByType[$workType],
                'work_type' => $workType,
                'item_category' => $itemCategory,
                'allowance_percent' => $this->formatPercent((float) $cell['allowance_percent']),
                'updated_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('third')->transaction(function () use ($records): void {
            foreach (array_chunk($records, 100) as $chunk) {
                FinishingShrinkAllowance::query()->upsert(
                    $chunk,
                    ['work_type', 'item_category'],
                    ['work_category', 'allowance_percent', 'updated_by', 'updated_at'],
                );
            }
        });

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private function allowances(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = $this->storedByKey();
            $resolved = [];

            foreach (FinishingHandmade::workTypesByCategory() as $workTypes) {
                foreach ($workTypes as $workType) {
                    foreach ($this->itemCategories() as $itemCategory) {
                        $key = $this->key($workType, $itemCategory);
                        $record = $stored[$key] ?? null;
                        $percent = $record !== null
                            ? (string) $record->allowance_percent
                            : $this->defaultPercent($workType, $itemCategory);

                        if ($percent !== null && $percent !== '') {
                            $resolved[$key] = $this->formatPercent((float) $percent);
                        }
                    }
                }
            }

            return $resolved;
        });
    }

    /**
     * @return array<string, string>
     */
    private function categoryByWorkType(): array
    {
        $categories = [];

        foreach (FinishingHandmade::workTypesByCategory() as $workCategory => $workTypes) {
            foreach ($workTypes as $workType) {
                $categories[$workType] = $workCategory;
            }
        }

        return $categories;
    }

    /**
     * @return array<string, FinishingShrinkAllowance>
     */
    private function storedByKey(): array
    {
        if (! Schema::connection('third')->hasTable('finishing_shrink_allowances')) {
            return [];
        }

        return FinishingShrinkAllowance::query()
            ->get()
            ->keyBy(fn (FinishingShrinkAllowance $row): string => $this->key($row->work_type, $row->item_category))
            ->all();
    }

    private function key(string $workType, string $itemCategory): string
    {
        return $workType.'|'.$itemCategory;
    }

    private function formatPercent(float $percent): string
    {
        return number_format($percent, 2, '.', '');
    }
}
