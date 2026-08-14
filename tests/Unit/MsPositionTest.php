<?php

use App\Models\MsPosition;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ms position model uses third connection and msposition table', function () {
    $model = new MsPosition;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('msposition')
        ->and($model->getKeyName())->toBe('id')
        ->and($model->usesTimestamps())->toBeTrue();
});

test('ms position table exists on third connection', function () {
    expect(Schema::connection('third')->hasTable('msposition'))->toBeTrue();
});

test('ms position can be created with nama', function () {
    $position = MsPosition::factory()->create();

    expect($position->exists)->toBeTrue()
        ->and($position->nama)->not->toBeEmpty()
        ->and($position->created_at)->not->toBeNull();

    $position->delete();
});
