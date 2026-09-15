<?php

use App\Support\FinishingMaterialBreakdown;

test('finishing material breakdown returns empty structure', function () {
    $breakdown = new FinishingMaterialBreakdown;

    expect($breakdown->empty())->toBe([
        'bahan' => [],
        'sisa' => [],
    ])
        ->and(FinishingMaterialBreakdown::transtypeIds())->toBe([5, 6]);
});

test('finishing material breakdown for empty ids returns empty map', function () {
    $breakdown = new FinishingMaterialBreakdown;

    expect($breakdown->forIds([]))->toBe([]);
});
