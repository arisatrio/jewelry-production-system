<?php

use App\Models\DiamondMounting;
use Tests\TestCase;

uses(TestCase::class);
use App\Models\Production;
use App\Support\SpkProductionWeightDisplay;

test('emas weight equals last weight minus mounting stone weight', function () {
    $production = Production::factory()->create([
        'last_weight' => 5.0,
        'is_deleted' => 0,
    ]);

    DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'weight_frame' => '4.00',
        'total_weigth_frame_diamond' => '5.00',
        'weight_diamond' => '1.00',
        'is_deleted' => 0,
    ]);

    $display = app(SpkProductionWeightDisplay::class)->formattedForProduction($production);

    expect($display['emasWeight'])->toBe('4.00')
        ->and($display['finishedGoodsWeight'])->toBe('5.00');

    $production->delete();
});

test('emas weight equals last weight when no mounting stone data exists', function () {
    $production = Production::factory()->create([
        'last_weight' => 3.2,
        'is_deleted' => 0,
    ]);

    $display = app(SpkProductionWeightDisplay::class)->formattedForProduction($production);

    expect($display['emasWeight'])->toBe('3.20')
        ->and($display['finishedGoodsWeight'])->toBe('3.20');

    $production->delete();
});
