<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SkuPrefixDiamondType;
use App\Models\SkuPrefixName;
use App\Models\SkuPrefixSize;
use App\Models\SkuPrefixStoneShape;
use App\Models\SkuPrefixStoneType;
use App\Support\SkuMasterHierarchyBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('hierarchy builder groups skus by all prefix elements except gold', function () {
    $category = SkuPrefixCategory::query()->create([
        'category' => 'Earring Hierarchy',
        'prefix' => 'EH'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);
    $name = SkuPrefixName::query()->create([
        'name' => 'Aura Classic',
        'prefix' => 'AC'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);
    $size = SkuPrefixSize::query()->create([
        'size' => 'Fancy',
        'prefix' => 'FY'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);
    $shape = SkuPrefixStoneShape::query()->create([
        'stone_shape' => 'Round Brilliant',
        'prefix' => 'RB'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);
    $stoneType = SkuPrefixStoneType::query()->create([
        'stone_type' => 'Diamond',
        'prefix' => 'DM'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);
    $diamondType = SkuPrefixDiamondType::query()->create([
        'diamond_type' => 'Natural',
        'prefix' => 'NT'.fake()->unique()->lexify('??'),
        'usage_count' => 0,
        'is_active' => 1,
    ]);

    $sku = SkuMaster::factory()->create([
        'sku_code' => 'WG-'.$category->prefix.'-'.$name->prefix.'-'.$size->prefix.'-'.$shape->prefix.'-'.$stoneType->prefix.'-'.$diamondType->prefix.'-030',
        'item_original' => 'EAR STUD AURA CLASSIC FANCY 0.30',
        'category_prefix_id' => $category->id,
        'name_prefix_id' => $name->id,
        'size_prefix_id' => $size->id,
        'stone_shape_prefix_id' => $shape->id,
        'stone_type_prefix_id' => $stoneType->id,
        'diamond_type_prefix_id' => $diamondType->id,
        'crt' => '0.30',
        'is_active' => 1,
        'is_deleted' => 0,
    ]);

    $tree = (new SkuMasterHierarchyBuilder)->build('Aura Classic Fancy');

    expect($tree)->not->toBeEmpty();

    $categoryNode = collect($tree)->firstWhere('label', 'Earring Hierarchy');
    expect($categoryNode)->not->toBeNull()
        ->and($categoryNode['type'])->toBe('category');

    $nameNode = collect($categoryNode['children'])->firstWhere('label', 'Aura Classic');
    expect($nameNode)->not->toBeNull();

    $sizeNode = collect($nameNode['children'])->firstWhere('label', 'Fancy');
    expect($sizeNode)->not->toBeNull();

    $shapeNode = collect($sizeNode['children'])->firstWhere('label', 'Round Brilliant');
    expect($shapeNode)->not->toBeNull()
        ->and($shapeNode['type'])->toBe('stone_shape');

    $stoneTypeNode = collect($shapeNode['children'])->firstWhere('label', 'Diamond');
    expect($stoneTypeNode)->not->toBeNull()
        ->and($stoneTypeNode['type'])->toBe('stone_type');

    $diamondTypeNode = collect($stoneTypeNode['children'])->firstWhere('label', 'Natural');
    expect($diamondTypeNode)->not->toBeNull()
        ->and($diamondTypeNode['type'])->toBe('diamond_type')
        ->and($diamondTypeNode['children'])->toBeEmpty()
        ->and($diamondTypeNode['skus'])->not->toBeEmpty();

    $skuItem = collect($diamondTypeNode['skus'])->firstWhere('id', $sku->id);

    expect($skuItem)->not->toBeNull()
        ->and($skuItem)->toHaveKeys(['id', 'skuCode', 'itemOriginal'])
        ->and($skuItem['skuCode'])->toBe($sku->sku_code)
        ->and($skuItem['itemOriginal'])->toBe('EAR STUD AURA CLASSIC FANCY 0.30');

    $sku->delete();
    $diamondType->delete();
    $stoneType->delete();
    $shape->delete();
    $size->delete();
    $name->delete();
    $category->delete();
});
