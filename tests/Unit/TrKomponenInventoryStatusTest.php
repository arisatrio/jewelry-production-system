<?php

use App\Models\TrKomponen;

test('tr komponen inventory status resolves used before reserved', function () {
    $used = new TrKomponen(['is_used' => 1, 'is_reserved' => 1]);
    $reserved = new TrKomponen(['is_used' => 0, 'is_reserved' => 1]);
    $available = new TrKomponen(['is_used' => 0, 'is_reserved' => 0]);

    expect($used->inventoryStatus())->toBe('used')
        ->and($reserved->inventoryStatus())->toBe('reserved')
        ->and($available->inventoryStatus())->toBe('available');
});
