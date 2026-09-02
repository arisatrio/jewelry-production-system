<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use App\Support\ResinApprovalService;
use App\Support\ResinSpkEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function resinStorePayload(int $spkId, string $operator, array $extra = []): array
{
    return array_merge([
        'operator' => $operator,
        'trans_date' => '2026-08-24',
        'notes' => 'Catatan dokumen resin',
        'details' => [
            [
                'spk_id' => $spkId,
                'berat_resin' => '3.250',
                'catatan' => 'Catatan resin',
            ],
        ],
    ], $extra);
}

test('resin index page is accessible', function () {
    $this->get(route('resin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/index')
            ->has('resins.data')
            ->has('resins.total')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('resin index lists total berat resin from detail rows', function () {
    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/RSNTOTA',
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/RSNTOTB',
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $productionA->row_id,
        'doc_no' => '2026/RSN/RSNTOT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $productionA->row_id,
        'berat_resin' => '1.500',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $productionB->row_id,
        'berat_resin' => '2.750',
    ]);

    $this->get(route('resin.index', ['search' => '2026/RSN/RSNTOT']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/index')
            ->where('resins.data.0.id', $resin->row_id)
            ->where('resins.data.0.totalBeratResin', '4.250')
        );

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $productionA->delete();
    $productionB->delete();
});

test('resin create page is accessible', function () {
    $this->get(route('resin.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/create')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-003')
            ->has('form.transDate')
            ->has('form.operator')
            ->has('form.details')
            ->has('statusOptions')
            ->has('operatorOptions')
            ->has('approvalFooter', 2)
        );
});

test('resin spk selector endpoint returns spk data', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RSL'.Str::upper(Str::random(4)),
        'item_name' => 'Resin Selector Item',
        'customer_name' => 'Resin Customer',
    ]);

    $this->getJson(route('resin.select.spks', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'item' => 'Resin Selector Item',
            'customer' => 'Resin Customer',
        ]);

    $production->delete();
});

test('resin spk selector endpoint supports queue filter', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RQF'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'status' => 'DRAFT',
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->getJson(route('resin.select.spks', ['queue' => 'inProgress']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'resinId' => $resin->row_id,
            'docNo' => $resin->doc_no,
        ]);

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin store creates document with detail row', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Store',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RST'.Str::upper(Str::random(4)),
    ]);

    $this->post(route('resin.store'), resinStorePayload(
        $production->row_id,
        (string) $operator->nama_lengkap,
    ))->assertRedirect();

    $resin = Resin::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($resin)->not->toBeNull()
        ->and($resin?->status)->toBe('DRAFT')
        ->and($resin?->doc_no)->toMatch('/^\d{4}\/RSN\/\d{5}$/')
        ->and($resin?->operator)->toBe('Operator Resin Store')
        ->and($resin?->notes)->toBe('Catatan dokumen resin');

    $detail = ResinDetail::query()
        ->notDeleted()
        ->where('row_id', $resin?->row_id)
        ->where('spk_id', $production->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and($detail?->berat_resin)->toBe('3.250')
        ->and($detail?->status_resin)->toBeNull()
        ->and($detail?->catatan)->toBe('Catatan resin');

    $production->refresh();

    expect($production->last_process)->toBe(ResinSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1);

    if ($detail !== null) {
        $detail->delete();
    }

    if ($resin !== null) {
        $resin->delete();
    }

    $operator->delete();
    $production->delete();
});

test('resin store creates document with multiple spk details', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Multi',
    ]);
    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RSM'.Str::upper(Str::random(4)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RSN'.Str::upper(Str::random(4)),
    ]);

    $this->post(route('resin.store'), [
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-24',
        'details' => [
            [
                'spk_id' => $firstProduction->row_id,
                'berat_resin' => '1.500',
                'catatan' => 'SPK pertama',
            ],
            [
                'spk_id' => $secondProduction->row_id,
                'berat_resin' => '2.750',
                'status_resin' => ResinDetail::STATUS_OK,
                'catatan' => 'SPK kedua',
            ],
        ],
    ])->assertRedirect();

    $resin = Resin::query()
        ->notDeleted()
        ->where('spk_id', $firstProduction->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($resin)->not->toBeNull()
        ->and($resin?->status)->toBe('DRAFT')
        ->and($resin?->operator)->toBe('Operator Resin Multi');

    $details = ResinDetail::query()
        ->notDeleted()
        ->where('row_id', $resin?->row_id)
        ->orderBy('line_id')
        ->get();

    expect($details)->toHaveCount(2)
        ->and($details[0]->spk_id)->toBe($firstProduction->row_id)
        ->and($details[1]->spk_id)->toBe($secondProduction->row_id);

    $details->each->delete();

    if ($resin !== null) {
        $resin->delete();
    }

    $operator->delete();
    $firstProduction->delete();
    $secondProduction->delete();
});

