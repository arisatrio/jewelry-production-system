<?php

use App\Models\MsChain;
use App\Models\TrxChain;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('mschain')) {
        $this->markTestSkipped('Table mschain is not available.');
    }
});

test('chain index page is accessible', function () {
    $this->get(route('inventory.chains.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/chain/index')
            ->has('items.data')
            ->has('items.total')
            ->has('categories')
            ->has('colorOptions', 4)
            ->where('filters.search', '')
            ->where('filters.category', '')
            ->where('filters.per_page', 50)
            ->where('filters.sort', 'category')
            ->where('filters.direction', 'asc')
            ->has('filterOptions.sort')
            ->has('filterOptions.direction')
            ->has('summary.totalAvailable')
            ->has('chainRequestCount')
        );
});

test('chain index summary counts total available stock units', function () {
    if (! Schema::connection('third')->hasTable('trxchain')) {
        $this->markTestSkipped('Table trxchain is not available.');
    }

    $token = 'SummaryChain '.Str::upper(Str::random(8));
    $master = MsChain::factory()->create([
        'item' => $token,
        'item_code' => 'SUM-'.Str::upper(Str::random(6)),
        'length' => '18.00',
    ]);
    $inStock = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'chain_length' => '18.00',
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);
    $issued = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'chain_length' => '18.00',
        'entry_date' => now()->subDay()->toDateString(),
        'out_date' => now()->toDateString(),
    ]);

    try {
        $this->get(route('inventory.chains.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.totalAvailable', 1)
            );
    } finally {
        $inStock->delete();
        $issued->delete();
        $master->delete();
    }
});

test('chain index page can sort by item code descending', function () {
    $prefix = 'SortChain '.Str::upper(Str::random(6));
    $first = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => $prefix,
        'item_code' => 'AAA-'.Str::upper(Str::random(4)),
        'length' => '16.00',
    ]);
    $second = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => $prefix,
        'item_code' => 'ZZZ-'.Str::upper(Str::random(4)),
        'length' => '16.00',
    ]);

    try {
        $this->get(route('inventory.chains.index', [
            'search' => $prefix,
            'sort' => 'code',
            'direction' => 'desc',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'code')
                ->where('filters.direction', 'desc')
                ->has('items.data', 2)
                ->where('items.data.0.id', $second->row_id)
                ->where('items.data.1.id', $first->row_id)
            );
    } finally {
        $first->delete();
        $second->delete();
    }
});

test('chain index page can filter by search and category', function () {
    $token = 'FilterChain '.Str::upper(Str::random(8));
    $matching = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => $token,
        'item_code' => 'FLT-'.Str::upper(Str::random(8)),
        'length' => '16.00',
    ]);
    $otherCategory = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_PENDANT,
        'item' => $token,
        'item_code' => 'FLT-'.Str::upper(Str::random(8)),
        'length' => '40.00',
    ]);

    try {
        $this->get(route('inventory.chains.index', [
            'search' => $token,
            'category' => MsChain::CATEGORY_BRACELET,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/index')
                ->where('filters.search', $token)
                ->where('filters.category', MsChain::CATEGORY_BRACELET)
                ->has('items.data', 1)
                ->where('items.data.0.id', $matching->row_id)
                ->where('items.data.0.itemCode', $matching->item_code)
            );
    } finally {
        $matching->delete();
        $otherCategory->delete();
    }
});

test('chain index page hides deleted rows', function () {
    $token = 'HideChain '.Str::upper(Str::random(8));
    $visible = MsChain::factory()->create([
        'item' => $token,
        'item_code' => 'VIS-'.Str::upper(Str::random(8)),
    ]);
    $hidden = MsChain::factory()->deleted()->create([
        'item' => $token,
        'item_code' => 'HID-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.chains.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.id', $visible->row_id)
            );
    } finally {
        $visible->delete();
        $hidden->delete();
    }
});

test('chain create route is not registered', function () {
    expect(Route::has('inventory.chains.create'))->toBeFalse();
});

test('chain can be stored and composes an item name', function () {
    $itemCode = 'NEW-'.Str::upper(Str::random(8));

    $this->post(route('inventory.chains.store'), [
        'chain_category' => MsChain::CATEGORY_PENDANT,
        'item' => 'Chain Classic',
        'item_code' => $itemCode,
        'length' => '40',
        'description' => 'Rantai liontin',
    ])
        ->assertRedirect(route('inventory.chains.index'));

    $item = MsChain::query()->notDeleted()->where('item_code', $itemCode)->first();

    expect($item)->not->toBeNull()
        ->and($item->chain_category)->toBe(MsChain::CATEGORY_PENDANT)
        ->and($item->length)->toBe('40.00')
        ->and($item->item_name)->toBe("Chain Classic - {$itemCode} - 40 cm")
        ->and($item->description)->toBe('Rantai liontin')
        ->and($item->is_deleted)->toBe(0)
        ->and($item->created_by)->toBe('system');

    $item->delete();
});

