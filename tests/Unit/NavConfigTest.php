<?php

use Tests\TestCase;

uses(TestCase::class);

test('modifikasi barang jadi is nested under pengerjaan lanjutan submenu', function () {
    $config = file_get_contents(resource_path('js/components/fiori/nav-config.ts'));

    expect($config)->not->toBeFalse();

    preg_match(
        '/export const defaultModuleNavItems: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $moduleNav,
    );
    preg_match(
        '/export const defaultPengerjaanLanjutanSubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $pengerjaanLanjutan,
    );
    preg_match(
        '/export const defaultPostSpkDropdowns: ShellNavDropdown\[\] = \[(.*?)\];/s',
        (string) $config,
        $postSpk,
    );

    preg_match(
        '/export const defaultProduksiSubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $produksi,
    );

    expect($moduleNav[1] ?? '')->not->toContain('Modifikasi Barang Jadi')
        ->and($moduleNav[1] ?? '')->not->toContain('Pasang Batu')
        ->and($moduleNav[1] ?? '')->not->toContain('JewelCAD')
        ->and($moduleNav[1] ?? '')->toContain("'SPK'")
        ->and($moduleNav[1] ?? '')->toContain('SPK Reparasi')
        ->and($moduleNav[1] ?? '')->toContain('reparasiIndex.url()')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Modifikasi Barang Jadi')
        ->and($pengerjaanLanjutan[1] ?? '')->not->toContain('SPK Reparasi')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Penambahan Chain')
        ->and($postSpk[1] ?? '')->toContain('produksi')
        ->and($postSpk[1] ?? '')->toContain('defaultProduksiSubmenus')
        ->and($produksi[1] ?? '')->toContain('Pasang Batu')
        ->and($produksi[1] ?? '')->toContain('pasangBatuIndex.url()');
});

test('stone inventory modules are grouped under inventory batu submenu', function () {
    $config = file_get_contents(resource_path('js/components/fiori/nav-config.ts'));

    expect($config)->not->toBeFalse();

    preg_match(
        '/export const defaultModuleNavItems: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $moduleNav,
    );
    preg_match(
        '/export const defaultInventoryBatuSubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $inventoryBatu,
    );
    preg_match(
        '/export const defaultInventorySubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $inventory,
    );
    preg_match(
        '/export const defaultTrailingDropdowns: ShellNavDropdown\[\] = \[(.*?)\];/s',
        (string) $config,
        $trailing,
    );

    expect($moduleNav[1] ?? '')->not->toContain('SPK Diamond')
        ->and($moduleNav[1] ?? '')->not->toContain('Alokasi Batu')
        ->and($moduleNav[1] ?? '')->not->toContain('Pasang Batu')
        ->and($inventoryBatu[1] ?? '')->toContain('Alokasi Batu')
        ->and($inventoryBatu[1] ?? '')->toContain('SPK Diamond')
        ->and($inventoryBatu[1] ?? '')->toContain('Batu Mikro')
        ->and($inventoryBatu[1] ?? '')->toContain('Batu Dossier')
        ->and($inventoryBatu[1] ?? '')->toContain('Transaksi Batu')
        ->and($inventory[1] ?? '')->not->toContain('Batu Mikro')
        ->and($inventory[1] ?? '')->not->toContain('Transaksi Batu')
        ->and($trailing[1] ?? '')->toContain('inventory-batu')
        ->and($trailing[1] ?? '')->toContain('Inventory Batu');
});

test('process report pages are listed under analytics submenu', function () {
    $config = file_get_contents(resource_path('js/components/fiori/nav-config.ts'));

    expect($config)->not->toBeFalse();

    preg_match(
        '/export const defaultAnalyticsSubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $analytics,
    );

    expect($analytics[1] ?? '')->toContain('Laporan Finishing')
        ->and($analytics[1] ?? '')->toContain('finishingReport.url()')
        ->and($analytics[1] ?? '')->toContain('Laporan Poles Rangka')
        ->and($analytics[1] ?? '')->toContain('polesRangkaReport.url()')
        ->and($analytics[1] ?? '')->toContain('Laporan Poles Chrome')
        ->and($analytics[1] ?? '')->toContain('polesChromeReport.url()');
});
