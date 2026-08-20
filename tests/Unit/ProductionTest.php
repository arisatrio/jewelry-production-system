<?php

use App\Http\Controllers\ProductionController;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

uses(TestCase::class);

test('production model uses the third connection and spk table', function () {
    $model = new Production;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('spk')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->getRouteKeyName())->toBe('spk_no')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('production model can query the spk table on third database', function () {
    expect(Schema::connection('third')->hasTable('spk'))->toBeTrue();

    $production = Production::query()->notDeleted()->first();

    expect($production)->not->toBeNull()
        ->and($production)->toBeInstanceOf(Production::class)
        ->and($production->row_id)->toBeInt();
});

test('production controller resolves image url from spk file name', function () {
    $method = new ReflectionMethod(ProductionController::class, 'productionImageUrl');

    expect($method->invoke(app(ProductionController::class), 'uploaded-spk.png'))
        ->toBe(rtrim((string) config('spk.production_image_base_url'), '/').'/uploaded-spk.png')
        ->and($method->invoke(app(ProductionController::class), null))
        ->toBeNull();
});
