<?php

use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use App\Support\JewelCadApprovalService;
use App\Support\JewelCadSpkEligibility;
use App\Support\ResinSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('resin eligible scope counts spk completed jewelcad without resin', function () {
    $spk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/RESIN'.Str::upper(Str::random(4)),
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_DONE,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(ResinSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('resin mark process started updates last process to resin', function () {
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/RMARK'.Str::upper(Str::random(4)),
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(ResinSpkEligibility::class)->markProcessStarted($production, 'Operator Resin');

    expect($updated->last_process)->toBe(ResinSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Resin');
});

test('resin mark process started is idempotent when already in resin', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RIDEM'.Str::upper(Str::random(4)),
        'last_process' => ResinSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
        'modified_by' => 'Existing Actor',
    ]);

    $updated = app(ResinSpkEligibility::class)->markProcessStarted($production, 'Operator Baru');

    expect($updated->last_process)->toBe(ResinSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Existing Actor');
});

test('resin in progress scope counts spk with open resin document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RIP'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'status' => 'DRAFT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
        'status_resin' => null,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(ResinSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin in progress scope counts spk from legacy resin header without details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RLG'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'status' => 'DRAFT',
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(ResinSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();

    $resin->delete();
    $production->delete();
});

test('resin completed scope counts spk with done resin document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RCP'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'status' => Resin::STATUS_DONE,
    ]);
    ResinDetail::factory()->done()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(ResinSpkEligibility::class)->applyCompletedScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});
