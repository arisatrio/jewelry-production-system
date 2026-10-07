<?php

use App\Models\FinishingHandmade;
use App\Support\FinishingShrinkAllowanceSettings;

test('finishing shrink allowance defaults cover every work type and item category', function () {
    $settings = app(FinishingShrinkAllowanceSettings::class);

    foreach (FinishingHandmade::workTypeOptions() as $workType) {
        foreach (FinishingHandmade::itemCategoryOptions() as $itemCategory) {
            expect($settings->defaultPercent($workType, $itemCategory))->not->toBeNull();
        }
    }
});

test('finishing shrink allowance defaults match the finishing process table', function (string $workType, string $itemCategory, string $percent) {
    expect(app(FinishingShrinkAllowanceSettings::class)->defaultPercent($workType, $itemCategory))
        ->toBe($percent);
})->with([
    ['Pasang / Ganti Chain', 'Barang Kecil', '1.00'],
    ['Pasang / Ganti Chain', 'Barang Besar', '0.50'],
    ['Pasang Batu', 'Barang Kecil - lvl 3', '1.50'],
    ['Finishing 1', 'Barang Besar - lvl 1', '4.00'],
    ['Finishing Rangka', 'Barang Besar - lvl 2', '3.00'],
    ['Poles / Doff / Permukaan', 'Barang Besar - lvl 3', '0.50'],
    ['Repair Berat / Ketebalan', 'Barang Kecil - lvl 2', '3.00'],
    ['Repair Berat / Ketebalan', 'Barang Besar - lvl 3', '2.00'],
    ['General Check Up', 'Barang Kecil', '1.00'],
    ['Resize Ukuran (HK)', 'Barang Besar', '1.00'],
    ['Ubah Panjang / Extension', 'Barang Kecil - lvl 1', '2.00'],
    ['Potong / Tambah Butir', 'Barang Besar - lvl 2', '0.50'],
    ['Lainnya', 'Barang Kecil', '0.00'],
]);
