<?php

use App\Models\Production;
use Illuminate\Support\Facades\Schema;
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
