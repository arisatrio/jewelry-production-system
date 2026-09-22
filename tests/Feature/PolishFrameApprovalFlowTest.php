<?php

use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\PolishFrameApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('poles rangka show exposes approval footer and abilities', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKAPV'.Str::upper(Str::random(3)),
    ]);
    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->get(route('poles-rangka.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/show')
            ->has('approvalFooter')
            ->has('approvalHistory')
            ->has('polishFrameItem')
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

test('poles rangka submit manager approve and complete flow', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKFLW'.Str::upper(Str::random(3)),
    ]);
    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->post(route('poles-rangka.submit', $document))
        ->assertRedirect(route('poles-rangka.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFrameApprovalService::STATUS_SUBMITTED);

    $this->post(route('poles-rangka.manager-approve', $document))
        ->assertRedirect(route('poles-rangka.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFrameApprovalService::STATUS_MANAGER);

    $this->post(route('poles-rangka.complete', $document))
        ->assertRedirect(route('poles-rangka.show', $document));

    $document->refresh();
    expect($document->status)->toBe(PolishFrameApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        $logs = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', PolishFrameApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->orderBy('row_id')
            ->pluck('status')
            ->all();

        expect($logs)->toBe([
            PolishFrameApprovalService::STATUS_SUBMITTED,
            PolishFrameApprovalService::STATUS_MANAGER,
            PolishFrameApprovalService::STATUS_DONE,
        ]);

        DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', PolishFrameApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->delete();
    }

    $document->delete();
    $production->delete();
});

test('poles rangka index page exposes documents and spk status counts', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKIDX'.Str::upper(Str::random(3)),
    ]);

    $document = PolishFrame::factory()->done()->create([
        'doc_no' => 'PRK9999911',
        'spk_id' => $production->row_id,
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'shrink' => '0.71',
        'notes' => 'Catatan poles rangka list',
        'send_craftsman_date' => '2026-08-25 10:16:58',
    ]);

    $this->get(route('poles-rangka.index', ['search' => 'PRK9999911']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/index')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.docNo', 'PRK9999911')
            ->where('documents.data.0.spkNo', $production->spk_no)
            ->where('documents.data.0.startWeight', '3.16')
            ->where('documents.data.0.finishWeight', '2.45')
            ->where('documents.data.0.shrink', '0.71')
            ->where('documents.data.0.statusLabel', 'Completed')
            ->where('documents.data.0.notes', 'Catatan poles rangka list')
            ->where('documents.data.0.transDate', '2026-08-25')
        );

    $document->delete();
    $production->delete();
});

test('poles rangka select spks queue returns in progress polish frame documents', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKQI'.Str::upper(Str::random(4)),
    ]);
    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => $spk->row_id,
        'status' => null,
    ]);

    $this->get(route('poles-rangka.select.spks', [
        'queue' => 'inProgress',
        'limit' => 50,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => (int) $spk->row_id,
            'spkNo' => $spk->spk_no,
            'polishFrameId' => (int) $document->row_id,
            'docNo' => $document->doc_no,
        ]);

    $document->delete();
    $spk->delete();
});
