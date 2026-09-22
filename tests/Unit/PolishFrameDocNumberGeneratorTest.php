<?php

use App\Models\PolishFrame;
use App\Support\PolishFrameDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('polish frame doc number generator increments from highest prk number', function () {
    $seed = PolishFrame::factory()->create(['doc_no' => 'PRK9999988']);

    $next = app(PolishFrameDocNumberGenerator::class)->generate();

    expect($next)->toBe('PRK9999989');

    $seed->delete();
});

test('polish frame doc number generator returns prk padded format', function () {
    $next = app(PolishFrameDocNumberGenerator::class)->generate();

    expect($next)->toMatch('/^PRK\d{7}$/');
});
