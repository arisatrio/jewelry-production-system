<?php

use App\Support\SpkDiamondDocumentPrintPresenter;

test('spk diamond print presenter maps sku fields and omits removed meta', function () {
    $presenter = new SpkDiamondDocumentPrintPresenter;

    $result = $presenter->fromDetailPayload([
        'docNo' => 'MKDM/PRINT/1',
        'tanggal' => '2026-11-05',
        'spkNo' => 'SPK/TEST/1',
        'typeSkuLabel' => 'RNG | ABC123',
        'itemDescription' => 'Deskripsi SKU test',
        'createdBy' => 'tester',
        'createdDate' => '2026-11-05 10:00:00',
        'items' => [],
        'totals' => ['pcs' => 0, 'carat' => 0.0],
    ]);

    expect($result['sku'])->toBe('RNG | ABC123')
        ->and($result['skuDescription'])->toBe('Deskripsi SKU test')
        ->and($result)->not->toHaveKeys(['statusLabel', 'itemName', 'lineCount']);
});