test('chain store validates required fields and unique identity', function () {
    $existing = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain HK',
        'item_code' => 'DUP-'.Str::upper(Str::random(8)),
        'length' => '18.00',
    ]);

    try {
        $this->from(route('inventory.chains.index'))
            ->post(route('inventory.chains.store'), [])
            ->assertRedirect(route('inventory.chains.index'))
            ->assertSessionHasErrors(['chain_category', 'item', 'item_code', 'length']);

        $this->from(route('inventory.chains.index'))
            ->post(route('inventory.chains.store'), [
                'chain_category' => 'Lainnya',
                'item' => 'Chain HK',
                'item_code' => 'BAD-'.Str::upper(Str::random(8)),
                'length' => '18.256',
            ])
            ->assertRedirect(route('inventory.chains.index'))
            ->assertSessionHasErrors(['chain_category', 'length']);

        $this->from(route('inventory.chains.index'))
            ->post(route('inventory.chains.store'), [
                'chain_category' => $existing->chain_category,
                'item' => $existing->item,
                'item_code' => $existing->item_code,
                'length' => '18',
            ])
            ->assertRedirect(route('inventory.chains.index'))
            ->assertSessionHasErrors('item_code');
    } finally {
        $existing->delete();
    }
});

test('chain store restores a soft deleted identity', function () {
    $itemCode = 'RST-'.Str::upper(Str::random(8));
    $existing = MsChain::factory()->deleted()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain UBS',
        'item_code' => $itemCode,
        'length' => '16.00',
        'description' => 'Lama',
    ]);

    try {
        $this->post(route('inventory.chains.store'), [
            'chain_category' => MsChain::CATEGORY_BRACELET,
            'item' => 'Chain UBS',
            'item_code' => $itemCode,
            'length' => '16',
            'item_name' => 'Chain UBS dipulihkan',
            'description' => 'Baru',
        ])
            ->assertRedirect(route('inventory.chains.index'));

        $existing->refresh();

        expect(MsChain::query()->where('item_code', $itemCode)->count())->toBe(1)
            ->and($existing->is_deleted)->toBe(0)
            ->and($existing->item_name)->toBe('Chain UBS dipulihkan')
            ->and($existing->description)->toBe('Baru')
            ->and($existing->deleted_by)->toBeNull()
            ->and($existing->deleted_date)->toBeNull();
    } finally {
        $existing->delete();
    }
});

test('chain edit page is accessible', function () {
    $item = MsChain::factory()->create([
        'item_code' => 'EDT-'.Str::upper(Str::random(8)),
        'item_name' => 'Chain untuk edit',
    ]);

    try {
        $this->get(route('inventory.chains.edit', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/edit')
                ->where('item.id', $item->row_id)
                ->where('item.itemCode', $item->item_code)
                ->where('item.itemName', 'Chain untuk edit')
            );
    } finally {
        $item->delete();
    }
});

test('deleted chain edit page returns not found', function () {
    $item = MsChain::factory()->deleted()->create([
        'item_code' => 'DEL-EDT-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.chains.edit', $item))
            ->assertNotFound();
    } finally {
        $item->delete();
    }
});

test('chain can be updated', function () {
    $item = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_PENDANT,
        'item' => 'Chain Classic',
        'item_code' => 'UPD-'.Str::upper(Str::random(8)),
        'length' => '45.00',
    ]);

    try {
        $this->put(route('inventory.chains.update', $item), [
            'chain_category' => MsChain::CATEGORY_PENDANT,
            'item' => 'Chain Classic',
            'item_code' => $item->item_code,
            'length' => '50',
            'item_name' => '',
            'description' => 'Diubah',
        ])
            ->assertRedirect(route('inventory.chains.index'));

        $item->refresh();

        expect($item->length)->toBe('50.00')
            ->and($item->item_name)->toBe("Chain Classic - {$item->item_code} - 50 cm")
            ->and($item->description)->toBe('Diubah')
            ->and($item->modified_by)->toBe('system');
    } finally {
        $item->delete();
    }
});

test('chain update rejects an identity used by another row', function () {
    $token = Str::upper(Str::random(8));
    $current = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain Baru',
        'item_code' => 'CUR-'.$token,
        'length' => '18.00',
    ]);
    $other = MsChain::factory()->deleted()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain Baru',
        'item_code' => 'OTH-'.$token,
        'length' => '20.00',
    ]);

    try {
        $this->from(route('inventory.chains.edit', $current))
            ->put(route('inventory.chains.update', $current), [
                'chain_category' => $other->chain_category,
                'item' => $other->item,
                'item_code' => $other->item_code,
                'length' => '20',
            ])
            ->assertRedirect(route('inventory.chains.edit', $current))
            ->assertSessionHasErrors('item_code');
    } finally {
        $current->delete();
        $other->delete();
    }
});

test('chain can be soft deleted', function () {
    $item = MsChain::factory()->create([
        'item_code' => 'DEL-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->delete(route('inventory.chains.destroy', $item))
            ->assertRedirect(route('inventory.chains.index'));

        $item->refresh();

        expect($item->is_deleted)->toBe(1)
            ->and($item->deleted_by)->toBe('system')
            ->and($item->deleted_date)->not->toBeNull();

        $this->get(route('inventory.chains.edit', $item))
            ->assertNotFound();
    } finally {
        $item->delete();
    }
});
