<?php

use App\Models\ResinDetail;

test('resin detail normalizes empty and legacy status input to null', function () {
    expect(ResinDetail::normalizeInputStatus(null))->toBeNull()
        ->and(ResinDetail::normalizeInputStatus(''))->toBeNull()
        ->and(ResinDetail::normalizeInputStatus('   '))->toBeNull()
        ->and(ResinDetail::normalizeInputStatus('—'))->toBeNull()
        ->and(ResinDetail::normalizeInputStatus(ResinDetail::STATUS_OPEN))->toBeNull()
        ->and(ResinDetail::normalizeInputStatus(ResinDetail::STATUS_OK))->toBe(ResinDetail::STATUS_OK)
        ->and(ResinDetail::normalizeInputStatus(ResinDetail::STATUS_NOT_OK))->toBe(ResinDetail::STATUS_NOT_OK);
});
