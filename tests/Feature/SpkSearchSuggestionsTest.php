<?php

use App\Models\Production;

test('spk search suggestions returns matching productions', function () {
    $match = Production::factory()->create([
        'spk_no' => '2026/PRD/12345',
        'spk_type' => 'Stock',
        'customer_name' => 'Andi Wijaya',
        'item_name' => 'Ladies Ring',
        'last_process' => 'Pasang Batu',
        'is_deleted' => 0,
    ]);

    Production::factory()->create([
        'spk_no' => '2026/PRD/99999',
        'customer_name' => 'Budi Santoso',
        'item_name' => 'Necklace',
        'is_deleted' => 0,
    ]);

    Production::factory()->deleted()->create([
        'spk_no' => '2026/PRD/12346',
        'customer_name' => 'Andi Deleted',
    ]);

    $this->getJson(route('spk.select.suggestions', [
        'search' => '12345',
        'limit' => 8,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rowId', (int) $match->row_id)
        ->assertJsonPath('data.0.spkNo', '2026/PRD/12345')
        ->assertJsonPath('data.0.spkType', 'Stock')
        ->assertJsonPath('data.0.customer', 'Andi Wijaya')
        ->assertJsonPath('data.0.item', 'Ladies Ring')
        ->assertJsonPath('data.0.lastProcess', 'Pasang Batu');
});

test('spk search suggestions returns empty data for blank search', function () {
    Production::factory()->create([
        'spk_no' => '2026/PRD/11111',
    ]);

    $this->getJson(route('spk.select.suggestions', [
        'search' => '   ',
    ]))
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonCount(0, 'data');
});

test('spk search suggestions can match by customer name', function () {
    Production::factory()->create([
        'spk_no' => '2026/PRD/22222',
        'customer_name' => 'Citra Dewi',
        'item_name' => 'Pendant',
        'is_deleted' => 0,
    ]);

    $this->getJson(route('spk.select.suggestions', [
        'search' => 'Citra',
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.spkNo', '2026/PRD/22222')
        ->assertJsonPath('data.0.customer', 'Citra Dewi');
});
