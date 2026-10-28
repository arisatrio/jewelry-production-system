<?php

use App\Models\MsComponent;
use App\Models\TrxComponent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('mscomponent')) {
        $this->markTestSkipped('Table mscomponent is not available.');
    }
});

test('component index page is accessible', function () {
    $this->get(route('inventory.components.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/component/index')
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
            ->has('componentRequestCount')
        );
});

test('component index summary counts total available stock units', function () {
    if (! Schema::connection('third')->hasTable('trxcomponent')) {
        $this->markTestSkipped('Table trxcomponent is not available.');
    }

    $token = 'SummaryKomponen '.Str::upper(Str::random(8));
    $master = MsComponent::factory()->create([
        'item' => $token,
        'item_code' => 'SUM-'.Str::upper(Str::random(6)),
    ]);
    $inStock = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'component_size' => '18.00',
        'entry_date' => now()->toDateString(),
        'out_date' => null,
    ]);
    $issued = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'component_size' => '18.00',
        'entry_date' => now()->subDay()->toDateString(),
        'out_date' => now()->toDateString(),
    ]);

    try {
        $this->get(route('inventory.components.index', ['search' => $token]))
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

test('component index page can sort by item code descending', function () {
    $prefix = 'SortKomponen '.Str::upper(Str::random(6));
    $first = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => $prefix,
        'item_code' => 'AAA-'.Str::upper(Str::random(4)),
    ]);
    $second = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => $prefix,
        'item_code' => 'ZZZ-'.Str::upper(Str::random(4)),
    ]);

    try {
        $this->get(route('inventory.components.index', [
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

test('component index page can filter by search and category', function () {
    $token = 'FilterKomponen '.Str::upper(Str::random(8));
    $matching = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => $token,
        'item_code' => 'FLT-'.Str::upper(Str::random(8)),
    ]);
    $otherCategory = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_STOPPER,
        'item' => $token,
        'item_code' => 'FLT-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.components.index', [
            'search' => $token,
            'category' => MsComponent::CATEGORY_KOMPONEN,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/component/index')
                ->where('filters.search', $token)
                ->where('filters.category', MsComponent::CATEGORY_KOMPONEN)
                ->has('items.data', 1)
                ->where('items.data.0.id', $matching->row_id)
                ->where('items.data.0.itemCode', $matching->item_code)
            );
    } finally {
        $matching->delete();
        $otherCategory->delete();
    }
});

test('component index page hides deleted rows', function () {
    $token = 'HideKomponen '.Str::upper(Str::random(8));
    $visible = MsComponent::factory()->create([
        'item' => $token,
        'item_code' => 'VIS-'.Str::upper(Str::random(8)),
    ]);
    $hidden = MsComponent::factory()->deleted()->create([
        'item' => $token,
        'item_code' => 'HID-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.components.index', ['search' => $token]))
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

test('component create route is not registered', function () {
    expect(Route::has('inventory.components.create'))->toBeFalse();
});

test('component can be stored and composes an item name', function () {
    $itemCode = 'NEW-'.Str::upper(Str::random(8));

    $this->post(route('inventory.components.store'), [
        'component_category' => MsComponent::CATEGORY_STOPPER,
        'item' => 'Komponen Classic',
        'item_code' => $itemCode,
        'description' => 'Stopper liontin',
    ])
        ->assertRedirect(route('inventory.components.index'));

    $item = MsComponent::query()->notDeleted()->where('item_code', $itemCode)->first();

    expect($item)->not->toBeNull()
        ->and($item->component_category)->toBe(MsComponent::CATEGORY_STOPPER)
        ->and($item->item_name)->toBe("Komponen Classic - {$itemCode}")
        ->and($item->description)->toBe('Stopper liontin')
        ->and($item->is_deleted)->toBe(0)
        ->and($item->created_by)->toBe('system');

    $item->delete();
});

test('component store validates required fields and unique identity', function () {
    $existing = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => 'Komponen HK',
        'item_code' => 'DUP-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->from(route('inventory.components.index'))
            ->post(route('inventory.components.store'), [])
            ->assertRedirect(route('inventory.components.index'))
            ->assertSessionHasErrors(['component_category', 'item', 'item_code']);

        $this->from(route('inventory.components.index'))
            ->post(route('inventory.components.store'), [
                'component_category' => 'Lainnya',
                'item' => 'Komponen HK',
                'item_code' => 'BAD-'.Str::upper(Str::random(8)),
            ])
            ->assertRedirect(route('inventory.components.index'))
            ->assertSessionHasErrors(['component_category']);

        $this->from(route('inventory.components.index'))
            ->post(route('inventory.components.store'), [
                'component_category' => $existing->component_category,
                'item' => $existing->item,
                'item_code' => $existing->item_code,
            ])
            ->assertRedirect(route('inventory.components.index'))
            ->assertSessionHasErrors('item_code');
    } finally {
        $existing->delete();
    }
});

test('component store restores a soft deleted identity', function () {
    $itemCode = 'RST-'.Str::upper(Str::random(8));
    $existing = MsComponent::factory()->deleted()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => 'Komponen UBS',
        'item_code' => $itemCode,
        'description' => 'Lama',
    ]);

    try {
        $this->post(route('inventory.components.store'), [
            'component_category' => MsComponent::CATEGORY_KOMPONEN,
            'item' => 'Komponen UBS',
            'item_code' => $itemCode,
            'item_name' => 'Komponen UBS dipulihkan',
            'description' => 'Baru',
        ])
            ->assertRedirect(route('inventory.components.index'));

        $existing->refresh();

        expect(MsComponent::query()->where('item_code', $itemCode)->count())->toBe(1)
            ->and($existing->is_deleted)->toBe(0)
            ->and($existing->item_name)->toBe('Komponen UBS dipulihkan')
            ->and($existing->description)->toBe('Baru')
            ->and($existing->deleted_by)->toBeNull()
            ->and($existing->deleted_date)->toBeNull();
    } finally {
        $existing->delete();
    }
});

