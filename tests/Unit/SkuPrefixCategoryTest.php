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
