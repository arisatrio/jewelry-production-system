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

    expect($moduleNav[1] ?? '')->not->toContain('Modifikasi Barang Jadi')
        ->and($moduleNav[1] ?? '')->toContain("'SPK'")
        ->and($moduleNav[1] ?? '')->toContain("'Reparasi'")
        ->and($moduleNav[1] ?? '')->toContain('reparasiIndex.url()')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Modifikasi Barang Jadi')
        ->and($pengerjaanLanjutan[1] ?? '')->not->toContain('Reparasi')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Penambahan Chain')
        ->and($postSpk[1] ?? '')->toContain('pengerjaan-lanjutan')
        ->and($postSpk[1] ?? '')->toContain('defaultPengerjaanLanjutanSubmenus');
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

    expect($labels)->toBe([
        'Stok Batu Dossier',
        'Stok Batu Mikro',
        'Stok Bahan Emas',
        'Stok Rantai',
        'Stok Komponen',
        'Transaksi Batu Dossier',
        'Transaksi Batu Mikro',
        'Transaksi Bahan Emas',
        'Transaksi Rantai',
        'Transaksi Komponen',
    ]);
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
