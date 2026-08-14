<?php

use App\Models\MsItem;

test('ms item factory creates a record on the third connection', function () {
    $item = MsItem::factory()->create([
        'name' => 'Factory Item '.uniqid(),
    ]);

    expect($item->exists)->toBeTrue()
        ->and($item->getConnectionName())->toBe('third')
        ->and($item->is_deleted)->toBe(0);

    $item->delete();
});

test('ms item factory deleted state marks the record as deleted', function () {
    $item = MsItem::factory()->deleted()->create([
        'name' => 'Deleted Factory Item '.uniqid(),
    ]);

    expect($item->is_deleted)->toBe(1)
        ->and($item->deleted_by)->toBe('system')
        ->and($item->deleted_date)->not->toBeNull();

    $item->delete();
});
