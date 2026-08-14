<?php

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('varian item index page is accessible', function () {
    $this->get(route('master-data.varian-item.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/varian-item/index')
            ->has('variances.data')
            ->has('variances.total')
            ->has('itemOptions')
            ->has('goldColorOptions', 4)
            ->has('filters.search')
            ->has('filters.item_id')
            ->has('filters.per_page')
            ->has('filters.create')
            ->has('filters.edit')
        );
});

test('varian item create redirects to index modal', function () {
    $this->get(route('master-data.varian-item.create'))
        ->assertRedirect(route('master-data.varian-item.index', ['create' => 1]));
});

test('varian item can be stored', function () {
    $item = MsItem::factory()->create([
        'name' => 'ParentItem '.Str::upper(Str::random(8)),
    ]);
    $name = 'CreateVariance '.Str::upper(Str::random(8));

    $this->from(route('master-data.varian-item.index'))
        ->post(route('master-data.varian-item.store'), [
            'item_id' => $item->row_id,
            'name' => $name,
            'description' => 'Deskripsi uji',
            'diameter' => '14',
            'dimensi' => '10x8',
            'ring_size' => '17',
            'gold_weight' => '12.50',
            'gold_color' => 'Yellow Gold',
            'jwcad_3d' => 'JWC-1001',
        ])
        ->assertRedirect(route('master-data.varian-item.index'));

    $variance = MsItemVariance::query()->notDeleted()->where('name', $name)->first();

    expect($variance)->not->toBeNull()
        ->and($variance->item_id)->toBe($item->row_id)
        ->and($variance->description)->toBe('Deskripsi uji')
        ->and($variance->diameter)->toBe('14')
        ->and($variance->dimensi)->toBe('10x8')
        ->and($variance->ring_size)->toBe('17')
        ->and($variance->diameter_length_ringsize)->toBe('14 / 10x8 / 17')
        ->and((string) $variance->gold_weight)->toBe('12.50')
        ->and($variance->gold_color)->toBe('Yellow Gold')
        ->and($variance->jwcad_3d)->toBe('JWC-1001')
        ->and($variance->image)->toBeNull()
        ->and($variance->created_by)->toBe('system');

    $variance->delete();
    $item->delete();
});

