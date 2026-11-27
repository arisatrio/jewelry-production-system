<?php

use App\Models\Production;
use App\Support\RequestOrderRepository;
use App\Support\SpkService;

test('spk update production type endpoint changes stock spk to pesanan', function () {
    $order = app(RequestOrderRepository::class)->search('', 1)->first();

    if ($order === null) {
        $this->markTestSkipped('Tidak ada request order untuk pengujian.');
    }

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'request_order_no' => null,
        'request_stock_no' => null,
        'is_deleted' => 0,
    ]);

    try {
        $this->postJson(route('spk.production-type.update', $production->row_id), [
            'spk_type' => 'Pesanan',
            'request_order_no' => $order['docNo'],
        ])
            ->assertOk()
            ->assertJsonPath('rowId', (int) $production->row_id);

        $production->refresh();

        expect($production->spk_type)->toBe('Pesanan')
            ->and($production->request_order_no)->toBe($order['docNo']);
    } finally {
        $production->delete();
    }
});

test('spk update production type validates pesanan requires request order', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'is_deleted' => 0,
    ]);

    try {
        $this->postJson(route('spk.production-type.update', $production->row_id), [
            'spk_type' => 'Pesanan',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_order_no']);
    } finally {
        $production->delete();
    }
});

test('spk update production type rejects invalid type', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'is_deleted' => 0,
    ]);

    try {
        $this->postJson(route('spk.production-type.update', $production->row_id), [
            'spk_type' => 'Invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['spk_type']);
    } finally {
        $production->delete();
    }
});

test('spk service types list matches form options', function () {
    expect(SpkService::TYPES)->toContain('Pesanan', 'Stock');
});
