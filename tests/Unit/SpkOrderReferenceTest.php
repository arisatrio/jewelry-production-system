<?php

use App\Models\Production;
use App\Support\SpkOrderReference;
use Tests\TestCase;

uses(TestCase::class);

test('pesanan spk resolves order number with customer name', function () {
    $production = new Production([
        'spk_type' => 'Pesanan',
        'request_order_no' => 'DP-00123',
        'customer_name' => 'Budi',
    ]);

    expect(SpkOrderReference::label($production))->toBe('DP-00123 (Budi)');
});

test('pesanan spk without customer name resolves order number only', function () {
    $production = new Production([
        'spk_type' => 'Pesanan',
        'request_order_no' => 'DP-00123',
        'customer_name' => '  ',
    ]);

    expect(SpkOrderReference::label($production))->toBe('DP-00123');
});

test('non pesanan or missing order number resolves to null', function (?string $spkType, ?string $orderNo) {
    $production = new Production([
        'spk_type' => $spkType,
        'request_order_no' => $orderNo,
        'customer_name' => 'Budi',
    ]);

    expect(SpkOrderReference::label($production))->toBeNull();
})->with([
    'stok' => ['Stok', 'DP-00123'],
    'tanpa tipe' => [null, 'DP-00123'],
    'nomor kosong' => ['Pesanan', null],
    'nomor placeholder' => ['Pesanan', '-'],
]);

test('missing production resolves to null', function () {
    expect(SpkOrderReference::label(null))->toBeNull();
});
