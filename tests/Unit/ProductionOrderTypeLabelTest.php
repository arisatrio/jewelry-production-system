<?php

use App\Models\Production;
use App\Support\ProductionOrderTypeLabel;
use App\Support\RequestOrderRepository;

test('production order type label returns customer name for stock spk', function () {
    $production = new Production([
        'spk_type' => 'Stock',
        'customer_name' => 'Maureen Langworth PhD',
        'request_order_no' => null,
    ]);

    $label = app(ProductionOrderTypeLabel::class)->forProduction($production);

    expect($label)->toBe('Maureen Langworth PhD');
});

test('production order type label returns pesanan display label with payment status', function () {
    $repository = Mockery::mock(RequestOrderRepository::class);
    $repository->shouldReceive('displayLabelByDocNo')
        ->once()
        ->with('DP-0009428', 'Friska Amelia')
        ->andReturn('DP-0009428 (Friska Amelia) (Lunas)');

    $production = new Production([
        'spk_type' => 'Pesanan',
        'customer_name' => 'Friska Amelia',
        'request_order_no' => 'DP-0009428',
    ]);

    $label = (new ProductionOrderTypeLabel($repository))->forProduction($production);

    expect($label)->toBe('DP-0009428 (Friska Amelia) (Lunas)');
});

test('production order type label returns dash when production is null', function () {
    expect(app(ProductionOrderTypeLabel::class)->forProduction(null))->toBe('—');
});
