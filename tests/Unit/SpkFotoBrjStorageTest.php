<?php

use App\Support\SpkItemImageUrl;
use App\Support\SpkService;
use Tests\TestCase;

uses(TestCase::class);

test('foto brj filename is derived from spk number', function () {
    $service = app(SpkService::class);

    expect($service->sanitizeSpkNoForFotoBrjFilename('2026/PRD/00123'))->toBe('2026-PRD-00123')
        ->and($service->sanitizeSpkNoForFotoBrjFilename(''))->toMatch('/^spk-\d+$/');
});

test('foto brj image url resolves under produksi brj folder', function () {
    config([
        'spk.production_image_base_url' => 'https://storage.googleapis.com/system-mahakarya/produksi/',
    ]);

    $url = SpkItemImageUrl::fromFotoBrj('brj/2026-PRD-00123.jpg');

    expect($url)->toBe('https://storage.googleapis.com/system-mahakarya/produksi/brj/2026-PRD-00123.jpg');
});
