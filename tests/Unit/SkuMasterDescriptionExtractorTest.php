<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SkuPrefixDiamondType;
use App\Models\SkuPrefixGoldColor;
use App\Models\SkuPrefixName;
use App\Models\SkuPrefixSize;
use App\Models\SkuPrefixStoneShape;
use App\Models\SkuPrefixStoneType;
use App\Support\SkuMasterDescriptionExtractor;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('sku description extractor builds text from sku component names', function () {
    $sku = SkuMaster::factory()->make([
        'sku_code' => 'RG-BGL-NTZ-ASM-HRS-DMD-DS-030',
        'item_original' => 'Legacy item original',
        'crt' => '0.3',
    ]);

    $sku->setRelation('categoryPrefix', new SkuPrefixCategory([
        'category' => 'BANGLE',
    ]));
    $sku->setRelation('namePrefix', new SkuPrefixName([
        'name' => 'NETIZEN',
    ]));
    $sku->setRelation('sizePrefix', new SkuPrefixSize([
        'size' => 'Asimetris',
    ]));
    $sku->setRelation('stoneShapePrefix', new SkuPrefixStoneShape([
        'stone_shape' => 'HEART',
    ]));
    $sku->setRelation('stoneTypePrefix', new SkuPrefixStoneType([
        'stone_type' => 'DIAMOND',
    ]));
    $sku->setRelation('diamondTypePrefix', new SkuPrefixDiamondType([
        'diamond_type' => 'Dossier',
    ]));
    $sku->setRelation('goldColorPrefix', new SkuPrefixGoldColor([
        'gold_color' => 'ROSE GOLD',
    ]));

    expect(app(SkuMasterDescriptionExtractor::class)->extract($sku))
        ->toBe('Rose Gold Bangle Netizen Asimetris Heart Diamond Dossier 0.3');
});

test('sku description extractor omits regular size prefix', function () {
    $sku = SkuMaster::factory()->make([
        'sku_code' => 'WG-PDT-STR-REG-RDB-DMD-DS-050',
        'item_original' => 'PDT SOLITAIRE ROUND 0.5 WG',
        'crt' => '0.5',
    ]);

    $sku->setRelation('categoryPrefix', new SkuPrefixCategory([
        'category' => 'PENDANT',
    ]));
    $sku->setRelation('namePrefix', new SkuPrefixName([
        'name' => 'SOLITAIRE',
    ]));
    $sku->setRelation('sizePrefix', new SkuPrefixSize([
        'size' => 'Regular',
    ]));
    $sku->setRelation('stoneShapePrefix', new SkuPrefixStoneShape([
        'stone_shape' => 'ROUND',
    ]));
    $sku->setRelation('stoneTypePrefix', new SkuPrefixStoneType([
        'stone_type' => 'DIAMOND',
    ]));
    $sku->setRelation('diamondTypePrefix', new SkuPrefixDiamondType([
        'diamond_type' => 'Dossier',
    ]));
    $sku->setRelation('goldColorPrefix', new SkuPrefixGoldColor([
        'gold_color' => 'WHITE GOLD',
    ]));

    expect(app(SkuMasterDescriptionExtractor::class)->extract($sku))
        ->toBe('White Gold Pendant Solitaire Round Diamond Dossier 0.5');
});

test('sku description extractor falls back to item original when prefixes are missing', function () {
    $sku = SkuMaster::factory()->make([
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'FALLBACK ITEM '.Str::upper(Str::random(4)),
    ]);

    expect(app(SkuMasterDescriptionExtractor::class)->extract($sku))
        ->toBe($sku->item_original);
});
