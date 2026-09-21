<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\CoranApprovalService;
use App\Support\CoranSpkEligibility;
use App\Support\FinishingSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('finishing eligible scope counts spk completed coran without finishing', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/FINEL'.Str::upper(Str::random(4)),
        'last_process' => CoranSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $coran = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_DONE,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(FinishingSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('finishing eligible scope excludes spk already assigned to finishing', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/FINEX'.Str::upper(Str::random(4)),
    ]);
    $coran = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_DONE,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $spk->row_id,
    ]);
    FinishingHandmade::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => null,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(FinishingSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('finishing mark process started updates last process to finishing', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FMARK'.Str::upper(Str::random(4)),
        'last_process' => CoranSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(FinishingSpkEligibility::class)->markProcessStarted($production, 'Operator Finishing');

    expect($updated->last_process)->toBe(FinishingSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Finishing');
});

test('finishing in progress scope counts spk with open finishing document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FIP'.Str::upper(Str::random(4)),
    ]);
    FinishingHandmade::factory()->create([
        'spk_id' => $production->row_id,
        'status' => FinishingHandmade::STATUS_TO_CRAFTSMAN,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(FinishingSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('finishing completed scope counts spk with done finishing document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FDONE'.Str::upper(Str::random(4)),
    ]);
    FinishingHandmade::factory()->done()->create([
        'spk_id' => $production->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(FinishingSpkEligibility::class)->applyCompletedScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});
