<?php

use App\Models\FinishingHandmade;
use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\FinishingSpkEligibility;
use App\Support\PolishFrameSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('polish frame eligible scope counts spk completed finishing without polish frame', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKEL'.Str::upper(Str::random(4)),
        'last_process' => FinishingSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    FinishingHandmade::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFrameSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('polish frame eligible scope excludes spk already assigned to polish frame', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKEX'.Str::upper(Str::random(4)),
    ]);
    FinishingHandmade::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);
    PolishFrame::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => null,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFrameSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('polish frame mark process started updates last process to poles rangka', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKMK'.Str::upper(Str::random(4)),
        'last_process' => FinishingSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(PolishFrameSpkEligibility::class)->markProcessStarted($production, 'Operator Poles Rangka');

    expect($updated->last_process)->toBe(PolishFrameSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Poles Rangka');
});

test('polish frame in progress scope counts spk with open polish frame document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKIP'.Str::upper(Str::random(4)),
    ]);
    PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'status' => PolishFrame::STATUS_TO_CRAFTSMAN,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFrameSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('polish frame completed scope counts spk with done polish frame document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKDN'.Str::upper(Str::random(4)),
    ]);
    PolishFrame::factory()->done()->create([
        'spk_id' => $production->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(PolishFrameSpkEligibility::class)->applyCompletedScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});
