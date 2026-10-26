<?php

use App\Models\TrxChain;
use App\Support\ChainUnitNumberGenerator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('trxchain')) {
        $this->markTestSkipped('Table trxchain is not available.');
    }
});

test('chain unit number generator produces sequential CHA numbers', function () {
    $generator = app(ChainUnitNumberGenerator::class);

    $anchor = $generator->generate();
    $unit = TrxChain::factory()->create(['unit_no' => $anchor]);

    $sequence = (int) substr($anchor, strlen(ChainUnitNumberGenerator::PREFIX));
    $expected = sprintf(
        '%s%0'.ChainUnitNumberGenerator::PADDING.'d',
        ChainUnitNumberGenerator::PREFIX,
        $sequence + 1,
    );

    try {
        expect($generator->generate())->toBe($expected);
    } finally {
        $unit->delete();
    }
});
