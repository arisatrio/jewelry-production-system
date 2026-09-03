<?php

use App\Models\Coran;
use App\Support\CoranDocNumberGenerator;
use Tests\TestCase;

uses(TestCase::class);

test('coran doc number generator increments from highest cor prefix', function () {
    $seed = Coran::factory()->create([
        'doc_no' => 'COR9999980',
    ]);

    try {
        $generator = app(CoranDocNumberGenerator::class);

        expect($generator->generate())->toBe('COR9999981');
    } finally {
        $seed->delete();
    }
});

test('coran doc number generator pads seven digits', function () {
    $generator = app(CoranDocNumberGenerator::class);
    $next = $generator->generate();

    expect($next)->toMatch('/^COR\d{7}$/');
});
