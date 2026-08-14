<?php

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\MsPosition;
use App\Models\MsShape;
use Illuminate\Support\Str;

test('varian item batu endpoint returns stones', function () {
    $item = MsItem::factory()->create([
        'name' => 'EditStoneParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'EditStoneVariance '.Str::upper(Str::random(6)),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'pcs' => 4,
    ]);

    $this->getJson(route('master-data.varian-item.batu', $variance))
        ->assertOk()
        ->assertJsonPath('stones.0.id', $stone->row_id)
        ->assertJsonPath('stones.0.pcs', 4);

    $stone->delete();
    $variance->delete();
    $item->delete();
});

test('batu varian index page is nested under varian item', function () {
    $item = MsItem::factory()->create([
        'name' => 'CreateStoneParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'CreateStoneVariance '.Str::upper(Str::random(6)),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'pcs' => 3,
    ]);

    $this->get(route('master-data.varian-item.stones.index', $variance))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/varian-item/stones/index')
            ->where('variance.id', $variance->row_id)
            ->where('stones.0.id', $stone->row_id)
            ->has('shapeOptions')
            ->has('positionOptions')
        );

    $stone->delete();
    $variance->delete();
    $item->delete();
});

test('batu varian can be stored under varian item', function () {
    $item = MsItem::factory()->create([
        'name' => 'StoreStoneParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'StoreStoneVariance '.Str::upper(Str::random(6)),
    ]);
    $shape = MsShape::query()->notDeleted()->first();
    $position = MsPosition::factory()->create();

    expect($shape)->not->toBeNull();

    $this->post(route('master-data.varian-item.stones.store', $variance), [
        'shape_id' => $shape->row_id,
        'position_id' => $position->id,
        'pcs' => 10,
        'carat_per_pcs' => '0.020',
        'total_carat' => '999.000',
        'size' => '1.50',
    ])
        ->assertRedirect(route('master-data.varian-item.stones.index', $variance));

    $stone = MsItemVarianceStone::query()
        ->notDeleted()
        ->where('item_variance_id', $variance->row_id)
        ->where('shape_id', $shape->row_id)
        ->latest('row_id')
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone->pcs)->toBe(10)
        ->and($stone->position_id)->toBe($position->id)
        ->and((string) $stone->carat_per_pcs)->toBe('0.020')
        ->and((string) $stone->total_carat)->toBe('0.200')
        ->and((string) $stone->size)->toBe('1.50');

    $stone->delete();
    $position->delete();
    $variance->delete();
    $item->delete();
});

test('batu varian can create new position when storing', function () {
    $item = MsItem::factory()->create([
        'name' => 'StoreStonePosParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'StoreStonePosVariance '.Str::upper(Str::random(6)),
    ]);
    $shape = MsShape::query()->notDeleted()->first();
    $positionNama = 'Posisi Baru '.Str::upper(Str::random(6));

    expect($shape)->not->toBeNull();

    $this->post(route('master-data.varian-item.stones.store', $variance), [
        'shape_id' => $shape->row_id,
        'position_nama' => $positionNama,
        'pcs' => 5,
        'carat_per_pcs' => '0.010',
        'size' => '1.00',
    ])
        ->assertRedirect(route('master-data.varian-item.stones.index', $variance));

    $position = MsPosition::query()->where('nama', $positionNama)->first();
    $stone = MsItemVarianceStone::query()
        ->notDeleted()
        ->where('item_variance_id', $variance->row_id)
        ->latest('row_id')
        ->first();

    expect($position)->not->toBeNull()
        ->and($stone)->not->toBeNull()
        ->and($stone->position_id)->toBe($position->id);

    $stone->delete();
    $position->delete();
    $variance->delete();
    $item->delete();
});

test('batu varian can be updated under varian item', function () {
    $item = MsItem::factory()->create([
        'name' => 'UpdateStoneParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'UpdateStoneVariance '.Str::upper(Str::random(6)),
    ]);
    $shape = MsShape::query()->notDeleted()->first();
    expect($shape)->not->toBeNull();

    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 2,
    ]);

    $this->put(route('master-data.varian-item.stones.update', [
        'msItemVariance' => $variance,
        'msItemVarianceStone' => $stone,
    ]), [
        'shape_id' => $shape->row_id,
        'pcs' => 8,
        'carat_per_pcs' => '0.040',
        'total_carat' => '999.000',
        'size' => '2.25',
    ])
        ->assertRedirect(route('master-data.varian-item.stones.index', $variance));

    $stone->refresh();

    expect($stone->pcs)->toBe(8)
        ->and((string) $stone->carat_per_pcs)->toBe('0.040')
        ->and((string) $stone->total_carat)->toBe('0.320')
        ->and((string) $stone->size)->toBe('2.25');

    $stone->delete();
    $variance->delete();
    $item->delete();
});

test('batu varian can be soft deleted under varian item', function () {
    $item = MsItem::factory()->create([
        'name' => 'DeleteStoneParent '.Str::upper(Str::random(6)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'DeleteStoneVariance '.Str::upper(Str::random(6)),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
    ]);

    $this->delete(route('master-data.varian-item.stones.destroy', [
        'msItemVariance' => $variance,
        'msItemVarianceStone' => $stone,
    ]))
        ->assertRedirect(route('master-data.varian-item.stones.index', $variance));

    $stone->refresh();

    expect($stone->is_deleted)->toBe(1);

    $this->get(route('master-data.varian-item.stones.index', $variance))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/varian-item/stones/index')
            ->where('stones', [])
        );

    $stone->delete();
    $variance->delete();
    $item->delete();
});
