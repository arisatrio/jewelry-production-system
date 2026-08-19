<?php

use App\Models\MsShape;
use App\Models\SkuMasterDiamond;
use App\Support\SkuMasterDiamondMapper;
use Tests\TestCase;

uses(TestCase::class);

test('sku diamond mapper resolves exact msshape codes', function () {
    $shape = MsShape::query()->notDeleted()->where('code', 'R')->first();

    expect($shape)->not->toBeNull();

    $resolved = (new SkuMasterDiamondMapper)->resolveShape('R');

    expect($resolved)->not->toBeNull()
        ->and($resolved?->row_id)->toBe($shape->row_id);
});

test('sku diamond mapper resolves common diamond type aliases', function (string $code, string $expectedShapeCode) {
    $shape = MsShape::query()->notDeleted()->where('code', $expectedShapeCode)->first();

    expect($shape)->not->toBeNull();

    $resolved = (new SkuMasterDiamondMapper)->resolveShape($code);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->code)->toBe($expectedShapeCode);
})->with([
    ['RD', 'R'],
    ['CU', 'CS'],
    ['CUS', 'CS'],
    ['RA', 'RAD'],
    ['EME', 'EM'],
    ['ASC', 'ASH'],
]);

test('sku diamond mapper maps diamond rows to form stones', function () {
    $shape = MsShape::query()->notDeleted()->where('code', 'R')->first();

    expect($shape)->not->toBeNull();

    $diamond = new SkuMasterDiamond([
        'grain' => 12,
        'grade' => '0.110',
        'diamond_type' => 'RD',
        'diameter' => '1.50',
        'position' => 'Center',
    ]);

    $mapped = (new SkuMasterDiamondMapper)->toFormStone($diamond);

    expect($mapped['shapeId'])->toBe((string) $shape->row_id)
        ->and($mapped['pcs'])->toBe('12')
        ->and($mapped['caratPerPcs'])->toBe('0.110')
        ->and($mapped['size'])->toBe('1.50')
        ->and($mapped['positionNama'])->toBe('Center');
});
