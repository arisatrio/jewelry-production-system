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
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Modifikasi Barang Jadi')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Reparasi')
        ->and($pengerjaanLanjutan[1] ?? '')->toContain('Penambahan Chain')
        ->and($postSpk[1] ?? '')->toContain('pengerjaan-lanjutan')
        ->and($postSpk[1] ?? '')->toContain('defaultPengerjaanLanjutanSubmenus');
});
