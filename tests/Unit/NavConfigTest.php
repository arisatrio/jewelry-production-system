<?php

use Tests\TestCase;

uses(TestCase::class);

test('module navigation is rendered inside the shell header', function () {
    $header = file_get_contents(resource_path('js/components/fiori/app-shell-header.tsx'));

    expect($header)->not->toBeFalse()
        ->and($header)->toContain('appNav--header')
        ->and($header)->toContain('shellHeaderMain')
        ->and($header)->not->toContain('<nav className="appNav" aria-label="Menu modul">');
});

test('spk index toolbar links to reparasi list beside tanda terima', function () {
    $index = file_get_contents(resource_path('js/pages/spk/index.tsx'));

    expect($index)->not->toBeFalse()
        ->and($index)->toContain('SPK Reparasi')
        ->and($index)->toContain('reparasiIndex.url()')
        ->and($index)->toContain('spkReceiptPrintBtn');
});

test('production process modules are grouped under produksi dropdown', function () {
    $config = file_get_contents(resource_path('js/components/fiori/nav-config.ts'));

    expect($config)->not->toBeFalse();

    preg_match(
        '/export const defaultModuleNavItems: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $moduleNav,
    );
    preg_match(
        '/export const defaultProduksiSubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $produksi,
    );
    preg_match(
        '/export const defaultPostSpkDropdowns: ShellNavDropdown\[\] = \[(.*?)\];/s',
        (string) $config,
        $postSpk,
    );

    $moduleBlock = $moduleNav[1] ?? '';
    $produksiBlock = $produksi[1] ?? '';

    expect($moduleBlock)->not->toContain('JewelCAD')
        ->and($moduleBlock)->not->toContain('Poles Chrome')
        ->and($postSpk[1] ?? '')->toContain("'produksi'")
        ->and($postSpk[1] ?? '')->toContain("'Produksi'")
        ->and($postSpk[1] ?? '')->toContain('defaultProduksiSubmenus')
        ->and($produksiBlock)->toContain('jewelCadIndex.url()')
        ->and($produksiBlock)->toContain('polesChromeIndex.url()');
});

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
        '/export const defaultTrailingDropdowns: ShellNavDropdown\[\] = \[(.*?)\];/s',
        (string) $config,
        $trailing,
    );

    expect($moduleNav[1] ?? '')->not->toContain('Modifikasi Barang Jadi')
        ->and($moduleNav[1] ?? '')->toContain("'SPK'")
        ->and($moduleNav[1] ?? '')->not->toContain('SPK Reparasi')
        ->and($moduleNav[1] ?? '')->not->toContain('reparasiIndex.url()')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Modifikasi Barang Jadi')
        ->and($pengerjaanLanjutan[1] ?? '')->not->toContain('Reparasi')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Penambahan Chain')
        ->and($trailing[1] ?? '')->not->toContain('pengerjaan-lanjutan')
        ->and($trailing[1] ?? '')->not->toContain('Pengerjaan Lanjutan');
});

test('inventory submenu lists stock and transaction items in order', function () {
    $config = file_get_contents(resource_path('js/components/fiori/nav-config.ts'));

    expect($config)->not->toBeFalse();

    preg_match(
        '/export const defaultInventorySubmenus: ShellNavItem\[\] = \[(.*?)\];/s',
        (string) $config,
        $inventory,
    );

    $block = $inventory[1] ?? '';

    expect($block)->toContain("'Stok Batu Dossier'")
        ->and($block)->toContain("'Transaksi Batu Dossier'")
        ->and($block)->toContain("'Stok Batu Mikro'")
        ->and($block)->toContain("'Stok Bahan Emas'")
        ->and($block)->toContain("'Stok Rantai'")
        ->and($block)->toContain("'Stok Komponen'")
        ->and($block)->toContain("'Transaksi Batu Mikro'")
        ->and($block)->toContain("'Transaksi Bahan Emas'")
        ->and($block)->toContain("'Transaksi Rantai'")
        ->and($block)->toContain("'Transaksi Komponen'")
        ->and($block)->not->toContain('Log Transaksi Chain')
        ->and($block)->not->toContain('Log Transaksi Komponen');

    $labels = [];
    preg_match_all("/text: '([^']+)'/", $block, $matches);

    foreach ($matches[1] ?? [] as $label) {
        $labels[] = $label;
    }

    expect($labels)->toContain('Rangka')
        ->and($labels)->toContain('Stok Rangka')
        ->and($labels)->toContain('Batu')
        ->and($labels)->toContain('Emas')
        ->and($labels)->toContain('Rantai & Komponen');

    expect($block)->toContain("text: 'Batu'")
        ->and($block)->toContain("text: 'Emas'")
        ->and($block)->toContain("text: 'Rantai & Komponen'")
        ->and($block)->not->toContain('Pembelian Batu')
        ->and($block)->not->toContain('section:');
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
