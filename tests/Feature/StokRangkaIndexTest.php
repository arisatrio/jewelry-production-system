<?php

use App\Models\StokRangka;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::hasTable('stok_rangka')) {
        $this->markTestSkipped('Table stok_rangka is not available.');
    }
});

test('stok rangka index page is accessible', function () {
    $this->get(route('inventory.frame-stocks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/rangka/index')
            ->has('items.data')
            ->has('items.total')
            ->where('filters.search', '')
            ->where('filters.per_page', 50)
            ->where('filters.sort', 'updated_at')
            ->where('filters.direction', 'desc')
            ->where('filters.status', 'READY')
            ->has('summary')
            ->has('stockSummaries.ready')
            ->has('stockSummaries.used')
            ->has('filterOptions.sort')
            ->has('categoryOptions')
        );
});

test('stok rangka index defaults to latest updated first', function () {
    $token = 'RK-SORT-'.Str::upper(Str::random(6));
    $older = StokRangka::factory()->create([
        'doc_no' => $token.'-OLD',
        'updated_at' => now()->subDay(),
    ]);
    $newer = StokRangka::factory()->create([
        'doc_no' => $token.'-NEW',
        'updated_at' => now(),
    ]);

    try {
        $this->get(route('inventory.frame-stocks.index', [
            'search' => $token,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.id', $newer->id)
                ->where('filters.sort', 'updated_at')
                ->where('filters.direction', 'desc')
            );
    } finally {
        $older->delete();
        $newer->delete();
    }
});

test('stok rangka index can search by doc no', function () {
    $token = 'RK-SEARCH-'.Str::upper(Str::random(8));
    $row = StokRangka::factory()->create([
        'doc_no' => $token,
        'item_name' => 'Search Frame Test',
    ]);

    try {
        $this->get(route('inventory.frame-stocks.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', $token)
                ->has('items.data', 1)
                ->where('items.data.0.docNo', $token)
            );
    } finally {
        $row->delete();
    }
});

test('stok rangka index exposes spk number from prd field', function () {
    $spkNo = '2025/PRD/'.Str::upper(Str::random(6));
    $row = StokRangka::factory()->create([
        'prd' => $spkNo,
        'category' => 'RING',
        'item_name' => 'Test Item Name',
    ]);

    try {
        $this->get(route('inventory.frame-stocks.index', ['search' => $row->doc_no]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.spkNo', $spkNo)
                ->where('items.data.0.category', 'RING')
                ->where('items.data.0.itemName', 'Test Item Name')
            );
    } finally {
        $row->delete();
    }
});

test('stok rangka index defaults to ready status filter', function () {
    $token = 'RK-STAT-'.Str::upper(Str::random(6));
    $ready = StokRangka::factory()->create([
        'doc_no' => $token.'-READY',
        'status' => 'READY',
    ]);
    $used = StokRangka::factory()->create([
        'doc_no' => $token.'-USED',
        'status' => 'USED',
    ]);

    try {
        $this->get(route('inventory.frame-stocks.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'READY')
                ->has('items.data', 1)
                ->where('items.data.0.status', 'READY')
            );

        $this->get(route('inventory.frame-stocks.index', [
            'search' => $token,
            'status' => 'all',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', null)
                ->has('items.data', 2)
            );
    } finally {
        $ready->delete();
        $used->delete();
    }
});

test('stok rangka index excludes deleted rows', function () {
    $token = 'RK-DEL-'.Str::upper(Str::random(8));
    $row = StokRangka::factory()->deleted()->create([
        'doc_no' => $token,
    ]);

    try {
        $this->get(route('inventory.frame-stocks.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 0)
            );
    } finally {
        $row->delete();
    }
});
