<?php

use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\PolishFrameApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('poles rangka index exposes filters, filter options and bulk actions', function () {
    $this->get(route('poles-rangka.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/index')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('filters.status', [])
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->where('filters.craftsman', null)
            ->where('filters.per_page', 50)
            ->has('filterOptions.status', 3)
            ->has('filterOptions.craftsman')
            ->has('filterOptions.sort', 4)
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('poles rangka index lists craftsman, handover dates and weight gain', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKGN'.Str::upper(Str::random(3)),
    ]);
    $craftsman = DB::connection('third')
        ->table('mscraftsman')
        ->where('is_deleted', 0)
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->first(['row_id', 'name']);

    expect($craftsman)->not->toBeNull();

    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK9999921',
        'spk_id' => $production->row_id,
        'craftsman_id' => (int) $craftsman->row_id,
        'start_weight' => '2.00',
        'finish_weight' => '2.10',
        'shrink' => '-0.10',
        'send_craftsman_date' => '2026-09-10 08:30:00',
        'received_craftsman_date' => '2026-09-11 15:45:00',
    ]);

    $this->get(route('poles-rangka.index', ['search' => 'PRK9999921']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/index')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.craftsmanName', (string) $craftsman->name)
            ->where('documents.data.0.sendCraftsmanDate', '2026-09-10 08:30')
            ->where('documents.data.0.receivedCraftsmanDate', '2026-09-11 15:45')
            ->where('documents.data.0.shrink', '+0.10')
            ->where('documents.data.0.hasWeightGain', true)
        );

    $document->delete();
    $production->delete();
});

test('poles rangka index can filter by status', function () {
    $unique = 'prkstatus'.Str::lower(Str::random(6));
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKST'.Str::upper(Str::random(3)),
    ]);

    $openDocument = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'status' => null,
        'notes' => $unique,
    ]);
    $doneDocument = PolishFrame::factory()->done()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
    ]);

    $this->get(route('poles-rangka.index', ['status' => ['done'], 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', ['done'])
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $doneDocument->row_id)
        );

    $this->get(route('poles-rangka.index', ['status' => ['open'], 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $openDocument->row_id)
        );

    $openDocument->delete();
    $doneDocument->delete();
    $production->delete();
});

test('poles rangka index can filter by send craftsman date range and craftsman', function () {
    $unique = 'prkdate'.Str::lower(Str::random(6));
    $craftsmanIds = DB::connection('third')
        ->table('mscraftsman')
        ->where('is_deleted', 0)
        ->limit(2)
        ->pluck('row_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    expect($craftsmanIds)->toHaveCount(2);

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKDT'.Str::upper(Str::random(3)),
    ]);

    $inside = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftsman_id' => $craftsmanIds[0],
        'send_craftsman_date' => '2026-09-15 10:00:00',
    ]);
    $otherCraftsman = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftsman_id' => $craftsmanIds[1],
        'send_craftsman_date' => '2026-09-16 10:00:00',
    ]);
    $outside = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftsman_id' => $craftsmanIds[0],
        'send_craftsman_date' => '2026-08-01 10:00:00',
    ]);

    $this->get(route('poles-rangka.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('documents.data', 2)
        );

    $this->get(route('poles-rangka.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'craftsman' => $craftsmanIds[0],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.craftsman', $craftsmanIds[0])
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $inside->row_id)
        );

    $inside->delete();
    $otherCraftsman->delete();
    $outside->delete();
    $production->delete();
});

test('poles rangka index can change show entries per page', function () {
    $this->get(route('poles-rangka.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.per_page', 25)
            ->where('documents.per_page', 25)
        );

    $this->get(route('poles-rangka.index', ['per_page' => 999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.per_page', 50)
            ->where('documents.per_page', 50)
        );
});

test('poles rangka index can sort by id and spk number', function () {
    $unique = 'prksort'.Str::lower(Str::random(6));

    $earlySpk = Production::factory()->create([
        'spk_no' => '2026/PRD/AAA'.Str::upper(Str::random(3)),
    ]);
    $lateSpk = Production::factory()->create([
        'spk_no' => '2026/PRD/ZZZ'.Str::upper(Str::random(3)),
    ]);

    $lowerDoc = PolishFrame::factory()->create([
        'doc_no' => 'AAA'.Str::upper(Str::random(6)),
        'spk_id' => $lateSpk->row_id,
        'notes' => $unique,
    ]);
    $higherDoc = PolishFrame::factory()->create([
        'doc_no' => 'ZZZ'.Str::upper(Str::random(6)),
        'spk_id' => $earlySpk->row_id,
        'notes' => $unique,
    ]);

    $this->get(route('poles-rangka.index', ['sort' => 'id', 'direction' => 'asc', 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'asc')
            ->where('documents.data.0.id', $lowerDoc->row_id)
            ->where('documents.data.1.id', $higherDoc->row_id)
        );

    $this->get(route('poles-rangka.index', ['sort' => 'spk', 'direction' => 'asc', 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'spk')
            ->where('documents.data.0.id', $higherDoc->row_id)
            ->where('documents.data.1.id', $lowerDoc->row_id)
        );

    $lowerDoc->delete();
    $higherDoc->delete();
    $earlySpk->delete();
    $lateSpk->delete();
});

test('poles rangka bulk status can submit, approve and complete selected documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKBLK'.Str::upper(Str::random(3)),
    ]);

    $open = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'status' => null,
    ]);
    $alreadyDone = PolishFrame::factory()->done()->create([
        'spk_id' => $production->row_id,
    ]);

    $this->from(route('poles-rangka.index'))
        ->post(route('poles-rangka.bulk-status'), [
            'ids' => [$open->row_id, $alreadyDone->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('poles-rangka.index'));

    expect($open->refresh()->status)->toBe(PolishFrameApprovalService::STATUS_SUBMITTED)
        ->and($alreadyDone->refresh()->status)->toBe(PolishFrame::STATUS_DONE);

    $this->from(route('poles-rangka.index'))
        ->post(route('poles-rangka.bulk-status'), [
            'ids' => [$open->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('poles-rangka.index'));

    expect($open->refresh()->status)->toBe(PolishFrameApprovalService::STATUS_MANAGER);

    $this->from(route('poles-rangka.index'))
        ->post(route('poles-rangka.bulk-status'), [
            'ids' => [$open->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('poles-rangka.index'));

    expect($open->refresh()->status)->toBe(PolishFrameApprovalService::STATUS_DONE);

    DB::connection('third')
        ->table('sysapproval')
        ->where('doc_name', PolishFrameApprovalService::DOC_NAME)
        ->where('doc_id', $open->row_id)
        ->delete();

    $open->delete();
    $alreadyDone->delete();
    $production->delete();
});

test('poles rangka bulk status can delete eligible documents only', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKBDL'.Str::upper(Str::random(3)),
    ]);

    $open = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'status' => null,
    ]);
    $pending = PolishFrame::factory()->create([
        'spk_id' => $production->row_id,
        'status' => PolishFrameApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('poles-rangka.index'))
        ->post(route('poles-rangka.bulk-status'), [
            'ids' => [$open->row_id, $pending->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('poles-rangka.index'));

    expect((int) $open->refresh()->is_deleted)->toBe(1)
        ->and((int) $pending->refresh()->is_deleted)->toBe(0);

    $open->delete();
    $pending->delete();
    $production->delete();
});

test('poles rangka bulk status validates payload', function () {
    $this->from(route('poles-rangka.index'))
        ->post(route('poles-rangka.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('poles-rangka.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});
