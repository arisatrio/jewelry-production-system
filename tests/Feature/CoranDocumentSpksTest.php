<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;

test('coran document spks endpoint returns spk rows for document', function () {
    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/CORSPKA',
        'item_name' => 'Item A',
        'customer_name' => 'Customer A',
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/CORSPKB',
        'item_name' => 'Item B',
        'customer_name' => 'Customer B',
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'CORSPKDOC1',
        'status' => null,
    ]);

    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionA->row_id,
        'weight' => '1.25',
        'status' => 'OK',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionB->row_id,
        'weight' => '2.50',
        'status' => 'NOK',
    ]);

    $this->getJson(route('coran.spks', $coran))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'spkNo' => '2026/PRD/CORSPKA',
            'item' => 'Item A',
            'customer' => 'Customer A',
            'weight' => '1.25',
            'status' => 'OK',
        ])
        ->assertJsonFragment([
            'spkNo' => '2026/PRD/CORSPKB',
            'item' => 'Item B',
            'customer' => 'Customer B',
            'weight' => '2.50',
            'status' => 'NOK',
        ]);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $productionA->delete();
    $productionB->delete();
});
