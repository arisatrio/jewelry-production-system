<?php

use App\Models\MsShape;
use App\Models\MsStone;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ms stone model uses the third connection and msstone table', function () {
    $model = new MsStone;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('msstone')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('ms stone belongs to shape', function () {
    expect(Schema::connection('third')->hasTable('msstone'))->toBeTrue();

    $stone = MsStone::query()
        ->notDeleted()
        ->whereNotNull('shape_id')
        ->with('shape')
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape)->toBeInstanceOf(MsShape::class);
});
