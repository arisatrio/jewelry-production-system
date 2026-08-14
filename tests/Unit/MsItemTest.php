<?php

use App\Models\MsItem;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ms item model uses the third connection and msitem table', function () {
    $model = new MsItem;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('msitem')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->usesTimestamps())->toBeFalse();
});

test('production item relationship resolves msitem by item_id', function () {
    expect(Schema::connection('third')->hasTable('msitem'))->toBeTrue();

    $production = Production::query()
        ->notDeleted()
        ->whereNotNull('item_id')
        ->where('item_id', '>', 0)
        ->with('item')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->item)->toBeInstanceOf(MsItem::class)
        ->and($production->item->row_id)->toBe($production->item_id);
});
