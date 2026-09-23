<?php

use App\Models\DiamondMounting;
use App\Models\PolishFinishedGood;
use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\DiamondMountingSpkEligibility;
use App\Support\PolishFinishedGoodSpkEligibility;
use App\Support\PolishFrameSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('poles chrome eligible scope counts spk completed pasang batu without chrome', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGEL'.Str::upper(Str::random(4)),
        'last_process' => DiamondMountingSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    PolishFrame::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);
    DiamondMounting::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFinishedGoodSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('poles chrome eligible scope excludes spk with only poles rangka done', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGRK'.Str::upper(Str::random(4)),
        'last_process' => PolishFrameSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    PolishFrame::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFinishedGoodSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('poles chrome eligible scope excludes spk already assigned to chrome', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGEX'.Str::upper(Str::random(4)),
    ]);
    DiamondMounting::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);
    PolishFinishedGood::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => null,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFinishedGoodSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('poles chrome mark process started updates last process', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGMK'.Str::upper(Str::random(4)),
        'last_process' => DiamondMountingSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(PolishFinishedGoodSpkEligibility::class)->markProcessStarted($production, 'Operator Poles Chrome');

    expect($updated->last_process)->toBe(PolishFinishedGoodSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Poles Chrome');
});

test('poles chrome sync last weight copies finish weight to spk', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGLW'.Str::upper(Str::random(4)),
        'last_weight' => null,
    ]);

    $updated = app(PolishFinishedGoodSpkEligibility::class)->syncLastWeight($production, '3.25', 'Operator Poles Chrome');

    expect((float) $updated->last_weight)->toBe(3.25)
        ->and($updated->modified_by)->toBe('Operator Poles Chrome');
});
