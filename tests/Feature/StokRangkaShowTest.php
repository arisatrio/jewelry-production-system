<?php

use App\Models\StokRangka;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::hasTable('stok_rangka')) {
        $this->markTestSkipped('Table stok_rangka is not available.');
    }
});

test('stok rangka show page is accessible', function () {
    $row = StokRangka::factory()->create([
        'doc_no' => 'RK-SHOW-'.Str::upper(Str::random(6)),
        'category' => 'RING',
        'item_name' => 'Detail Item',
        'prd' => '2025/PRD/'.Str::upper(Str::random(4)),
    ]);

    try {
        $this->get(route('inventory.frame-stocks.show', $row))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/rangka/show')
                ->has('item')
                ->where('item.id', $row->id)
                ->where('item.docNo', $row->doc_no)
                ->where('item.category', 'RING')
                ->where('item.itemName', 'Detail Item')
            );
    } finally {
        $row->delete();
    }
});

test('deleted stok rangka show returns not found', function () {
    $row = StokRangka::factory()->deleted()->create();

    try {
        $this->get(route('inventory.frame-stocks.show', $row))
            ->assertNotFound();
    } finally {
        $row->delete();
    }
});
