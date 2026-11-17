<?php

use App\Models\SkuPrefixCategory;
use Tests\TestCase;

uses(TestCase::class);

test('sku prefix category display name is uppercase', function () {
    $category = new SkuPrefixCategory([
        'category' => 'brooche',
        'prefix' => 'BRO',
    ]);

    expect($category->displayName())->toBe('BROOCHE (BRO)');
});

test('sku prefix category display name keeps dash when empty', function () {
    expect((new SkuPrefixCategory)->displayName())->toBe('-');
});

test('sku prefix category chart label omits prefix abbreviation', function () {
    $category = new SkuPrefixCategory([
        'category' => 'Ladies Ring',
        'prefix' => 'LR',
    ]);

    expect($category->displayName())->toBe('LADIES RING (LR)')
        ->and($category->chartLabel())->toBe('LADIES RING');
});
