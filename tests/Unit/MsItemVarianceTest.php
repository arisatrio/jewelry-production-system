<?php

use App\Models\MsItem;
use App\Models\MsItemVariance;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ms item variance model uses the third connection and msitemvariance table', function () {
    $model = new MsItemVariance;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('msitemvariance')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('ms item variance table exists on third connection', function () {
    expect(Schema::connection('third')->hasTable('msitemvariance'))->toBeTrue();
});

test('ms item variance table has image column on third connection', function () {
    expect(Schema::connection('third')->hasColumn('msitemvariance', 'image'))->toBeTrue();
});

test('ms item variance belongs to ms item', function () {
    $item = MsItem::factory()->create([
        'name' => 'RelParent '.uniqid(),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'RelVariance '.uniqid(),
    ]);

    expect($variance->item)->toBeInstanceOf(MsItem::class)
        ->and($variance->item->row_id)->toBe($item->row_id)
        ->and($item->variances()->whereKey($variance->row_id)->exists())->toBeTrue();

    $variance->delete();
    $item->delete();
});
