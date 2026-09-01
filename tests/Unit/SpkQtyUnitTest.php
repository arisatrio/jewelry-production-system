<?php

use App\Support\SpkQtyUnit;

test('spk qty unit options map to qty and satuan', function () {
    expect(SpkQtyUnit::options())->toBe([
        [
            'value' => '1|Pcs',
            'label' => '1 Pcs',
            'qty' => 1,
            'satuan' => 'Pcs',
        ],
        [
            'value' => '1|Pasang',
            'label' => '1 Pasang',
            'qty' => 1,
            'satuan' => 'Pasang',
        ],
        [
            'value' => '1|Setengah Pasang',
            'label' => '1/2 Pasang',
            'qty' => 1,
            'satuan' => 'Setengah Pasang',
        ],
    ]);
});

test('spk qty unit label uses friendly text for half pair', function () {
    expect(SpkQtyUnit::label(1, 'Setengah Pasang'))->toBe('1/2 Pasang')
        ->and(SpkQtyUnit::label(1, 'Pcs'))->toBe('1 Pcs')
        ->and(SpkQtyUnit::label(2, 'Pasang'))->toBe('2 Pasang');
});

test('spk qty unit parses option value', function () {
    expect(SpkQtyUnit::parseOptionValue('1|Setengah Pasang'))->toBe([
        'qty' => 1,
        'satuan' => 'Setengah Pasang',
    ]);
});
