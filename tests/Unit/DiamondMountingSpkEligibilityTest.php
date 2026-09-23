<?php

use App\Models\DiamondMounting;
use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\DiamondMountingSpkEligibility;
use App\Support\PolishFrameSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('pasang batu eligible scope counts spk completed poles rangka without mounting', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTEL'.Str::upper(Str::random(4)),
        'last_process' => PolishFrameSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    PolishFrame::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(DiamondMountingSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('pasang batu eligible scope excludes spk already assigned to mounting', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTEX'.Str::upper(Str::random(4)),
    ]);
    PolishFrame::factory()->done()->create([
        'spk_id' => $spk->row_id,
    ]);
    DiamondMounting::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => null,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(DiamondMountingSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('pasang batu mark process started updates last process', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTMK'.Str::upper(Str::random(4)),
        'last_process' => PolishFrameSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(DiamondMountingSpkEligibility::class)->markProcessStarted($production, 'Operator Pasang Batu');

    expect($updated->last_process)->toBe(DiamondMountingSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Pasang Batu');
});

test('pasang batu sync last weight copies finish weight to spk', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTLW'.Str::upper(Str::random(4)),
        'last_weight' => null,
    ]);

    $updated = app(DiamondMountingSpkEligibility::class)->syncLastWeight($production, '3.25', 'Operator Pasang Batu');

    expect((float) $updated->last_weight)->toBe(3.25)
        ->and($updated->modified_by)->toBe('Operator Pasang Batu');
});
