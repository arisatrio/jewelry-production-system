<?php

namespace App\Support;

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SkuPrefixDiamondType;
use App\Models\SkuPrefixName;
use App\Models\SkuPrefixSize;
use App\Models\SkuPrefixStoneShape;
use App\Models\SkuPrefixStoneType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SkuMasterHierarchyBuilder
{
    private const EMPTY_LABEL = '—';

    /**
     * Folder levels in SKU order (gold color intentionally skipped).
     *
     * @var list<string>
     */
    private const LEVELS = [
        'category',
        'name',
        'size',
        'stone_shape',
        'stone_type',
        'diamond_type',
    ];

    /**
     * @var array<int, string>
     */
    private array $categoryLabels = [];

    /**
     * @var array<int, string>
     */
    private array $nameLabels = [];

    /**
     * @var array<int, string>
     */
    private array $sizeLabels = [];

    /**
     * @var array<int, string>
     */
    private array $stoneShapeLabels = [];

    /**
     * @var array<int, string>
     */
    private array $stoneTypeLabels = [];

    /**
     * @var array<int, string>
     */
    private array $diamondTypeLabels = [];

    /**
     * Build folder tree with lightweight SKU leaves (code + item original only).
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     count: int,
     *     children: list<array<string, mixed>>,
     *     skus: list<array{id: int, skuCode: string, itemOriginal: string}>
     * }>
     */
    public function build(?string $search = null): array
    {
        $this->hydratePrefixMaps();

        $search = trim((string) $search);

        $skus = SkuMaster::query()
            ->active()
            ->orderBy('sku_code')
            ->get([
                'id',
                'sku_code',
                'item_original',
                'category_prefix_id',
                'name_prefix_id',
                'size_prefix_id',
                'stone_shape_prefix_id',
                'stone_type_prefix_id',
                'diamond_type_prefix_id',
                'crt',
            ]);

        if ($search !== '') {
            $needle = Str::lower($search);
            $skus = $skus->filter(function (SkuMaster $sku) use ($needle): bool {
                $haystack = Str::lower(implode(' ', array_filter([
                    $sku->sku_code,
                    $sku->item_original,
                    $this->rawCategoryLabel($sku),
                    $this->rawNameLabel($sku),
                    $this->rawSizeLabel($sku),
                    $this->rawStoneShapeLabel($sku),
                    $this->rawStoneTypeLabel($sku),
                    $this->rawDiamondTypeLabel($sku),
                    $this->formatCrtLabel($sku->crt),
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        return $this->buildLevel($skus, 0, '');
    }

    /**
     * @param  Collection<int, SkuMaster>  $skus
     * @return list<array<string, mixed>>
     */
    private function buildLevel(Collection $skus, int $levelIndex, string $parentKey): array
    {
        $type = self::LEVELS[$levelIndex];
        $isLeafLevel = $levelIndex === count(self::LEVELS) - 1;

        $grouped = $skus->groupBy(
            fn (SkuMaster $sku): string => $this->labelFor($sku, $type),
        );

        $nodes = [];

        foreach ($grouped as $label => $groupSkus) {
            /** @var Collection<int, SkuMaster> $groupSkus */
            $key = ($parentKey !== '' ? $parentKey.'/' : '').$type.':'.$this->slug((string) $label);

            if ($isLeafLevel) {
                $skuItems = $groupSkus
                    ->sortBy([
                        fn (SkuMaster $sku): float => (float) ($sku->crt ?? 0),
                        fn (SkuMaster $sku): string => (string) $sku->sku_code,
                    ])
                    ->map(fn (SkuMaster $sku): array => $this->toSkuItem($sku))
                    ->values()
                    ->all();

                $nodes[] = [
                    'key' => $key,
                    'label' => (string) $label,
                    'type' => $type,
                    'count' => count($skuItems),
                    'children' => [],
                    'skus' => $skuItems,
                ];

                continue;
            }

            $children = $this->buildLevel($groupSkus, $levelIndex + 1, $key);

            $nodes[] = [
                'key' => $key,
                'label' => (string) $label,
                'type' => $type,
                'count' => $groupSkus->count(),
                'children' => $children,
                'skus' => [],
            ];
        }

        usort($nodes, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $nodes;
    }

    private function hydratePrefixMaps(): void
    {
        if ($this->categoryLabels !== []) {
            return;
        }

        $this->categoryLabels = SkuPrefixCategory::query()->pluck('category', 'id')->all();
        $this->nameLabels = SkuPrefixName::query()->pluck('name', 'id')->all();
        $this->sizeLabels = SkuPrefixSize::query()->pluck('size', 'id')->all();
        $this->stoneShapeLabels = SkuPrefixStoneShape::query()->pluck('stone_shape', 'id')->all();
        $this->stoneTypeLabels = SkuPrefixStoneType::query()->pluck('stone_type', 'id')->all();
        $this->diamondTypeLabels = SkuPrefixDiamondType::query()->pluck('diamond_type', 'id')->all();
    }

    private function labelFor(SkuMaster $sku, string $type): string
    {
        return match ($type) {
            'category' => $this->displayLabel($this->rawCategoryLabel($sku)),
            'name' => $this->displayLabel($this->rawNameLabel($sku)),
            'size' => $this->sizeLabel($sku),
            'stone_shape' => $this->displayLabel($this->rawStoneShapeLabel($sku)),
            'stone_type' => $this->displayLabel($this->rawStoneTypeLabel($sku)),
            'diamond_type' => $this->displayLabel($this->rawDiamondTypeLabel($sku)),
            default => self::EMPTY_LABEL,
        };
    }

    private function rawCategoryLabel(SkuMaster $sku): string
    {
        $id = $sku->category_prefix_id;

        return $id !== null ? (string) ($this->categoryLabels[(int) $id] ?? '') : '';
    }

    private function rawNameLabel(SkuMaster $sku): string
    {
        $id = $sku->name_prefix_id;

        return $id !== null ? (string) ($this->nameLabels[(int) $id] ?? '') : '';
    }

    private function rawSizeLabel(SkuMaster $sku): string
    {
        $id = $sku->size_prefix_id;

        return $id !== null ? (string) ($this->sizeLabels[(int) $id] ?? '') : '';
    }

    private function rawStoneShapeLabel(SkuMaster $sku): string
    {
        $id = $sku->stone_shape_prefix_id;

        return $id !== null ? (string) ($this->stoneShapeLabels[(int) $id] ?? '') : '';
    }

    private function rawStoneTypeLabel(SkuMaster $sku): string
    {
        $id = $sku->stone_type_prefix_id;

        return $id !== null ? (string) ($this->stoneTypeLabels[(int) $id] ?? '') : '';
    }

    private function rawDiamondTypeLabel(SkuMaster $sku): string
    {
        $id = $sku->diamond_type_prefix_id;

        return $id !== null ? (string) ($this->diamondTypeLabels[(int) $id] ?? '') : '';
    }

    /**
     * @return array{id: int, skuCode: string, itemOriginal: string}
     */
    private function toSkuItem(SkuMaster $sku): array
    {
        return [
            'id' => (int) $sku->id,
            'skuCode' => (string) $sku->sku_code,
            'itemOriginal' => (string) ($sku->item_original ?? ''),
        ];
    }

    private function sizeLabel(SkuMaster $sku): string
    {
        $size = trim($this->rawSizeLabel($sku));

        if ($size === '') {
            return self::EMPTY_LABEL;
        }

        return Str::of($size)->lower()->title()->toString();
    }

    private function formatCrtLabel(mixed $crt): string
    {
        if ($crt === null || $crt === '') {
            return '';
        }

        $numeric = (float) $crt;

        if ($numeric <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function displayLabel(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return self::EMPTY_LABEL;
        }

        return Str::of($text)->lower()->title()->toString();
    }

    private function slug(string $label): string
    {
        $slug = Str::slug($label);

        return $slug !== '' ? $slug : 'empty';
    }
}
