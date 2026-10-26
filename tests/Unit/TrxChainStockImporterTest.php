<?php

use App\Support\TrxChainStockImporter;
use Tests\TestCase;

uses(TestCase::class);

test('trx chain stock importer parses csv dates', function () {
    $importer = app(TrxChainStockImporter::class);

    expect($importer->parseDateFromCsv('27/03/2026'))->toBe('2026-03-27')
        ->and($importer->parseDateFromCsv(''))->toBeNull();
});

test('trx chain stock importer parses purchase prices', function () {
    $importer = app(TrxChainStockImporter::class);

    expect($importer->parsePurchasePriceFromCsv('$215,00'))->toBe('215.00')
        ->and($importer->parsePurchasePriceFromCsv('4.729.397,00'))->toBe('4729397.00')
        ->and($importer->parsePurchasePriceFromCsv('Rp2.167.189'))->toBe('2167189.00')
        ->and($importer->parsePurchasePriceFromCsv('328,52'))->toBe('328.52');
});

test('trx chain stock importer parses weight', function () {
    $importer = app(TrxChainStockImporter::class);

    expect($importer->parseWeightFromCsv('2,03'))->toBe('2.03');
});

test('trx chain stock importer allocates sequential item codes per model', function () {
    $importer = app(TrxChainStockImporter::class);
    $sequence = [];

    expect($importer->allocateItemCode('K18SHM2P18', $sequence))->toBe('K18SHM2P18')
        ->and($importer->allocateItemCode('K18SHM2P18', $sequence))->toBe('K18SHM2P18-2')
        ->and($importer->allocateItemCode('CDOV035', $sequence))->toBe('CDOV035');
});
