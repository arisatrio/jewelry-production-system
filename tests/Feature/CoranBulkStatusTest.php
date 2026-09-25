<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranApprovalService;
use Illuminate\Support\Str;

test('coran bulk status can submit selected open documents', function () {
    $first = Coran::factory()->create([
        'status' => null,
        'doc_no' => 'CORBS1'.Str::upper(Str::random(4)),
    ]);
    $second = Coran::factory()->create([
        'status' => null,
        'doc_no' => 'CORBS2'.Str::upper(Str::random(4)),
    ]);
    $alreadySubmitted = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_SUBMITTED,
        'doc_no' => 'CORBS3'.Str::upper(Str::random(4)),
    ]);

    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/CB1'.Str::upper(Str::random(3)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/CB2'.Str::upper(Str::random(3)),
    ]);

    CoranSpk::factory()->create([
        'row_id' => $first->row_id,
        'spk_id' => $firstProduction->row_id,
        'weight' => '1.00',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $second->row_id,
        'spk_id' => $secondProduction->row_id,
        'weight' => '2.00',
    ]);

    $this->from(route('coran.index'))
        ->post(route('coran.bulk-status'), [
            'ids' => [$first->row_id, $second->row_id, $alreadySubmitted->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('coran.index'));

    $first->refresh();
    $second->refresh();
    $alreadySubmitted->refresh();

    expect($first->status)->toBe(CoranApprovalService::STATUS_SUBMITTED)
        ->and($second->status)->toBe(CoranApprovalService::STATUS_SUBMITTED)
        ->and($alreadySubmitted->status)->toBe(CoranApprovalService::STATUS_SUBMITTED);
});

test('coran bulk status can manager approve selected documents', function () {
    $pending = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_SUBMITTED,
        'doc_no' => 'CORBMA'.Str::upper(Str::random(4)),
    ]);
    $open = Coran::factory()->create([
        'status' => null,
        'doc_no' => 'CORBMB'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('coran.index'))
        ->post(route('coran.bulk-status'), [
            'ids' => [$pending->row_id, $open->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('coran.index'));

    $pending->refresh();
    $open->refresh();

    expect($pending->status)->toBe(CoranApprovalService::STATUS_MANAGER)
        ->and($open->status)->toBeNull();
});

test('coran bulk status can complete selected documents', function () {
    $ready = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_MANAGER,
        'doc_no' => 'CORBCM'.Str::upper(Str::random(4)),
    ]);

    $this->from(route('coran.index'))
        ->post(route('coran.bulk-status'), [
            'ids' => [$ready->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('coran.index'));

    $ready->refresh();

    expect($ready->status)->toBe(CoranApprovalService::STATUS_DONE);
});

test('coran bulk status validates payload', function () {
    $this->from(route('coran.index'))
        ->post(route('coran.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('coran.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});

test('coran index exposes bulk actions', function () {
    $this->get(route('coran.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/index')
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('coran bulk status can delete eligible documents', function () {
    $open = Coran::factory()->create([
        'status' => null,
        'doc_no' => 'CORBD1'.Str::upper(Str::random(4)),
    ]);
    $pending = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_SUBMITTED,
        'doc_no' => 'CORBD2'.Str::upper(Str::random(4)),
    ]);
    $done = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_DONE,
        'doc_no' => 'CORBD3'.Str::upper(Str::random(4)),
    ]);

    $openDetail = CoranSpk::factory()->create([
        'row_id' => $open->row_id,
    ]);

    $this->from(route('coran.index'))
        ->post(route('coran.bulk-status'), [
            'ids' => [$open->row_id, $pending->row_id, $done->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('coran.index'));

    $open->refresh();
    $pending->refresh();
    $done->refresh();
    $openDetail->refresh();

    // Coran canDelete = open OR done
    expect((int) $open->is_deleted)->toBe(1)
        ->and((int) $pending->is_deleted)->toBe(0)
        ->and((int) $done->is_deleted)->toBe(1)
        ->and((int) $openDetail->is_deleted)->toBe(1);
});
