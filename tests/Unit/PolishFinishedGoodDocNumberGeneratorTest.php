<?php

use App\Models\PolishFinishedGood;
use App\Support\PolishFinishedGoodDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('poles chrome doc number generator increments from highest pfg number', function () {
    $seed = PolishFinishedGood::factory()->create(['doc_no' => 'PFG9999988']);

    $next = app(PolishFinishedGoodDocNumberGenerator::class)->generate();

    expect($next)->toBe('PFG9999989');

    $seed->delete();
});

test('poles chrome doc number generator returns pfg padded format', function () {
    $next = app(PolishFinishedGoodDocNumberGenerator::class)->generate();

    expect($next)->toMatch('/^PFG\d{7}$/');
});