test('component edit page is accessible', function () {
    $item = MsComponent::factory()->create([
        'item_code' => 'EDT-'.Str::upper(Str::random(8)),
        'item_name' => 'Komponen untuk edit',
    ]);

    try {
        $this->get(route('inventory.components.edit', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/component/edit')
                ->where('item.id', $item->row_id)
                ->where('item.itemCode', $item->item_code)
                ->where('item.itemName', 'Komponen untuk edit')
            );
    } finally {
        $item->delete();
    }
});

test('deleted component edit page returns not found', function () {
    $item = MsComponent::factory()->deleted()->create([
        'item_code' => 'DEL-EDT-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->get(route('inventory.components.edit', $item))
            ->assertNotFound();
    } finally {
        $item->delete();
    }
});

test('component can be updated', function () {
    $item = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_STOPPER,
        'item' => 'Komponen Classic',
        'item_code' => 'UPD-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->put(route('inventory.components.update', $item), [
            'component_category' => MsComponent::CATEGORY_STOPPER,
            'item' => 'Komponen Classic',
            'item_code' => $item->item_code,
            'item_name' => '',
            'description' => 'Diubah',
        ])
            ->assertRedirect(route('inventory.components.index'));

        $item->refresh();

        expect($item->item_name)->toBe("Komponen Classic - {$item->item_code}")
            ->and($item->description)->toBe('Diubah')
            ->and($item->modified_by)->toBe('system');
    } finally {
        $item->delete();
    }
});

test('component update rejects an identity used by another row', function () {
    $token = Str::upper(Str::random(8));
    $current = MsComponent::factory()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => 'Komponen Baru',
        'item_code' => 'CUR-'.$token,
    ]);
    $other = MsComponent::factory()->deleted()->create([
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => 'Komponen Baru',
        'item_code' => 'OTH-'.$token,
    ]);

    try {
        $this->from(route('inventory.components.edit', $current))
            ->put(route('inventory.components.update', $current), [
                'component_category' => $other->component_category,
                'item' => $other->item,
                'item_code' => $other->item_code,
            ])
            ->assertRedirect(route('inventory.components.edit', $current))
            ->assertSessionHasErrors('item_code');
    } finally {
        $current->delete();
        $other->delete();
    }
});

