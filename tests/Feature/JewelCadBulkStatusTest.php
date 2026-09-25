<?php

use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use App\Support\JewelCadApprovalService;
use Illuminate\Support\Str;

test('jewelcad bulk status can submit selected draft requests', function () {
    $first = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/JWC/BS1'.Str::upper(Str::random(4)),
    ]);
    $second = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/JWC/BS2'.Str::upper(Str::random(4)),
    ]);
    $alreadySubmitted = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/JWC/BS3'.Str::upper(Str::random(4)),
    ]);

    $firstProduction = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/BS1'.Str::upper(Str::random(3)),
    ]);
    $secondProduction = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/BS2'.Str::upper(Str::random(3)),
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $first->row_id,
        'spk_id' => $firstProduction->row_id,
        'estimation_brj' => '10.00',
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $second->row_id,
        'spk_id' => $secondProduction->row_id,
        'estimation_brj' => '11.00',
    ]);

    $this->from(route('jewelcad.index'))
        ->post(route('jewelcad.bulk-status'), [
            'ids' => [$first->row_id, $second->row_id, $alreadySubmitted->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('jewelcad.index'));

    $first->refresh();
    $second->refresh();
    $alreadySubmitted->refresh();

    expect($first->status)->toBe(JewelCadApprovalService::STATUS_SUBMITTED)
        ->and($second->status)->toBe(JewelCadApprovalService::STATUS_SUBMITTED)
        ->and($alreadySubmitted->status)->toBe(JewelCadApprovalService::STATUS_SUBMITTED);
});

test('jewelcad bulk status can manager approve selected requests', function () {
    $pending = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/JWC/BMA'.Str::upper(Str::random(4)),
    ]);
    $draft = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/JWC/BMB'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('jewelcad.index'))
        ->post(route('jewelcad.bulk-status'), [
            'ids' => [$pending->row_id, $draft->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('jewelcad.index'));

    $pending->refresh();
    $draft->refresh();

    expect($pending->status)->toBe(JewelCadApprovalService::STATUS_MANAGER)
        ->and($draft->status)->toBe('DRAFT');
});

test('jewelcad bulk status can complete selected requests', function () {
    $ready = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
        'doc_no' => '2026/JWC/BCM'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('jewelcad.index'))
        ->post(route('jewelcad.bulk-status'), [
            'ids' => [$ready->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('jewelcad.index'));

    $ready->refresh();

    expect($ready->status)->toBe(JewelCadApprovalService::STATUS_DONE);
});

test('jewelcad bulk status validates payload', function () {
    $this->from(route('jewelcad.index'))
        ->post(route('jewelcad.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('jewelcad.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});

test('jewelcad index exposes bulk actions', function () {
    $this->get(route('jewelcad.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/index')
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('jewelcad bulk status can delete selected requests', function () {
    $draft = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/JWC/BD1'.Str::upper(Str::random(4)),
    ]);
    $pending = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/JWC/BD2'.Str::upper(Str::random(4)),
    ]);
    $done = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_DONE,
        'doc_no' => '2026/JWC/BD3'.Str::upper(Str::random(4)),
    ]);

    $draftDetail = JewelCadRequestDetail::factory()->create([
        'row_id' => $draft->row_id,
    ]);

    $this->from(route('jewelcad.index'))
        ->post(route('jewelcad.bulk-status'), [
            'ids' => [$draft->row_id, $pending->row_id, $done->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('jewelcad.index'));

    $draft->refresh();
    $pending->refresh();
    $done->refresh();
    $draftDetail->refresh();

    expect((int) $draft->is_deleted)->toBe(1)
        ->and((int) $pending->is_deleted)->toBe(1)
        ->and((int) $done->is_deleted)->toBe(1)
        ->and((int) $draftDetail->is_deleted)->toBe(1);
});
