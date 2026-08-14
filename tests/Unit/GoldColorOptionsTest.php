<?php

use App\Support\GoldColorOptions;

test('gold color options are fixed to four values', function () {
    expect(GoldColorOptions::all())->toBe([
        'Rose Gold',
        'White Gold',
        'Yellow Gold',
        'Two Tones',
    ]);
});
