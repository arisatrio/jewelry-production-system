<?php

use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use App\Support\JewelCadApprovalService;
use App\Support\JewelCadSpkEligibility;
use App\Support\SpkApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('jewelcad spk eligibility requires manager approved spk without last process', function () {
    $eligible = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/ELIG001',
    ]);
    $pending = Production::factory()->create([
        'spk_no' => '2026/PRD/PEND001',
        'status' => SpkApprovalService::STATUS_PENDING,
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $inProcess = Production::factory()->create([
        'spk_no' => '2026/PRD/PROC001',
        'status' => SpkApprovalService::STATUS_DONE,
        'last_process' => 'Coran',
        'is_inprocess' => 1,
    ]);

    $service = app(JewelCadSpkEligibility::class);

    expect($service->isEligible($eligible))->toBeTrue()
        ->and($service->isEligible($pending))->toBeFalse()
        ->and($service->isEligible($inProcess))->toBeFalse();
});

test('jewelcad mark process started updates last process and inprocess flag', function () {
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/MARK001',
    ]);

    $updated = app(JewelCadSpkEligibility::class)->markProcessStarted($production, 'Operator Test');

    expect($updated->last_process)->toBe(JewelCadSpkEligibility::PROCESS_KEY)
        ->and($updated->is_inprocess)->toBe(1)
        ->and($updated->modified_by)->toBe('Operator Test');
});

test('jewelcad selectable scope allows existing request spk on edit', function () {
    $request = JewelCadRequest::factory()->create();
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/EDIT001',
        'status' => SpkApprovalService::STATUS_DONE,
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $production->row_id,
    ]);

    $ids = Production::query()
        ->tap(fn ($query) => app(JewelCadSpkEligibility::class)->applySelectableScope(
            $query,
            (int) $request->row_id,
        ))
        ->pluck('row_id')
        ->all();

    expect($ids)->toContain($production->row_id);
});

test('jewelcad in progress scope counts spk in active request', function () {
    $spk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/ACTIVE'.Str::upper(Str::random(4)),
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $matches = Production::query()
        ->tap(fn ($query) => app(JewelCadSpkEligibility::class)->applyInProgressScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('jewelcad completed scope counts spk in finished request', function () {
    $spk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/FIN'.Str::upper(Str::random(4)),
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
        ->tap(fn ($query) => app(JewelCadSpkEligibility::class)->applyCompletedScope($query))
        ->where('row_id', $spk->row_id)
        ->exists();

    expect($matches)->toBeTrue();
});

test('jewelcad request refs by spk ids returns latest request doc no', function () {
    $spk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/REF'.Str::upper(Str::random(4)),
    ]);
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
        'doc_no' => '2026/JWC/REF'.Str::upper(Str::random(4)),
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $refs = app(JewelCadSpkEligibility::class)->requestRefsBySpkIds(
        [$spk->row_id],
        completed: false,
    );

    expect($refs[$spk->row_id])->toMatchArray([
        'requestId' => $request->row_id,
        'docNo' => $request->doc_no,
    ]);
});
