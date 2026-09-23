<?php

use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Support\PolishFinishedGoodApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('poles chrome show exposes approval footer and abilities', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGAPV'.Str::upper(Str::random(3)),
    ]);
    $document = PolishFinishedGood::factory()->create([
        'doc_no' => 'PFG'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->get(route('poles-chrome.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-chrome/show')
            ->has('approvalFooter')
            ->has('approvalHistory')
            ->has('polishFinishedGoodItem')
            ->where('approval.canSubmit', true)
            ->where('approval.canOpenEdit', true)
            ->where('approval.canManagerApprove', false)
            ->where('approval.canComplete', false)
            ->where('workflowStatus.stages.0.label', 'Open')
            ->where('workflowStatus.stages.1.label', 'Pengajuan')
            ->where('workflowStatus.stages.2.label', 'Serahkan ke PPIC')
            ->where('workflowStatus.stages.3.label', 'Done')
        );

    $document->delete();
    $production->delete();
});

test('poles chrome submit manager approve and complete flow', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGFLW'.Str::upper(Str::random(3)),
    ]);
    $document = PolishFinishedGood::factory()->create([
        'doc_no' => 'PFG'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->post(route('poles-chrome.submit', $document))
        ->assertRedirect(route('poles-chrome.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFinishedGoodApprovalService::STATUS_SUBMITTED);

    $this->post(route('poles-chrome.manager-approve', $document))
        ->assertRedirect(route('poles-chrome.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFinishedGoodApprovalService::STATUS_MANAGER);

    $this->post(route('poles-chrome.complete', $document))
        ->assertRedirect(route('poles-chrome.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFinishedGoodApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        $logs = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', PolishFinishedGoodApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->orderBy('row_id')
            ->pluck('status')
            ->all();

        expect($logs)->toBe([
            PolishFinishedGoodApprovalService::STATUS_SUBMITTED,
            PolishFinishedGoodApprovalService::STATUS_MANAGER,
            PolishFinishedGoodApprovalService::STATUS_DONE,
        ]);

        DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', PolishFinishedGoodApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->delete();
    }

    $document->delete();
    $production->delete();
});

test('poles chrome index page exposes documents and spk status counts', function () {
    $this->get(route('poles-chrome.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-chrome/index')
            ->has('documents.data')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
        );
});
