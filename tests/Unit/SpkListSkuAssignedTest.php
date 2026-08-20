<?php

use App\Http\Controllers\ProductionController;
use App\Models\Production;
use App\Models\SkuMaster;
use Tests\TestCase;

uses(TestCase::class);

function listHasAssignedSku(Production $production): bool
{
    $method = new ReflectionMethod(ProductionController::class, 'listHasAssignedSku');

    return $method->invoke(app(ProductionController::class), $production);
}

test('new system production without sku is marked unassigned', function () {
    $production = new Production([
        'is_from_new_system' => 1,
        'sku_id' => null,
    ]);

    expect(listHasAssignedSku($production))->toBeFalse();
});

test('new system production with sku is marked assigned', function () {
    $production = new Production([
        'is_from_new_system' => 1,
        'sku_id' => 12,
    ]);
    $production->setRelation('sku', new SkuMaster([
        'id' => 12,
        'sku_code' => '2T-LDR-ATF-REG',
    ]));

    expect(listHasAssignedSku($production))->toBeTrue();
});

test('legacy production without sku is marked unassigned', function () {
    $production = new Production([
        'is_from_new_system' => 0,
        'sku_id' => null,
    ]);

    expect(listHasAssignedSku($production))->toBeFalse();
});
