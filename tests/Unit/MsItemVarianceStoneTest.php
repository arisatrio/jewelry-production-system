<?php

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\MsPosition;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ms item variance stone model uses third connection and table', function () {
    $model = new MsItemVarianceStone;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('msitemvariancestone')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('ms item variance stone table exists on third connection', function () {
    expect(Schema::connection('third')->hasTable('msitemvariancestone'))->toBeTrue();
});

test('ms item variance stone belongs to variance and shape', function () {
    $item = MsItem::factory()->create([
        'name' => 'RelStoneItem '.uniqid(),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'RelStoneVariance '.uniqid(),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
    ]);

    expect($stone->variance)->toBeInstanceOf(MsItemVariance::class)
        ->and($stone->variance->row_id)->toBe($variance->row_id)
        ->and($variance->stones()->whereKey($stone->row_id)->exists())->toBeTrue()
        ->and($stone->shape)->not->toBeNull()
        ->and($stone->shape_id)->toBe($stone->shape->row_id);

    $stone->delete();
    $variance->delete();
    $item->delete();
});

test('ms item variance stone belongs to position', function () {
    $item = MsItem::factory()->create([
        'name' => 'RelStonePosItem '.uniqid(),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'RelStonePosVariance '.uniqid(),
    ]);
    $position = MsPosition::factory()->create();
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'position_id' => $position->id,
    ]);

    expect($stone->position)->toBeInstanceOf(MsPosition::class)
        ->and($stone->position->id)->toBe($position->id)
        ->and($stone->position->nama)->toBe($position->nama);

    $stone->delete();
    $position->delete();
    $variance->delete();
    $item->delete();
});
