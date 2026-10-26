<?php

use App\Models\MsChain;
use Tests\TestCase;

uses(TestCase::class);

test('ms chain composes a display name from item code and length', function () {
    expect(MsChain::composeItemName('Chain HK', 'F-83', '18.00'))->toBe('Chain HK - F-83 - 18 cm')
        ->and(MsChain::composeItemName('Chain HK', 'F-83', '18.50'))->toBe('Chain HK - F-83 - 18.5 cm');
});

test('ms chain model uses the third connection and mschain table', function () {
    $model = new MsChain;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('mschain')
        ->and($model->getKeyName())->toBe('row_id')
        ->and($model->usesTimestamps())->toBeFalse();
});
