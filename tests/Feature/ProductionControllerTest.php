<?php

use App\Models\Production;
use App\Support\SpkService;

test('spk index page is accessible and returns production list props', function () {
    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->has('productions.data')
            ->has('productions.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('spk index page can filter productions by search query', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.search', $production->spk_no)
            ->has('productions.data.0')
            ->where('productions.data.0.produksiNo', $production->spk_no)
            ->has('productions.data.0.description')
        );
});

test('spk index page respects per page option', function () {
    $this->get(route('spk.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.per_page', 25)
            ->where('productions.per_page', 25)
        );
});

test('spk show page displays production detail', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.id', (string) $production->row_id)
            ->where('production.produksiNo', $production->spk_no)
            ->has('production.customer')
            ->has('production.item')
            ->has('production.status')
            ->has('item.name')
            ->has('item.qty')
            ->has('item.diameter')
            ->has('item.dimensi')
            ->has('item.ringSize')
            ->has('item.diameterLengthRingSize')
            ->has('item.goldWeight')
            ->has('item.goldColor')
            ->has('item.jwcad3d')
            ->has('item.description')
            ->has('item.imageUrl')
            ->has('item.finishingType')
            ->has('stones')
            ->has('navigation.position')
            ->has('navigation.total')
            ->has('navigation.previousSpkNo')
            ->has('navigation.nextSpkNo')
            ->where('detailUrl', route('spk.show', $production, absolute: true))
        );
});

test('spk show provides absolute detail url for qr code modal', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $detailUrl = route('spk.show', $production, absolute: true);

    expect($detailUrl)->toStartWith('http');

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('detailUrl', $detailUrl)
            ->where('production.produksiNo', $production->spk_no)
        );
});

test('spk show page resolves legacy gcs image url from file name', function () {
    $production = Production::query()
        ->notDeleted()
        ->whereNotNull('spk_no')
        ->whereNotNull('file_name')
        ->where('file_name', '!=', '')
        ->where('file_name', 'not like', '%/%')
        ->first();

    expect($production)->not->toBeNull();

    $expectedUrl = rtrim((string) config('spk.production_image_base_url'), '/').'/'.$production->file_name;

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.imageUrl', $expectedUrl)
        );
});

test('spk show page resolves local storage image url for uploaded paths', function () {
    $production = app(SpkService::class)->createStock('system');
    $localPath = 'spk/'.$production->row_id.'/item-preview.jpg';
    $production->update(['file_name' => $localPath]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.imageUrl', '/storage/'.$localPath)
        );

    $production->delete();
});

test('spk show returns null image url when file name is empty for photo button', function () {
    $production = app(SpkService::class)->createStock('system');
    $production->update(['file_name' => null]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.imageUrl', null)
        );

    $production->delete();
});

test('spk show page maps status order labels', function (string $code, string $label) {
    $production = Production::query()
        ->notDeleted()
        ->whereNotNull('spk_no')
        ->where('status_order', $code)
        ->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.statusOrder', $label)
            ->has('item.statusOrderLabel')
        );
})->with([
    'repeat order' => ['RO', 'Repeat Order'],
    'new order' => ['NO', 'New Order'],
]);

test('spk show page returns not found for deleted production', function () {
    $production = Production::query()->where('is_deleted', 1)->first();

    if (! $production) {
        $production = Production::query()->notDeleted()->first();

        expect($production)->not->toBeNull();

        $production->forceFill(['is_deleted' => 1])->save();
    }

    $this->get(route('spk.show', $production))->assertNotFound();
});
