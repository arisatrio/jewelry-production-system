<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Support\DiamondMountingDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('pasang batu doc number generator increments from latest dmd prefix', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTDOC'.Str::upper(Str::random(3)),
    ]);
    $seed = DiamondMounting::factory()->create([
        'doc_no' => 'DMD9999988',
        'spk_id' => $production->row_id,
    ]);

    $next = app(DiamondMountingDocNumberGenerator::class)->generate();

    expect($next)->toBe('DMD9999989');

    $seed->delete();
    $production->delete();
});

test('pasang batu doc number generator returns dmd format when empty', function () {
    $next = app(DiamondMountingDocNumberGenerator::class)->generate();

    expect($next)->toMatch('/^DMD\d{7}$/');
});
