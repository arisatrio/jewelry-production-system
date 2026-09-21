<?php

use App\Models\CoranSpk;

test('coran aggregate status prefers nok when any color is nok', function () {
    expect(CoranSpk::aggregateInputStatus(['OK', 'NOK', 'OK']))->toBe(CoranSpk::STATUS_NOK)
        ->and(CoranSpk::aggregateInputStatus(['OK', null, 'OK']))->toBe(CoranSpk::STATUS_OK)
        ->and(CoranSpk::aggregateInputStatus([null, null, null]))->toBeNull()
        ->and(CoranSpk::firstFilledKadar([null, '75.00', '37.50']))->toBe('75.00')
        ->and(CoranSpk::firstFilledKadar([null, null, null]))->toBeNull();
});
