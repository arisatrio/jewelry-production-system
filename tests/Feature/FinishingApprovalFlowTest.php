<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\FinishingApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('finishing show exposes approval footer and abilities', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINAPV'.Str::upper(Str::random(3)),
    ]);
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->get(route('finishing.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/show')
            ->has('approvalFooter')
            ->has('approvalHistory')
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

test('finishing submit manager approve and complete flow', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINFLW'.Str::upper(Str::random(3)),
    ]);
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->post(route('finishing.submit', $document))
        ->assertRedirect(route('finishing.show', $document));

    $document->refresh();
    expect($document->status)->toBe(FinishingApprovalService::STATUS_SUBMITTED);

    $this->post(route('finishing.manager-approve', $document))
        ->assertRedirect(route('finishing.show', $document));

    $document->refresh();
    expect($document->status)->toBe(FinishingApprovalService::STATUS_MANAGER);

    $this->post(route('finishing.complete', $document))
        ->assertRedirect(route('finishing.show', $document));

    $document->refresh();
    expect($document->status)->toBe(FinishingApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        $logs = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', FinishingApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->orderBy('row_id')
            ->pluck('status')
            ->all();

        expect($logs)->toBe([
            FinishingApprovalService::STATUS_SUBMITTED,
            FinishingApprovalService::STATUS_MANAGER,
            FinishingApprovalService::STATUS_DONE,
        ]);

        DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', FinishingApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->delete();
    }

    $document->delete();
    $production->delete();
});
