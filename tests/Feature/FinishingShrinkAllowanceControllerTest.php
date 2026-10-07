<?php

use App\Models\FinishingShrinkAllowance;
use App\Support\FinishingShrinkAllowanceSettings;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget(FinishingShrinkAllowanceSettings::CACHE_KEY);
    FinishingShrinkAllowance::query()->delete();
});

afterEach(function (): void {
    FinishingShrinkAllowance::query()->delete();
    Cache::forget(FinishingShrinkAllowanceSettings::CACHE_KEY);
});

test('finishing shrink allowance edit page shows the default matrix', function () {
    $this->get(route('master-data.finishing-shrink-allowance.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/finishing-shrink-allowance/edit')
            ->has('itemCategories', 8)
            ->has('rows', 23)
            ->where('rows.4.workCategory', 'Finishing')
            ->where('rows.4.workType', 'Finishing 1')
            ->where('rows.4.percents.Barang Kecil', '5.00')
            ->where('rows.4.percents.Barang Besar', '4.00')
            ->where('rows.4.percents.Barang Besar - lvl 2', '3.00')
            ->where('rows.4.percents.Barang Besar - lvl 3', '2.00')
            ->where('rows.3.workType', 'Pasang Batu')
            ->where('rows.3.percents.Barang Besar - lvl 1', '1.50')
            ->where('rows.21.workType', 'Potong / Tambah Butir')
            ->where('rows.21.percents.Barang Kecil', '0.50')
            ->where('lastUpdate', null)
        );
});

test('finishing shrink allowance matrix can be updated', function () {
    $settings = app(FinishingShrinkAllowanceSettings::class);
    $cells = $settings->formCells();

    foreach ($cells as $index => $cell) {
        if ($cell['work_type'] === 'Finishing 1' && $cell['item_category'] === 'Barang Kecil') {
            $cells[$index]['allowance_percent'] = '6,50';
        }
    }

    $this->put(route('master-data.finishing-shrink-allowance.update'), [
        'cells' => $cells,
    ])
        ->assertRedirect(route('master-data.finishing-shrink-allowance.edit'));

    expect(FinishingShrinkAllowance::query()->count())->toBe(count($cells))
        ->and(FinishingShrinkAllowance::query()
            ->where('work_type', 'Finishing 1')
            ->where('item_category', 'Barang Kecil')
            ->value('allowance_percent'))->toBe('6.50')
        ->and(FinishingShrinkAllowance::query()
            ->where('work_type', 'Finishing 1')
            ->where('item_category', 'Barang Kecil')
            ->value('work_category'))->toBe('Finishing')
        ->and($settings->percentFor('Finishing 1', 'Barang Kecil'))->toBe('6.50')
        ->and($settings->percentFor('Repair Berat / Ketebalan', 'Barang Besar'))->toBe('2.00');
});

test('finishing shrink allowance update rejects a percent above 100', function () {
    $cells = app(FinishingShrinkAllowanceSettings::class)->formCells();
    $cells[0]['allowance_percent'] = '101';

    $this->from(route('master-data.finishing-shrink-allowance.edit'))
        ->put(route('master-data.finishing-shrink-allowance.update'), [
            'cells' => $cells,
        ])
        ->assertRedirect(route('master-data.finishing-shrink-allowance.edit'))
        ->assertSessionHasErrors('cells.0.allowance_percent');
});

test('finishing shrink allowance update requires every work type and item category', function () {
    $cells = app(FinishingShrinkAllowanceSettings::class)->formCells();
    array_pop($cells);

    $this->from(route('master-data.finishing-shrink-allowance.edit'))
        ->put(route('master-data.finishing-shrink-allowance.update'), [
            'cells' => $cells,
        ])
        ->assertRedirect(route('master-data.finishing-shrink-allowance.edit'))
        ->assertSessionHasErrors('cells');
});
