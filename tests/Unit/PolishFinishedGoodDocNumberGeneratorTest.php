<?php

use App\Models\Coran;
use App\Models\PolishFinishedGood;
use App\Support\PolishFinishedGoodDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('poles chrome doc number generator increments from highest cor number', function () {
    $seed = PolishFinishedGood::factory()->create(['doc_no' => 'COR9999988']);

    $next = app(PolishFinishedGoodDocNumberGenerator::class)->generate();

    expect($next)->toBe('COR9999989');

    $seed->delete();
});

test('poles chrome doc number generator returns cor padded format', function () {
    $next = app(PolishFinishedGoodDocNumberGenerator::class)->generate();

    expect($next)->toMatch('/^COR\d{7}$/');
});

test('poles chrome doc number generator ignores coran and pfg documents', function () {
    $pfgSeed = PolishFinishedGood::factory()->create(['doc_no' => 'PFG9999998']);
    $coran = Coran::factory()->create(['doc_no' => 'COR9999997']);
    $corSeed = PolishFinishedGood::factory()->create(['doc_no' => 'COR9999990']);

    $next = app(PolishFinishedGoodDocNumberGenerator::class)->generate();

    expect($next)->toBe('COR9999991');

    $pfgSeed->delete();
    $coran->delete();
    $corSeed->delete();
});
