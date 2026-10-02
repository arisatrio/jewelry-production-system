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
