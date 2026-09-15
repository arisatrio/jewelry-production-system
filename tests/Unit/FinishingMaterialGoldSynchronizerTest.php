<?php

use App\Support\FinishingMaterialBreakdown;
use App\Support\FinishingMaterialGoldSynchronizer;

test('finishing material gold synchronizer exposes bahan and sisa sections', function () {
    expect(FinishingMaterialGoldSynchronizer::sectionKeys())->toBe(['bahan', 'sisa'])
        ->and(FinishingMaterialGoldSynchronizer::transtypeIds())->toBe([
            FinishingMaterialBreakdown::TRANSTYPE_OUT,
            FinishingMaterialBreakdown::TRANSTYPE_IN,
        ])
        ->and(FinishingMaterialGoldSynchronizer::SECTION_MAP['bahan']['transtype_id'])
        ->toBe(5)
        ->and(FinishingMaterialGoldSynchronizer::SECTION_MAP['sisa']['transtype_id'])
        ->toBe(6);
});
