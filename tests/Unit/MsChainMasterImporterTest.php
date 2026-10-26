<?php

use App\Support\MsChainMasterImporter;
use Tests\TestCase;

uses(TestCase::class);

test('ms chain master importer parses length from csv text', function () {
    $importer = new MsChainMasterImporter;

    expect($importer->parseLengthFromCsv('18 cm'))->toBe('18.00')
        ->and($importer->parseLengthFromCsv('45,5 cm'))->toBe('45.50');
});

test('ms chain master importer rejects invalid length', function () {
    $importer = new MsChainMasterImporter;

    $importer->parseLengthFromCsv('invalid');
})->throws(InvalidArgumentException::class);