test('component can be soft deleted', function () {
    $item = MsComponent::factory()->create([
        'item_code' => 'DEL-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->delete(route('inventory.components.destroy', $item))
            ->assertRedirect(route('inventory.components.index'));

        $item->refresh();

        expect($item->is_deleted)->toBe(1)
            ->and($item->deleted_by)->toBe('system')
            ->and($item->deleted_date)->not->toBeNull();

        $this->get(route('inventory.components.edit', $item))
            ->assertNotFound();
    } finally {
        $item->delete();
    }
});

test('component stock store requires supplier weight and color only', function () {
    if (! Schema::connection('third')->hasTable('trxcomponent')) {
        $this->markTestSkipped('Table trxcomponent is not available.');
    }

    $master = MsComponent::factory()->create([
        'item_code' => 'STV-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->from(route('inventory.components.show', $master))
            ->post(route('inventory.components.stock.store', $master), [])
            ->assertRedirect(route('inventory.components.show', $master))
            ->assertSessionHasErrors(['supplier', 'weight', 'color'])
            ->assertSessionDoesntHaveErrors([
                'entry_date',
                'purchase_price',
                'component_size',
            ]);
    } finally {
        $master->delete();
    }
});

test('component stock can be received with supplier weight and color', function () {
    if (! Schema::connection('third')->hasTable('trxcomponent')) {
        $this->markTestSkipped('Table trxcomponent is not available.');
    }

    $master = MsComponent::factory()->create([
        'item_code' => 'RCV-'.Str::upper(Str::random(8)),
    ]);

    try {
        $this->post(route('inventory.components.stock.store', $master), [
            'supplier' => 'Supplier Komponen',
            'weight' => '2.50',
            'color' => 'Yellow Gold',
            'return_to' => 'show',
        ])
            ->assertRedirect(route('inventory.components.show', $master));

        $unit = TrxComponent::query()
            ->where('mscomponent_id', $master->row_id)
            ->where('supplier', 'Supplier Komponen')
            ->first();

        expect($unit)->not->toBeNull()
            ->and($unit->weight)->toBe('2.50')
            ->and($unit->color)->toBe('Yellow Gold')
            ->and($unit->component_size)->toBe('0.00')
            ->and($unit->purchase_price)->toBe('0.00')
            ->and($unit->entry_date?->toDateString())->toBe(now()->toDateString())
            ->and($unit->out_date)->toBeNull();

        $unit?->delete();
    } finally {
        $master->delete();
    }
});

test('component stock receive creates one row per pcs with unique unit numbers', function () {
    if (! Schema::connection('third')->hasTable('trxcomponent')) {
        $this->markTestSkipped('Table trxcomponent is not available.');
    }

    $master = MsComponent::factory()->create([
        'item_code' => 'PCS-'.Str::upper(Str::random(8)),
    ]);
    $supplier = 'Bulk Supplier '.Str::upper(Str::random(6));

    try {
        $this->post(route('inventory.components.stock.store', $master), [
            'supplier' => $supplier,
            'weight' => '1.00',
            'color' => 'White Gold',
            'pcs' => 5,
            'return_to' => 'show',
        ])
            ->assertRedirect(route('inventory.components.show', $master));

        $units = TrxComponent::query()
            ->where('mscomponent_id', $master->row_id)
            ->where('supplier', $supplier)
            ->orderBy('unit_no')
            ->get();

        expect($units)->toHaveCount(5)
            ->and($units->pluck('unit_no')->unique()->count())->toBe(5)
            ->and($units->every(fn (TrxComponent $unit): bool => $unit->weight === '1.00'))->toBeTrue()
            ->and($units->every(fn (TrxComponent $unit): bool => $unit->color === 'White Gold'))->toBeTrue();

        foreach ($units as $unit) {
            $unit->delete();
        }
    } finally {
        $master->delete();
    }
});
