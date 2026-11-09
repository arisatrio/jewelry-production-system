<?php

use App\Support\SpkDiamondStatus;

test('spk diamond status labels', function () {
    expect(SpkDiamondStatus::label(SpkDiamondStatus::DRAFT))->toBe('Draft')
        ->and(SpkDiamondStatus::label(SpkDiamondStatus::SENT_TO_WORKSHOP))->toBe('Dikirim ke Workshop')
        ->and(SpkDiamondStatus::label(SpkDiamondStatus::RECEIVED_BY_WORKSHOP))->toBe('Diterima Workshop')
        ->and(SpkDiamondStatus::label(SpkDiamondStatus::INSTALLED))->toBe('Terpasang');
});

test('spk diamond status normalizes unknown values to draft', function () {
    expect(SpkDiamondStatus::normalize(null))->toBe(SpkDiamondStatus::DRAFT)
        ->and(SpkDiamondStatus::normalize('invalid'))->toBe(SpkDiamondStatus::DRAFT);
});
