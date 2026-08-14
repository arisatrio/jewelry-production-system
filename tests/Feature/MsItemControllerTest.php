<?php

use App\Models\MsItem;
use Illuminate\Support\Str;

test('tipe item index page is accessible', function () {
    $this->get(route('master-data.tipe-item.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/tipe-item/index')
            ->has('items.data')
            ->has('items.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('tipe item index page can filter by search query', function () {
    $item = MsItem::factory()->create([
        'name' => 'FilterItem '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('master-data.tipe-item.index', ['search' => $item->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/tipe-item/index')
            ->where('filters.search', $item->name)
            ->has('items.data.0')
            ->where('items.data.0.name', $item->name)
        );

    $item->delete();
});

test('tipe item create page is accessible', function () {
    $this->get(route('master-data.tipe-item.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/tipe-item/create')
        );
});

test('tipe item can be stored', function () {
    $name = 'CreateItem '.Str::upper(Str::random(8));

    $this->post(route('master-data.tipe-item.store'), [
        'name' => $name,
    ])
        ->assertRedirect(route('master-data.tipe-item.index'));

    $item = MsItem::query()->notDeleted()->where('name', $name)->first();

    expect($item)->not->toBeNull()
        ->and($item->is_deleted)->toBe(0)
        ->and($item->created_by)->toBe('system');

    $item->delete();
});

test('tipe item store validates unique name', function () {
    $item = MsItem::factory()->create([
        'name' => 'UniqueItem '.Str::upper(Str::random(8)),
    ]);

    $this->from(route('master-data.tipe-item.create'))
        ->post(route('master-data.tipe-item.store'), [
            'name' => $item->name,
        ])
        ->assertRedirect(route('master-data.tipe-item.create'))
        ->assertSessionHasErrors('name');

    $item->delete();
});

test('tipe item edit page is accessible', function () {
    $item = MsItem::factory()->create([
        'name' => 'EditPage '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('master-data.tipe-item.edit', $item))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/tipe-item/edit')
            ->where('item.id', $item->row_id)
            ->where('item.name', $item->name)
        );

    $item->delete();
});

test('tipe item can be updated', function () {
    $item = MsItem::factory()->create([
        'name' => 'BeforeUpdate '.Str::upper(Str::random(8)),
    ]);
    $newName = 'AfterUpdate '.Str::upper(Str::random(8));

    $this->put(route('master-data.tipe-item.update', $item), [
        'name' => $newName,
    ])
        ->assertRedirect(route('master-data.tipe-item.index'));

    $item->refresh();

    expect($item->name)->toBe($newName)
        ->and($item->modified_by)->toBe('system');

    $item->delete();
});

test('tipe item can be soft deleted', function () {
    $item = MsItem::factory()->create([
        'name' => 'DeleteItem '.Str::upper(Str::random(8)),
    ]);

    $this->delete(route('master-data.tipe-item.destroy', $item))
        ->assertRedirect(route('master-data.tipe-item.index'));

    $item->refresh();

    expect($item->is_deleted)->toBe(1)
        ->and($item->deleted_by)->toBe('system')
        ->and($item->deleted_date)->not->toBeNull();

    $this->get(route('master-data.tipe-item.edit', $item))
        ->assertNotFound();

    $item->delete();
});

test('deleted tipe item is hidden from index', function () {
    $item = MsItem::factory()->deleted()->create([
        'name' => 'HiddenItem '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('master-data.tipe-item.index', ['search' => $item->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.total', 0)
            ->has('items.data', 0)
        );

    $item->delete();
});
