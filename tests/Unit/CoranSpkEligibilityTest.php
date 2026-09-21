<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use App\Support\CoranApprovalService;
use App\Support\CoranSpkEligibility;
use App\Support\ResinApprovalService;
use App\Support\ResinSpkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('coran eligible scope counts spk completed resin without coran', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/COREL'.Str::upper(Str::random(4)),
        'last_process' => ResinSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => ResinApprovalService::STATUS_DONE,
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(CoranSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('coran eligible scope excludes spk already assigned to coran', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/COREX'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $spk->row_id,
        'status' => ResinApprovalService::STATUS_DONE,
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $spk->row_id,
    ]);
    $coran = Coran::factory()->create([
        'status' => null,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(CoranSpkEligibility::class)->applyEligibleScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeFalse();
});

test('coran mark process started updates last process to coran', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CMARK'.Str::upper(Str::random(4)),
        'last_process' => ResinSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $updated = app(CoranSpkEligibility::class)->markProcessStarted($production, 'Operator Coran');

    expect($updated->last_process)->toBe(CoranSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Coran');
});

test('coran sync last weight updates spk last weight from hasil coran', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CLW'.Str::upper(Str::random(4)),
        'last_weight' => null,
    ]);

    $updated = app(CoranSpkEligibility::class)->syncLastWeight($production, '3.25', 'Operator Coran');

    expect((float) $updated->last_weight)->toBe(3.25)
        ->and($updated->modified_by)->toBe('Operator Coran');
});

test('coran sync last weight skips empty weight', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CLWE'.Str::upper(Str::random(4)),
        'last_weight' => 1.5,
    ]);

    $updated = app(CoranSpkEligibility::class)->syncLastWeight($production, null, 'Operator Coran');

    expect((float) $updated->last_weight)->toBe(1.5);
});

test('coran in progress scope counts spk with open coran document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CIP'.Str::upper(Str::random(4)),
    ]);
    $coran = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_SUBMITTED,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(CoranSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('coran completed scope counts spk with done coran document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CDONE'.Str::upper(Str::random(4)),
    ]);
    $coran = Coran::factory()->done()->create();
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(CoranSpkEligibility::class)->applyCompletedScope($query))
        ->where('row_id', $production->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});