test('varian item can be stored with image', function () {
    Storage::fake('public');

    $item = MsItem::factory()->create([
        'name' => 'ImageParent '.Str::upper(Str::random(8)),
    ]);
    $name = 'ImageVariance '.Str::upper(Str::random(8));
    $image = UploadedFile::fake()->image('varian.jpg', 200, 200);

    $this->from(route('master-data.varian-item.index'))
        ->post(route('master-data.varian-item.store'), [
            'item_id' => $item->row_id,
            'name' => $name,
            'description' => null,
            'diameter' => null,
            'dimensi' => null,
            'ring_size' => null,
            'gold_weight' => '8.00',
            'gold_color' => 'Rose Gold',
            'jwcad_3d' => null,
            'image' => $image,
        ])
        ->assertRedirect(route('master-data.varian-item.index'));

    $variance = MsItemVariance::query()->notDeleted()->where('name', $name)->first();

    expect($variance)->not->toBeNull()
        ->and($variance->image)->not->toBeNull();

    Storage::disk('public')->assertExists($variance->image);

    $this->get(route('master-data.varian-item.index', ['item_id' => $item->row_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/varian-item/index')
            ->where('variances.data.0.id', $variance->row_id)
            ->where('variances.data.0.image', $variance->image)
            ->where('variances.data.0.imageUrl', '/storage/'.$variance->image)
        );

    Storage::disk('public')->delete($variance->image);
    $variance->delete();
    $item->delete();
});

test('varian item store validates required fields', function () {
    $this->from(route('master-data.varian-item.index'))
        ->post(route('master-data.varian-item.store'), [])
        ->assertRedirect(route('master-data.varian-item.index'))
        ->assertSessionHasErrors(['item_id', 'name', 'gold_weight', 'gold_color']);
});

test('varian item store rejects invalid image', function () {
    Storage::fake('public');

    $item = MsItem::factory()->create([
        'name' => 'InvalidImageParent '.Str::upper(Str::random(8)),
    ]);

    $this->from(route('master-data.varian-item.index'))
        ->post(route('master-data.varian-item.store'), [
            'item_id' => $item->row_id,
            'name' => 'InvalidImage '.Str::upper(Str::random(8)),
            'gold_weight' => '5.00',
            'gold_color' => 'Yellow Gold',
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('master-data.varian-item.index'))
        ->assertSessionHasErrors(['image']);

    $item->delete();
});

test('varian item edit redirects to index modal', function () {
    $item = MsItem::factory()->create([
        'name' => 'EditParent '.Str::upper(Str::random(8)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'EditVariance '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('master-data.varian-item.edit', $variance))
        ->assertRedirect(route('master-data.varian-item.index', [
            'edit' => $variance->row_id,
        ]));

    $variance->delete();
    $item->delete();
});

test('varian item batu endpoint returns stones', function () {
    $item = MsItem::factory()->create([
        'name' => 'BatuParent '.Str::upper(Str::random(8)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'BatuVariance '.Str::upper(Str::random(8)),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'pcs' => 4,
    ]);

    $this->getJson(route('master-data.varian-item.batu', $variance))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('variance.id', $variance->row_id)
        ->assertJsonPath('stones.0.id', $stone->row_id)
        ->assertJsonPath('stones.0.pcs', 4);

    $stone->delete();
    $variance->delete();
    $item->delete();
});

test('varian item can be updated', function () {
    $item = MsItem::factory()->create([
        'name' => 'UpdateParent '.Str::upper(Str::random(8)),
    ]);
    $otherItem = MsItem::factory()->create([
        'name' => 'OtherParent '.Str::upper(Str::random(8)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'OldVariance '.Str::upper(Str::random(8)),
    ]);
    $newName = 'UpdatedVariance '.Str::upper(Str::random(8));

    $this->from(route('master-data.varian-item.index'))
        ->put(route('master-data.varian-item.update', $variance), [
            'item_id' => $otherItem->row_id,
            'name' => $newName,
            'description' => 'Updated desc',
            'diameter' => '15',
            'dimensi' => '12x10',
            'ring_size' => '18',
            'gold_weight' => '9.25',
            'gold_color' => 'White Gold',
            'jwcad_3d' => 'JWC-2002',
        ])
        ->assertRedirect(route('master-data.varian-item.index'));

    $variance->refresh();

    expect($variance->name)->toBe($newName)
        ->and($variance->item_id)->toBe($otherItem->row_id)
        ->and($variance->description)->toBe('Updated desc')
        ->and($variance->diameter)->toBe('15')
        ->and($variance->dimensi)->toBe('12x10')
        ->and($variance->ring_size)->toBe('18')
        ->and($variance->diameter_length_ringsize)->toBe('15 / 12x10 / 18')
        ->and((string) $variance->gold_weight)->toBe('9.25')
        ->and($variance->gold_color)->toBe('White Gold')
        ->and($variance->jwcad_3d)->toBe('JWC-2002')
        ->and($variance->modified_by)->toBe('system');

    $variance->delete();
    $item->delete();
    $otherItem->delete();
});

test('varian item can be updated with new image', function () {
    Storage::fake('public');

    $item = MsItem::factory()->create([
        'name' => 'UpdateImageParent '.Str::upper(Str::random(8)),
    ]);
    $oldPath = 'varian-item/old/old.jpg';
    Storage::disk('public')->put($oldPath, 'old-image-content');

    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'UpdateImageVariance '.Str::upper(Str::random(8)),
        'gold_weight' => '7.50',
        'gold_color' => 'Yellow Gold',
        'image' => $oldPath,
    ]);

    $newImage = UploadedFile::fake()->image('new-varian.png', 180, 180);

    $this->from(route('master-data.varian-item.index'))
        ->post(route('master-data.varian-item.update', $variance), [
            '_method' => 'put',
            'item_id' => $item->row_id,
            'name' => $variance->name,
            'description' => $variance->description,
            'diameter' => $variance->diameter,
            'dimensi' => $variance->dimensi,
            'ring_size' => $variance->ring_size,
            'gold_weight' => '7.50',
            'gold_color' => 'Yellow Gold',
            'jwcad_3d' => $variance->jwcad_3d,
            'image' => $newImage,
        ])
        ->assertRedirect(route('master-data.varian-item.index'));

    $variance->refresh();

    expect($variance->image)->not->toBeNull()
        ->and($variance->image)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($variance->image);

    Storage::disk('public')->delete($variance->image);
    $variance->delete();
    $item->delete();
});

test('varian item can be soft deleted', function () {
    $item = MsItem::factory()->create([
        'name' => 'DeleteParent '.Str::upper(Str::random(8)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'DeleteVariance '.Str::upper(Str::random(8)),
    ]);

    $this->delete(route('master-data.varian-item.destroy', $variance))
        ->assertRedirect(route('master-data.varian-item.index'));

    $variance->refresh();

    expect($variance->is_deleted)->toBe(1)
        ->and($variance->deleted_by)->toBe('system')
        ->and($variance->deleted_date)->not->toBeNull();

    $this->get(route('master-data.varian-item.edit', $variance))
        ->assertNotFound();

    $variance->delete();
    $item->delete();
});

test('varian item index includes stone list summaries', function () {
    $item = MsItem::factory()->create([
        'name' => 'StoneListParent '.Str::upper(Str::random(8)),
    ]);
    $variance = MsItemVariance::factory()->create([
        'item_id' => $item->row_id,
        'name' => 'StoneListVariance '.Str::upper(Str::random(8)),
    ]);
    $stone = MsItemVarianceStone::factory()->create([
        'item_variance_id' => $variance->row_id,
        'pcs' => 3,
        'carat_per_pcs' => '0.250',
        'total_carat' => '0.750',
        'size' => '1.50',
    ]);

    $this->get(route('master-data.varian-item.index', ['item_id' => $item->row_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/varian-item/index')
            ->where('variances.data.0.id', $variance->row_id)
            ->has('variances.data.0.stones', 1)
            ->where('variances.data.0.stones.0.id', $stone->row_id)
            ->where('variances.data.0.stones.0.pcs', 3)
            ->where('variances.data.0.stones.0.caratPerPcs', '0.250')
            ->where('variances.data.0.stones.0.totalCarat', '0.750')
            ->where('variances.data.0.stones.0.size', '1.50')
        );

    $stone->delete();
    $variance->delete();
    $item->delete();
});
