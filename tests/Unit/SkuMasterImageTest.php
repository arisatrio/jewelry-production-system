<?php

use App\Models\SkuMaster;

test('sku master prefers design_image for display url', function () {
    $sku = SkuMaster::factory()->make([
        'design_image' => 'designs/item-001.jpg',
        'image_url' => 'https://example.com/fallback.jpg',
        'catalog_image' => 'catalog/item-001.jpg',
    ]);

    expect($sku->resolvedImageReference())->toBe('designs/item-001.jpg')
        ->and($sku->resolvedImageUrl('https://cdn.example.com/assets/'))
        ->toBe('https://cdn.example.com/assets/designs/item-001.jpg');
});

test('sku master ignores image_url and catalog_image when design_image is empty', function () {
    $sku = SkuMaster::factory()->make([
        'design_image' => null,
        'image_url' => 'https://example.com/fallback.jpg',
        'catalog_image' => 'catalog/item-001.jpg',
    ]);

    expect($sku->resolvedImageReference())->toBeNull()
        ->and($sku->resolvedImageUrl())->toBeNull();
});

test('sku master resolves jwcad file from file_jwlcad', function () {
    $sku = SkuMaster::factory()->make([
        'file_jwlcad' => 'JWC-ATF-001',
    ]);

    expect($sku->resolvedJwcadFile())->toBe('JWC-ATF-001');
});

test('sku master resolves image filename from design_image when image_filename empty', function () {
    $sku = SkuMaster::factory()->make([
        'image_filename' => null,
        'design_image' => '1782887215_design.jpg',
    ]);

    expect($sku->resolvedImageFileName())->toBe('1782887215_design.jpg');
});
