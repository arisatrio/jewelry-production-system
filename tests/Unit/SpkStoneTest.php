<?php

use App\Models\MsShape;
use App\Models\Production;
use App\Models\SpkStone;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('spk stone model uses the third connection and spkstone table', function () {
    $model = new SpkStone;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('spkstone')
        ->and($model->getKeyName())->toBe('line_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('spk stone belongs to production and shape', function () {
    expect(Schema::connection('third')->hasTable('spkstone'))->toBeTrue();

    $stone = SpkStone::query()
        ->notDeleted()
        ->with(['production', 'shape'])
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone->production)->toBeInstanceOf(Production::class)
        ->and($stone->shape)->toBeInstanceOf(MsShape::class);
});

test('production stones relationship returns related stones', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $production = Production::query()->find($stone->row_id);

    expect($production)->not->toBeNull()
        ->and($production->stones()->notDeleted()->count())->toBeGreaterThan(0);
});
