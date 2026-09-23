<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Support\DiamondMountingApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('pasang batu show exposes approval footer and abilities', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTAPV'.Str::upper(Str::random(3)),
    ]);
    $document = DiamondMounting::factory()->create([
        'doc_no' => 'DMD'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/show')
            ->has('approvalFooter')
            ->has('approvalHistory')
            ->has('diamondMountingItem')
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

test('pasang batu submit manager approve and complete flow', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTFLW'.Str::upper(Str::random(3)),
    ]);
    $document = DiamondMounting::factory()->create([
        'doc_no' => 'DMD'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->post(route('pasang-batu.submit', $document))
        ->assertRedirect(route('pasang-batu.show', $document));

    $document->refresh();
    expect($document->status)->toBe(DiamondMountingApprovalService::STATUS_SUBMITTED);

    $this->post(route('pasang-batu.manager-approve', $document))
        ->assertRedirect(route('pasang-batu.show', $document));

    $document->refresh();
    expect($document->status)->toBe(DiamondMountingApprovalService::STATUS_MANAGER);

    $this->post(route('pasang-batu.complete', $document))
        ->assertRedirect(route('pasang-batu.show', $document));

    $document->refresh();
    expect($document->status)->toBe(DiamondMountingApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        $logs = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', DiamondMountingApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->orderBy('row_id')
            ->pluck('status')
            ->all();

        expect($logs)->toBe([
            DiamondMountingApprovalService::STATUS_SUBMITTED,
            DiamondMountingApprovalService::STATUS_MANAGER,
            DiamondMountingApprovalService::STATUS_DONE,
        ]);

        DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', DiamondMountingApprovalService::DOC_NAME)
            ->where('doc_id', $document->row_id)
            ->delete();
    }

    $document->delete();
    $production->delete();
});

test('pasang batu index page exposes documents and spk status counts', function () {
    $this->get(route('pasang-batu.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/index')
            ->has('documents.data')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
        );
});
