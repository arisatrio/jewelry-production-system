<?php

use App\Support\SpkStatusOrder;

test('status order code is new when sku is missing', function () {
    expect(app(SpkStatusOrder::class)->code(null))->toBeNull()
        ->and(app(SpkStatusOrder::class)->displayLabel(null))->toBe('-');
});
