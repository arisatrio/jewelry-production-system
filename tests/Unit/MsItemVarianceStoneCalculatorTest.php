<?php

use App\Support\MsItemVarianceStoneCalculator;

test('total carat is pcs multiplied by carat per pcs', function () {
    expect(MsItemVarianceStoneCalculator::totalCarat(10, '0.020'))->toBe('0.200')
        ->and(MsItemVarianceStoneCalculator::totalCarat(8, '0.040'))->toBe('0.320')
        ->and(MsItemVarianceStoneCalculator::totalCarat(null, '0.020'))->toBeNull()
        ->and(MsItemVarianceStoneCalculator::totalCarat(10, null))->toBeNull()
        ->and(MsItemVarianceStoneCalculator::totalCarat(10, ''))->toBeNull();
});
