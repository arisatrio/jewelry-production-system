<?php

use App\Models\SkuMaster;

test('sku identity code drops the gold color prefix', function () {
    expect(SkuMaster::identityCode('2T-LDR-ATF-REG-PRS-DMD-DS-070'))
        ->toBe('LDR-ATF-REG-PRS-DMD-DS-070')
        ->and(SkuMaster::identityCode('WG-LDR-ATF-REG-PRS-DMD-DS-070'))
        ->toBe('LDR-ATF-REG-PRS-DMD-DS-070')
        ->and(SkuMaster::identityCode('yg-ldr-atf-reg'))
        ->toBe('LDR-ATF-REG');
});

test('sku identity code keeps codes without a gold color prefix', function () {
    expect(SkuMaster::identityCode('LDRONLY'))->toBe('LDRONLY')
        ->and(SkuMaster::identityCode(''))->toBe('')
        ->and(SkuMaster::identityCode(null))->toBe('');
});
