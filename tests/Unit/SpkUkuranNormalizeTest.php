<?php

use App\Support\SpkService;
use Tests\TestCase;

uses(TestCase::class);

test('normalize ukuran input splits combined label stuck in diameter', function () {
    $normalized = app(SpkService::class)->normalizeUkuranInput(
        ' / Panjang 150 / ',
        'Panjang 150',
        null,
    );

    expect($normalized)->toBe([
        'diameter' => null,
        'dimensi' => 'Panjang 150',
        'ring_size' => null,
    ]);
});

test('normalize ukuran input keeps valid separate fields', function () {
    $normalized = app(SpkService::class)->normalizeUkuranInput(
        '12',
        'Panjang 150',
        '16',
    );

    expect($normalized)->toBe([
        'diameter' => '12',
        'dimensi' => 'Panjang 150',
        'ring_size' => '16',
    ]);
});

test('parse ukuran label keeps ring size in third slot when diameter and dimensi empty', function () {
    expect(app(SpkService::class)->parseUkuranLabel(' /  / Size 12 HK'))->toBe([
        'diameter' => null,
        'dimensi' => null,
        'ring_size' => 'Size 12 HK',
    ]);
});

test('parse ukuran label handles collapsed slashes without spaces', function () {
    expect(app(SpkService::class)->parseUkuranLabel('//Size 12 HK'))->toBe([
        'diameter' => null,
        'dimensi' => null,
        'ring_size' => 'Size 12 HK',
    ]);
});