test('resin show page is accessible', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RSH'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
        'operator' => 'Operator Resin Show',
        'status' => 'DRAFT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
        'berat_resin' => '2.500',
        'status_resin' => ResinDetail::STATUS_OK,
        'catatan' => 'Detail show test',
    ]);

    $this->get(route('resin.show', $resin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/show')
            ->where('resinItem.id', $resin->row_id)
            ->where('resinItem.docNo', $resin->doc_no)
            ->where('resinItem.operator', 'Operator Resin Show')
            ->has('resinItem.details', 1)
            ->where('resinItem.details.0.satuan', fn ($satuan) => is_string($satuan))
            ->has('approvalFooter', 2)
            ->has('approvalHistory')
            ->has('approval')
            ->has('workflowStatus')
            ->where('approval.canSubmit', true)
        );

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin edit page is accessible', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RED'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
        'operator' => 'Operator Resin Edit',
        'status' => 'DRAFT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->get(route('resin.edit', $resin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/edit')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-003')
            ->where('resinItem.id', $resin->row_id)
            ->where('resinItem.docNo', $resin->doc_no)
            ->where('resinItem.operator', 'Operator Resin Edit')
            ->has('resinItem.details', 1)
            ->where('resinItem.details.0.satuan', fn ($satuan) => is_string($satuan))
            ->has('operatorOptions')
            ->has('approvalFooter', 2)
            ->has('approval')
            ->where('approval.canSubmit', true)
        );

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin update changes document fields', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Update',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RUP'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
        'status' => 'DRAFT',
        'operator' => 'Operator Lama',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->put(route('resin.update', $resin), [
        'doc_no' => $resin->doc_no,
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-25',
        'notes' => 'Catatan diperbarui',
        'details' => [
            [
                'spk_id' => $production->row_id,
                'berat_resin' => '4.125',
                'status_resin' => ResinDetail::STATUS_OK,
                'catatan' => 'Selesai',
            ],
        ],
    ])->assertRedirect(route('resin.show', $resin));

    $resin->refresh();

    expect($resin->trans_date?->format('Y-m-d'))->toBe('2026-08-25')
        ->and($resin->status)->toBe('DRAFT')
        ->and($resin->operator)->toBe('Operator Resin Update')
        ->and($resin->notes)->toBe('Catatan diperbarui');

    $detail = ResinDetail::query()
        ->notDeleted()
        ->where('row_id', $resin->row_id)
        ->where('spk_id', $production->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and($detail?->berat_resin)->toBe('4.125')
        ->and($detail?->status_resin)->toBe(ResinDetail::STATUS_OK)
        ->and($detail?->catatan)->toBe('Selesai');

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $operator->delete();
    $production->delete();
});

test('resin update accepts empty status resin when adding spk on draft', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Draft',
    ]);
    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RAD'.Str::upper(Str::random(4)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RAD'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $firstProduction->row_id,
        'doc_no' => '2026/RSN/'.Str::upper(Str::random(5)),
        'status' => 'DRAFT',
        'operator' => 'Operator Lama',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $firstProduction->row_id,
        'status_resin' => ResinDetail::STATUS_OPEN,
    ]);

    $this->put(route('resin.update', $resin), [
        'doc_no' => $resin->doc_no,
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-25',
        'details' => [
            [
                'spk_id' => $firstProduction->row_id,
                'berat_resin' => '',
                'status_resin' => '',
                'catatan' => '',
            ],
            [
                'spk_id' => $secondProduction->row_id,
                'berat_resin' => '',
                'status_resin' => '',
                'catatan' => '',
            ],
        ],
    ])->assertRedirect(route('resin.show', $resin));

    $details = ResinDetail::query()
        ->notDeleted()
        ->where('row_id', $resin->row_id)
        ->orderBy('line_id')
        ->get();

    expect($details)->toHaveCount(2)
        ->and($details[0]->status_resin)->toBeNull()
        ->and($details[1]->status_resin)->toBeNull();

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $operator->delete();
    $firstProduction->delete();
    $secondProduction->delete();
});

