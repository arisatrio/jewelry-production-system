<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran show exposes approval footer and abilities', function () {
    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->has('approvalFooter')
            ->has('approvalHistory')
            ->where('approval.canSubmit', true)
            ->where('approval.canOpenEdit', true)
            ->where('approval.canManagerApprove', false)
            ->where('approval.canComplete', false)
        );

    $coran->delete();
});

test('coran submit manager approve and complete flow', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORAPV'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->post(route('coran.submit', $coran))
        ->assertRedirect(route('coran.show', $coran));

    $coran->refresh();
    expect($coran->status)->toBe(CoranApprovalService::STATUS_SUBMITTED);

    $this->post(route('coran.manager-approve', $coran))
        ->assertRedirect(route('coran.show', $coran));

    $coran->refresh();
    expect($coran->status)->toBe(CoranApprovalService::STATUS_MANAGER);

    $this->post(route('coran.complete', $coran))
        ->assertRedirect(route('coran.show', $coran));

    $coran->refresh();
    expect($coran->status)->toBe(CoranApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        $logs = DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', CoranApprovalService::DOC_NAME)
            ->where('doc_id', $coran->row_id)
            ->orderBy('row_id')
            ->pluck('status')
            ->all();

        expect($logs)->toBe([
            CoranApprovalService::STATUS_SUBMITTED,
            CoranApprovalService::STATUS_MANAGER,
            CoranApprovalService::STATUS_DONE,
        ]);

        DB::connection('third')
            ->table('sysapproval')
            ->where('doc_name', CoranApprovalService::DOC_NAME)
            ->where('doc_id', $coran->row_id)
            ->delete();
    }

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
