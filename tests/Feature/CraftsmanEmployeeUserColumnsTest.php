<?php

use Illuminate\Support\Facades\Schema;

test('mscraftsman has nullable employee_id and user_id after name', function () {
    $columns = Schema::connection('third')->getColumns('mscraftsman');
    $byName = collect($columns)->keyBy('name');
    $names = array_column($columns, 'name');
    $nameIndex = array_search('name', $names, true);

    expect($byName->has('employee_id'))->toBeTrue()
        ->and($byName->get('employee_id')['nullable'])->toBeTrue()
        ->and($byName->has('user_id'))->toBeTrue()
        ->and($byName->get('user_id')['nullable'])->toBeTrue()
        ->and(array_search('employee_id', $names, true))->toBe($nameIndex + 1)
        ->and(array_search('user_id', $names, true))->toBe($nameIndex + 2);
});