test('resin update allows adding spk while pending manager approval', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Submitted',
    ]);
    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RSB'.Str::upper(Str::random(4)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/RSB'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->submitted()->create([
        'spk_id' => $firstProduction->row_id,
        'operator' => 'Operator Lama',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $firstProduction->row_id,
    ]);

    $this->put(route('resin.update', $resin), [
        'doc_no' => $resin->doc_no,
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-25',
        'notes' => 'Tambah SPK sebelum approval',
        'details' => [
            [
                'spk_id' => $firstProduction->row_id,
                'berat_resin' => '1,250',
                'status_resin' => ResinDetail::STATUS_OK,
                'catatan' => '',
            ],
            [
                'spk_id' => $secondProduction->row_id,
                'berat_resin' => '2,333',
                'status_resin' => ResinDetail::STATUS_OK,
                'catatan' => '',
            ],
        ],
    ])->assertRedirect(route('resin.show', $resin));

    $resin->refresh();

    expect($resin->status)->toBe(ResinApprovalService::STATUS_SUBMITTED)
        ->and($resin->notes)->toBe('Tambah SPK sebelum approval');

    $details = ResinDetail::query()
        ->notDeleted()
        ->where('row_id', $resin->row_id)
        ->orderBy('line_id')
        ->get();

    expect($details)->toHaveCount(2)
        ->and($details[1]?->berat_resin)->toBe('2.333');

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $operator->delete();
    $firstProduction->delete();
    $secondProduction->delete();
});

test('resin update rejects changes after manager approval', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin Locked',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RLK'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->managerApproved()->create([
        'spk_id' => $production->row_id,
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->put(route('resin.update', $resin), resinStorePayload(
        $production->row_id,
        (string) $operator->nama_lengkap,
    ))->assertForbidden();

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $operator->delete();
    $production->delete();
});

test('resin destroy soft deletes document and details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RDL'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
        'status' => 'DRAFT',
    ]);
    $detail = ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->delete(route('resin.destroy', $resin))
        ->assertRedirect(route('resin.index'));

    $resin->refresh();
    $detail->refresh();

    expect($resin->is_deleted)->toBe(1)
        ->and($detail->is_deleted)->toBe(1);

    $detail->delete();
    $resin->delete();
    $production->delete();
});

test('resin draft request can be submitted to manager', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RSUB'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'status' => 'DRAFT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->post(route('resin.submit', $resin))
        ->assertRedirect(route('resin.show', $resin));

    expect($resin->refresh()->status)->toBe(ResinApprovalService::STATUS_SUBMITTED);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        expect(
            DB::connection('third')
                ->table('sysapproval')
                ->where('doc_name', ResinApprovalService::DOC_NAME)
                ->where('doc_id', $resin->row_id)
                ->where('status', ResinApprovalService::STATUS_SUBMITTED)
                ->exists(),
        )->toBeTrue();
    }

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin manager approved request can be completed', function () {
    $resin = Resin::factory()->managerApproved()->create();

    $this->post(route('resin.complete', $resin))
        ->assertRedirect(route('resin.show', $resin));

    expect($resin->refresh()->status)->toBe(ResinApprovalService::STATUS_DONE);

    $resin->delete();
});

test('resin progress can be updated when status is serahkan ke resin', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RPR'.Str::upper(Str::random(4)),
    ]);
    $resin = Resin::factory()->managerApproved()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
        'spk_id' => $production->row_id,
        'berat_resin' => null,
        'status_resin' => null,
        'catatan' => null,
    ]);

    $this->put(route('resin.update-progress', $resin), [
        'details' => [
            [
                'spk_id' => $production->row_id,
                'berat_resin' => '2.500',
                'status_resin' => ResinDetail::STATUS_OK,
                'catatan' => 'Progress resin',
            ],
        ],
    ])->assertRedirect(route('resin.show', $resin));

    $detail = ResinDetail::query()
        ->where('row_id', $resin->row_id)
        ->where('spk_id', $production->row_id)
        ->where('is_deleted', 0)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->berat_resin)->toBe('2.500')
        ->and($detail->status_resin)->toBe(ResinDetail::STATUS_OK)
        ->and($detail->catatan)->toBe('Progress resin')
        ->and($resin->refresh()->status)->toBe(ResinApprovalService::STATUS_MANAGER);

    ResinDetail::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});
