<?php

use App\Support\FinishingDocNumberGenerator;

test('finishing doc number generator class is instantiable', function () {
    $generator = new FinishingDocNumberGenerator;

    expect($generator)->toBeInstanceOf(FinishingDocNumberGenerator::class);
});
