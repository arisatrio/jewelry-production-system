<?php

use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use App\Support\ResinApprovalService;
use Illuminate\Support\Str;

test('resin bulk status can submit selected draft requests', function () {
    $first = Resin::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/RSN/BS1'.Str::upper(Str::random(4)),
    ]);
    $second = Resin::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/RSN/BS2'.Str::upper(Str::random(4)),
    ]);
    $alreadySubmitted = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/RSN/BS3'.Str::upper(Str::random(4)),
    ]);

    $firstProduction = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/RS1'.Str::upper(Str::random(3)),
    ]);
    $secondProduction = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/RS2'.Str::upper(Str::random(3)),
    ]);

    ResinDetail::factory()->create([
        'row_id' => $first->row_id,
        'spk_id' => $firstProduction->row_id,
        'berat_resin' => '10.00',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $second->row_id,
        'spk_id' => $secondProduction->row_id,
        'berat_resin' => '11.00',
    ]);

    $this->from(route('resin.index'))
        ->post(route('resin.bulk-status'), [
            'ids' => [$first->row_id, $second->row_id, $alreadySubmitted->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('resin.index'));

    $first->refresh();
    $second->refresh();
    $alreadySubmitted->refresh();

    expect($first->status)->toBe(ResinApprovalService::STATUS_SUBMITTED)
        ->and($second->status)->toBe(ResinApprovalService::STATUS_SUBMITTED)
        ->and($alreadySubmitted->status)->toBe(ResinApprovalService::STATUS_SUBMITTED);
});

test('resin bulk status can manager approve selected requests', function () {
    $pending = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/RSN/BMA'.Str::upper(Str::random(4)),
    ]);
    $draft = Resin::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/RSN/BMB'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('resin.index'))
        ->post(route('resin.bulk-status'), [
            'ids' => [$pending->row_id, $draft->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('resin.index'));

    $pending->refresh();
    $draft->refresh();

    expect($pending->status)->toBe(ResinApprovalService::STATUS_MANAGER)
        ->and($draft->status)->toBe('DRAFT');
});

test('resin bulk status can complete selected requests', function () {
    $ready = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_MANAGER,
        'doc_no' => '2026/RSN/BCM'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('resin.index'))
        ->post(route('resin.bulk-status'), [
            'ids' => [$ready->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('resin.index'));

    $ready->refresh();

    expect($ready->status)->toBe(ResinApprovalService::STATUS_DONE);
});

test('resin bulk status validates payload', function () {
    $this->from(route('resin.index'))
        ->post(route('resin.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('resin.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});

test('resin index exposes bulk actions', function () {
    $this->get(route('resin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/index')
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('resin bulk status can delete selected requests', function () {
    $draft = Resin::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => '2026/RSN/BD1'.Str::upper(Str::random(4)),
    ]);
    $pending = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/RSN/BD2'.Str::upper(Str::random(4)),
    ]);
    $done = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_DONE,
        'doc_no' => '2026/RSN/BD3'.Str::upper(Str::random(4)),
    ]);

    $draftDetail = ResinDetail::factory()->create([
        'row_id' => $draft->row_id,
    ]);

    $this->from(route('resin.index'))
        ->post(route('resin.bulk-status'), [
            'ids' => [$draft->row_id, $pending->row_id, $done->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('resin.index'));

    $draft->refresh();
    $pending->refresh();
    $done->refresh();
    $draftDetail->refresh();

    expect((int) $draft->is_deleted)->toBe(1)
        ->and((int) $pending->is_deleted)->toBe(1)
        ->and((int) $done->is_deleted)->toBe(1)
        ->and((int) $draftDetail->is_deleted)->toBe(1);
});
