<?php

use App\Models\SkuMaster;
use App\Models\SkuMasterDiamond;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('sku master diamond model uses the second connection and table', function () {
    $model = new SkuMasterDiamond;

    expect($model->getConnectionName())->toBe('second')
        ->and($model->getTable())->toBe('sku_master_diamond')
        ->and($model->getKeyName())->toBe('line_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('sku master diamond table exists on second connection', function () {
    expect(Schema::connection('second')->hasTable('sku_master_diamond'))->toBeTrue();
});

test('sku master has many diamonds', function () {
    $sku = SkuMaster::factory()->create();
    $diamond = SkuMasterDiamond::factory()->create([
        'row_id' => $sku->id,
        'diamond_type' => 'R',
        'grain' => 4,
        'grade' => '0.250',
    ]);

    expect($sku->diamonds()->whereKey($diamond->line_id)->exists())->toBeTrue()
        ->and($diamond->sku)->toBeInstanceOf(SkuMaster::class)
        ->and($diamond->sku?->id)->toBe($sku->id);

    $diamond->delete();
    $sku->delete();
});
