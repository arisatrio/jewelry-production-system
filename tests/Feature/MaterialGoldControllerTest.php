<?php

use App\Models\MaterialGold;
use App\Support\GoldMaterialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('bahan emas index page is accessible', function () {
    $this->get(route('inventory.gold-materials.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/bahan-emas/index')
            ->has('items.data')
            ->has('items.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('bahan emas index page can filter by search query', function () {
    $item = MaterialGold::factory()->create([
        'name' => 'FilterGold '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('inventory.gold-materials.index', ['search' => $item->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/bahan-emas/index')
            ->where('filters.search', $item->name)
            ->has('items.data.0')
            ->where('items.data.0.name', $item->name)
        );

    $item->delete();
});

test('bahan emas index shows stock of the active period', function () {
    $ledger = app(GoldMaterialLedger::class);
    $period = $ledger->activePeriod();

    if ($period === null) {
        $this->markTestSkipped('Active period is not available.');
    }

    $connection = DB::connection('third');
    $connection->beginTransaction();

    try {
        $token = 'StockGold '.Str::upper(Str::random(8));
        $item = MaterialGold::factory()->create([
            'name' => $token.' A',
        ]);

        foreach ([
            [GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID, '10.50'],
            [GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID, '2.25'],
            [GoldMaterialLedger::DEDUCTION_TRANSACTION_TYPE_ID, '4.00'],
        ] as [$transactionTypeId, $weight]) {
            $connection->table('trmaterialgold')->insert([
                'period_id' => $period['id'],
                'transtype_id' => $transactionTypeId,
                'ref_row_id' => 0,
                'materialgold_id' => $item->row_id,
                'spk_id' => 0,
                'weight' => $weight,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => 'tester',
            ]);
        }

        $emptyItem = MaterialGold::factory()->create([
            'name' => $token.' B',
        ]);

        $this->get(route('inventory.gold-materials.index', ['search' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.name', $item->name)
                ->where('items.data.0.stock', '8.75')
                ->where('items.data.1.name', $emptyItem->name)
                ->where('items.data.1.stock', '0.00')
            );
    } finally {
        $connection->rollBack();
    }
});

test('bahan emas show page displays stock and transactions of the active period', function () {
    $ledger = app(GoldMaterialLedger::class);
    $period = $ledger->activePeriod();

    if ($period === null) {
        $this->markTestSkipped('Active period is not available.');
    }

    $connection = DB::connection('third');
    $connection->beginTransaction();

    try {
        $item = MaterialGold::factory()->create([
            'name' => 'ShowGold '.Str::upper(Str::random(8)),
        ]);
        $otherItem = MaterialGold::factory()->create([
            'name' => 'ShowGold Other '.Str::upper(Str::random(8)),
        ]);

        foreach ([
            [$item->row_id, GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID, '5.00'],
            [$item->row_id, GoldMaterialLedger::DEDUCTION_TRANSACTION_TYPE_ID, '1.25'],
            [$otherItem->row_id, GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID, '9.00'],
        ] as [$materialId, $transactionTypeId, $weight]) {
            $connection->table('trmaterialgold')->insert([
                'period_id' => $period['id'],
                'transtype_id' => $transactionTypeId,
                'ref_row_id' => 0,
                'materialgold_id' => $materialId,
                'spk_id' => 0,
                'weight' => $weight,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => 'tester',
            ]);
        }

        $this->get(route('inventory.gold-materials.show', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/bahan-emas/show')
                ->where('item.id', $item->row_id)
                ->where('item.name', $item->name)
                ->where('item.stock', '3.75')
                ->where('activePeriod.id', $period['id'])
                ->where('transactions.total', 2)
                ->where('transactions.data.0.category', 'Pemakaian')
                ->where('transactions.data.0.weight', '1.25')
                ->where('transactions.data.1.category', 'Penambahan')
                ->where('transactions.data.1.weight', '5.00')
            );
    } finally {
        $connection->rollBack();
    }
});

test('deleted bahan emas show page returns not found', function () {
    $item = MaterialGold::factory()->deleted()->create([
        'name' => 'DeletedShowGold '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('inventory.gold-materials.show', $item))
        ->assertNotFound();

    $item->delete();
});

test('bahan emas create page is accessible', function () {
    $this->get(route('inventory.gold-materials.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/bahan-emas/create')
        );
});

test('bahan emas can be stored', function () {
    $name = 'CreateGold '.Str::upper(Str::random(8));

    $this->post(route('inventory.gold-materials.store'), [
        'name' => $name,
    ])
        ->assertRedirect(route('inventory.gold-materials.index'));

    $item = MaterialGold::query()->notDeleted()->where('name', $name)->first();

    expect($item)->not->toBeNull()
        ->and($item->is_deleted)->toBe(0)
        ->and($item->created_by)->toBe('system');

    $item->delete();
});

test('bahan emas store validates unique name', function () {
    $item = MaterialGold::factory()->create([
        'name' => 'UniqueGold '.Str::upper(Str::random(8)),
    ]);

    $this->from(route('inventory.gold-materials.create'))
        ->post(route('inventory.gold-materials.store'), [
            'name' => $item->name,
        ])
        ->assertRedirect(route('inventory.gold-materials.create'))
        ->assertSessionHasErrors('name');

    $item->delete();
});

test('bahan emas edit page is accessible', function () {
    $item = MaterialGold::factory()->create([
        'name' => 'EditGold '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('inventory.gold-materials.edit', $item))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/bahan-emas/edit')
            ->where('item.id', $item->row_id)
            ->where('item.name', $item->name)
        );

    $item->delete();
});

test('bahan emas can be updated', function () {
    $item = MaterialGold::factory()->create([
        'name' => 'BeforeGold '.Str::upper(Str::random(8)),
    ]);
    $newName = 'AfterGold '.Str::upper(Str::random(8));

    $this->put(route('inventory.gold-materials.update', $item), [
        'name' => $newName,
    ])
        ->assertRedirect(route('inventory.gold-materials.index'));

    $item->refresh();

    expect($item->name)->toBe($newName)
        ->and($item->modified_by)->toBe('system');

    $item->delete();
});

test('bahan emas can be soft deleted', function () {
    $item = MaterialGold::factory()->create([
        'name' => 'DeleteGold '.Str::upper(Str::random(8)),
    ]);

    $this->delete(route('inventory.gold-materials.destroy', $item))
        ->assertRedirect(route('inventory.gold-materials.index'));

    $item->refresh();

    expect($item->is_deleted)->toBe(1)
        ->and($item->deleted_by)->toBe('system')
        ->and($item->deleted_date)->not->toBeNull();

    $this->get(route('inventory.gold-materials.edit', $item))
        ->assertNotFound();

    $item->delete();
});

test('deleted bahan emas is hidden from index', function () {
    $item = MaterialGold::factory()->deleted()->create([
        'name' => 'HiddenGold '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('inventory.gold-materials.index', ['search' => $item->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.total', 0)
            ->has('items.data', 0)
        );

    $item->delete();
});
