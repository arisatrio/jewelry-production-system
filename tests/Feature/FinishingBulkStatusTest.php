<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\FinishingApprovalService;
use Illuminate\Support\Str;

test('finishing bulk status can submit selected open documents', function () {
    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/BULK'.Str::upper(Str::random(3)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/BULK'.Str::upper(Str::random(3)),
    ]);

    $first = FinishingHandmade::factory()->create([
        'doc_no' => 'FB1'.Str::upper(Str::random(6)),
        'spk_id' => $firstProduction->row_id,
        'status' => null,
    ]);
    $second = FinishingHandmade::factory()->create([
        'doc_no' => 'FB2'.Str::upper(Str::random(6)),
        'spk_id' => $secondProduction->row_id,
        'status' => null,
    ]);
    $alreadySubmitted = FinishingHandmade::factory()->create([
        'doc_no' => 'FB3'.Str::upper(Str::random(6)),
        'spk_id' => $firstProduction->row_id,
        'status' => FinishingApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('finishing.index'))
        ->post(route('finishing.bulk-status'), [
            'ids' => [$first->row_id, $second->row_id, $alreadySubmitted->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('finishing.index'));

    $first->refresh();
    $second->refresh();
    $alreadySubmitted->refresh();

    expect($first->status)->toBe(FinishingApprovalService::STATUS_SUBMITTED)
        ->and($second->status)->toBe(FinishingApprovalService::STATUS_SUBMITTED)
        ->and($alreadySubmitted->status)->toBe(FinishingApprovalService::STATUS_SUBMITTED);

    $first->delete();
    $second->delete();
    $alreadySubmitted->delete();
    $firstProduction->delete();
    $secondProduction->delete();
});

test('finishing bulk status can manager approve selected documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/BMAP'.Str::upper(Str::random(3)),
    ]);

    $pending = FinishingHandmade::factory()->create([
        'doc_no' => 'FBA'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => FinishingApprovalService::STATUS_SUBMITTED,
    ]);
    $open = FinishingHandmade::factory()->create([
        'doc_no' => 'FBB'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $this->from(route('finishing.index'))
        ->post(route('finishing.bulk-status'), [
            'ids' => [$pending->row_id, $open->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('finishing.index'));

    $pending->refresh();
    $open->refresh();

    expect($pending->status)->toBe(FinishingApprovalService::STATUS_MANAGER)
        ->and($open->status)->toBeNull();

    $pending->delete();
    $open->delete();
    $production->delete();
});

test('finishing bulk status can complete selected documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/BCMP'.Str::upper(Str::random(3)),
    ]);

    $ready = FinishingHandmade::factory()->create([
        'doc_no' => 'FBC'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => FinishingApprovalService::STATUS_MANAGER,
    ]);

    $this->from(route('finishing.index'))
        ->post(route('finishing.bulk-status'), [
            'ids' => [$ready->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('finishing.index'));

    $ready->refresh();

    expect($ready->status)->toBe(FinishingApprovalService::STATUS_DONE);

    $ready->delete();
    $production->delete();
});

test('finishing bulk status validates payload', function () {
    $this->from(route('finishing.index'))
        ->post(route('finishing.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('finishing.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});

test('finishing index exposes document status counts', function () {
    $this->get(route('finishing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('finishing bulk status can delete eligible documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/BDEL'.Str::upper(Str::random(3)),
    ]);

    $open = FinishingHandmade::factory()->create([
        'doc_no' => 'FBD'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);
    $pending = FinishingHandmade::factory()->create([
        'doc_no' => 'FBE'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => FinishingApprovalService::STATUS_SUBMITTED,
    ]);
    $done = FinishingHandmade::factory()->create([
        'doc_no' => 'FBF'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'status' => FinishingApprovalService::STATUS_DONE,
    ]);

    $this->from(route('finishing.index'))
        ->post(route('finishing.bulk-status'), [
            'ids' => [$open->row_id, $pending->row_id, $done->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('finishing.index'));

    $open->refresh();
    $pending->refresh();
    $done->refresh();

    expect((int) $open->is_deleted)->toBe(1)
        ->and((int) $pending->is_deleted)->toBe(0)
        ->and((int) $done->is_deleted)->toBe(0);

    $open->delete();
    $pending->delete();
    $done->delete();
    $production->delete();
});
