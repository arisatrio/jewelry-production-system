<?php

use App\Models\Employee;
use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\User;
use App\Support\GoogleCloudStorageService;
use App\Support\JewelCadApprovalService;
use App\Support\JewelCadSpkEligibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

test('jewelcad index page is accessible', function () {
    $this->get(route('jewelcad.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/index')
            ->has('requests.data')
            ->has('requests.total')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('jewelcad index includes spk status counts', function () {
    $eligible = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/PEND'.Str::upper(Str::random(4)),
    ]);
    $inProgressSpk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/PROC'.Str::upper(Str::random(4)),
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $completedSpk = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/DONE'.Str::upper(Str::random(4)),
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    Production::factory()->create([
        'spk_no' => '2026/PRD/NOT'.Str::upper(Str::random(4)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);

    $inProgressRequest = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
        'doc_no' => '2026/JWC/PROC'.Str::upper(Str::random(4)),
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $inProgressRequest->row_id,
        'spk_id' => $inProgressSpk->row_id,
    ]);

    $completedRequest = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_DONE,
        'doc_no' => '2026/JWC/DONE'.Str::upper(Str::random(4)),
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $completedRequest->row_id,
        'spk_id' => $completedSpk->row_id,
    ]);

    $this->get(route('jewelcad.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
        );

    $this->getJson(route('jewelcad.select.spks', ['queue' => 'pending']))
        ->assertOk()
        ->assertJsonFragment(['spkNo' => $eligible->spk_no]);

    $this->getJson(route('jewelcad.select.spks', ['queue' => 'inProgress']))
        ->assertOk()
        ->assertJsonFragment([
            'spkNo' => $inProgressSpk->spk_no,
            'requestId' => $inProgressRequest->row_id,
            'docNo' => $inProgressRequest->doc_no,
        ]);

    $this->getJson(route('jewelcad.select.spks', ['queue' => 'completed']))
        ->assertOk()
        ->assertJsonFragment([
            'spkNo' => $completedSpk->spk_no,
            'requestId' => $completedRequest->row_id,
            'docNo' => $completedRequest->doc_no,
        ]);
});

test('jewelcad create page is accessible', function () {
    Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Produksi',
    ]);

    $this->actingAs(User::factory()->adminSpk()->create([
        'name' => 'Operator Produksi',
    ]));

    $this->get(route('jewelcad.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/create')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-002')
            ->where('form.operator', 'Operator Produksi')
            ->has('form.transDate')
            ->has('form.details', 0)
            ->has('operatorOptions')
            ->has('approvalFooter', 2)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', 'Operator Produksi')
            ->where('approvalFooter.0.date', fn ($date) => is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $date) === 1)
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->missing('spkOptions')
        );
});

test('jewelcad spk selector endpoint returns manager approved spk data only', function () {
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/JWC'.Str::upper(Str::random(4)),
        'gold_color' => 'White Gold',
        'gold_weight' => 12.5,
        'qty' => 2,
        'notes' => 'Catatan SPK',
    ]);
    $pending = Production::factory()->create([
        'spk_no' => '2026/PRD/PND'.Str::upper(Str::random(4)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);

    $this->getJson(route('jewelcad.select.spks', ['search' => '2026/PRD/']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
        ])
        ->assertJsonMissing([
            'rowId' => $pending->row_id,
        ]);
});

test('jewelcad spk selector endpoint returns spk data', function () {
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/JWC'.Str::upper(Str::random(4)),
        'gold_color' => 'White Gold',
        'gold_weight' => 12.5,
        'qty' => 2,
        'notes' => 'Catatan SPK',
    ]);

    $this->getJson(route('jewelcad.select.spks', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'goldColor' => 'White Gold',
            'goldWeight' => '12.500',
            'qty' => 2,
            'notes' => 'Catatan SPK',
        ]);
});

test('jewelcad spk detail endpoint returns editable gold and stones payload', function () {
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/DET'.Str::upper(Str::random(4)),
        'gold_color' => 'Rose Gold',
        'gold_weight' => 8.0,
        'notes' => 'Catatan detail',
        'sku_id' => null,
    ]);

    $this->getJson(route('jewelcad.spk.detail', $production->row_id))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.production.spkNo', $production->spk_no)
        ->assertJsonPath('data.production.goldColor', 'Rose Gold')
        ->assertJsonPath('data.production.goldWeight', '8.000')
        ->assertJsonPath('data.item.masterGoldWeight', '8.000')
        ->assertJsonStructure([
            'data' => [
                'item' => [
                    'masterGoldWeight',
                    'jwcad3d',
                    'fileName',
                    'imageUrl',
                ],
                'masterStoneCount',
                'stones',
                'options' => ['goldColors', 'shapeOptions', 'positionOptions'],
            ],
        ]);
});

test('jewelcad request store updates master spk gold and stones', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator JewelCAD Test',
    ]);
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'gold_color' => 'Yellow Gold',
        'gold_weight' => 5.0,
        'qty' => 2,
        'notes' => 'Catatan SPK',
    ]);

    $response = $this->post(route('jewelcad.store'), [
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-20',
        'notes' => 'Request baru',
        'details' => [
            [
                'spk_id' => $production->row_id,
                'material' => 'White Gold',
                'gold_weight' => '9.250',
                'jwcad_3d' => 'JWC-TEST-001',
                'qty' => 2,
                'estimation_brj' => '18.50',
                'notes' => 'Catatan SPK',
                'stones' => [],
            ],
        ],
    ]);

    $request = JewelCadRequest::query()
        ->notDeleted()
        ->where('notes', 'Request baru')
        ->orderByDesc('row_id')
        ->first();

    $response->assertRedirect(route('jewelcad.show', $request));

    expect($request)->not->toBeNull()
        ->and($request->doc_no)->toMatch('/^\d{4}\/JWC\/\d{5}$/')
        ->and($request->operator)->toBe('Operator JewelCAD Test')
        ->and($request->created_by)->toBe('system');

    $detail = JewelCadRequestDetail::query()
        ->notDeleted()
        ->where('row_id', $request->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and($detail->spk_id)->toBe($production->row_id)
        ->and($detail->material)->toBe('White Gold')
        ->and($detail->qty)->toBe(2)
        ->and((string) $detail->estimation_brj)->toBe('18.500');

    $production->refresh();

    expect((string) $production->gold_color)->toBe('White Gold')
        ->and(number_format((float) $production->gold_weight, 3, '.', ''))->toBe('9.250')
        ->and($production->jwcad_3d)->toBe('JWC-TEST-001')
        ->and($production->last_process)->toBe(JewelCadSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1);
});

test('jewelcad store uploads spk image file', function () {
    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->andReturnUsing(function ($file, string $folder, string $filename): string {
                return "https://storage.googleapis.com/system-mahakarya/{$folder}/{$filename}";
            });
    });

    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Upload JewelCAD',
    ]);
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'gold_color' => 'Rose Gold',
        'gold_weight' => 4.5,
        'qty' => 1,
        'file_name' => null,
    ]);

    $file = UploadedFile::fake()->image('spk-jewelcad.png', 100, 100);

    $response = $this->post(route('jewelcad.store'), [
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-21',
        'notes' => 'Upload foto SPK',
        'details' => [
            [
                'spk_id' => $production->row_id,
                'material' => 'Rose Gold',
                'gold_weight' => '4.500',
                'jwcad_3d' => 'CAD-UPLOAD-1',
                'qty' => 1,
                'estimation_brj' => '10.00',
                'notes' => 'Catatan',
                'stones' => [],
                'file' => $file,
            ],
        ],
    ]);

    $request = JewelCadRequest::query()
        ->notDeleted()
        ->where('notes', 'Upload foto SPK')
        ->orderByDesc('row_id')
        ->first();

    $response->assertRedirect(route('jewelcad.show', $request));

    $production->refresh();

    expect($production->jwcad_3d)->toBe('CAD-UPLOAD-1')
        ->and($production->file_name)->not->toBeNull()
        ->and($production->file_name)->toMatch('/^\d+\.png$/');
});

test('jewelcad store rejects spk that is not manager approved', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Reject Test',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);

    $this->from(route('jewelcad.create'))
        ->post(route('jewelcad.store'), [
            'operator' => $operator->nama_lengkap,
            'trans_date' => '2026-08-20',
            'notes' => 'Reject test',
            'details' => [
                [
                    'spk_id' => $production->row_id,
                    'material' => 'White Gold',
                    'gold_weight' => '5.000',
                    'qty' => 1,
                    'estimation_brj' => '10.00',
                    'notes' => 'Catatan',
                    'stones' => [],
                ],
            ],
        ])
        ->assertRedirect(route('jewelcad.create'))
        ->assertSessionHasErrors(['details.0.spk_id']);

    $production->refresh();

    expect($production->last_process)->toBeNull()
        ->and($production->is_inprocess)->toBe(0);
});

test('jewelcad store validates details', function () {
    $this->from(route('jewelcad.create'))
        ->post(route('jewelcad.store'), [
            'operator' => '',
            'trans_date' => '',
            'details' => [],
        ])
        ->assertRedirect(route('jewelcad.create'))
        ->assertSessionHasErrors(['operator', 'trans_date', 'details']);
});

test('jewelcad show page is accessible', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first();
    $sku = $category !== null
        ? SkuMaster::query()
            ->where('category_prefix_id', $category->id)
            ->whereNotNull('sku_code')
            ->whereNotNull('item_original')
            ->orderBy('id')
            ->first()
        : null;

    if ($category === null || $sku === null) {
        $this->markTestSkipped('Membutuhkan data SKU master dan kategori prefix yang sudah ada.');
    }

    $request = JewelCadRequest::factory()->create([
        'notes' => 'Detail request test',
    ]);
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'gold_color' => 'White Gold',
        'gold_weight' => 6.5,
        'qty' => 1,
        'notes' => 'Catatan SPK detail',
        'sku_id' => $sku->id,
        'category_prefix_id' => $category->id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $production->row_id,
        'material' => 'White Gold',
        'qty' => 1,
        'estimation_brj' => '12.500',
    ]);

    $this->get(route('jewelcad.show', $request))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/show')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-002')
            ->where('requestItem.id', $request->row_id)
            ->where('requestItem.docNo', $request->doc_no)
            ->where('requestItem.operator', $request->operator)
            ->where('requestItem.notes', 'Detail request test')
            ->where('requestItem.createdBy', $request->created_by)
            ->has('requestItem.details', 1)
            ->where('requestItem.details.0.spkId', $production->row_id)
            ->where('requestItem.details.0.spkNo', $production->spk_no)
            ->where('requestItem.details.0.skuCode', $sku->sku_code)
            ->where('requestItem.details.0.typeCode', $category->prefix)
            ->where('requestItem.details.0.productItemName', $sku->item_original)
            ->where('requestItem.details.0.satuan', '1 Pcs')
            ->where('requestItem.details.0.estimationBrj', '12.500')
            ->has('approvalFooter', 2)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->has('approvalHistory')
            ->has('approval')
            ->where('approval.canSubmit', true)
            ->where('approval.canEdit', true)
            ->where('approval.canOpenEdit', true)
            ->where('approval.canDelete', true)
            ->where('approval.status', 'DRAFT')
        );
});

test('jewelcad show page returns not found for deleted request', function () {
    $request = JewelCadRequest::factory()->create([
        'is_deleted' => 1,
    ]);

    $this->get(route('jewelcad.show', $request))
        ->assertNotFound();
});

test('jewelcad draft request can be submitted to manager', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
    ]);
    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
    ]);
    $detail = JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $production->row_id,
        'estimation_brj' => '10.000',
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.submit', $request))
        ->assertRedirect(route('jewelcad.show', $request));

    $request->refresh();

    expect($request->status)->toBe(JewelCadApprovalService::STATUS_SUBMITTED);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        expect(
            DB::connection('third')
                ->table('sysapproval')
                ->where('doc_name', JewelCadApprovalService::DOC_NAME)
                ->where('doc_id', $request->row_id)
                ->where('status', JewelCadApprovalService::STATUS_SUBMITTED)
                ->where('approve', JewelCadApprovalService::APPROVE_OK)
                ->exists(),
        )->toBeTrue();
    }
});

test('jewelcad submit rejects non draft request', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.submit', $request))
        ->assertRedirect(route('jewelcad.show', $request))
        ->assertSessionHasErrors(['approval']);
});

test('jewelcad pending request can be approved by manager', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.manager-approve', $request))
        ->assertRedirect(route('jewelcad.show', $request));

    $request->refresh();

    expect($request->status)->toBe(JewelCadApprovalService::STATUS_MANAGER);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        expect(
            DB::connection('third')
                ->table('sysapproval')
                ->where('doc_name', JewelCadApprovalService::DOC_NAME)
                ->where('doc_id', $request->row_id)
                ->where('status', JewelCadApprovalService::STATUS_MANAGER)
                ->where('approve', JewelCadApprovalService::APPROVE_OK)
                ->exists(),
        )->toBeTrue();
    }
});

test('jewelcad manager approve rejects non pending request', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.manager-approve', $request))
        ->assertForbidden();
});

test('jewelcad manager approved request can be completed', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.complete', $request))
        ->assertRedirect(route('jewelcad.show', $request));

    $request->refresh();

    expect($request->status)->toBe(JewelCadApprovalService::STATUS_DONE);

    if (Schema::connection('third')->hasTable('sysapproval')) {
        expect(
            DB::connection('third')
                ->table('sysapproval')
                ->where('doc_name', JewelCadApprovalService::DOC_NAME)
                ->where('doc_id', $request->row_id)
                ->where('status', JewelCadApprovalService::STATUS_DONE)
                ->where('approve', JewelCadApprovalService::APPROVE_OK)
                ->exists(),
        )->toBeTrue();
    }
});

test('jewelcad complete rejects non manager approved request', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('jewelcad.show', $request))
        ->post(route('jewelcad.complete', $request))
        ->assertForbidden();
});

test('jewelcad edit page is accessible', function () {
    $request = JewelCadRequest::factory()->create();
    $detail = JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
    ]);

    $this->get(route('jewelcad.edit', $request))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/edit')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-002')
            ->where('requestItem.id', $request->row_id)
            ->where('requestItem.docNo', $request->doc_no)
            ->where('requestItem.operator', $request->operator)
            ->has('requestItem.details', 1)
            ->has('operatorOptions')
            ->has('approvalFooter', 2)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', $request->created_by)
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->has('approval')
            ->where('approval.canSubmit', true)
        );
});

test('jewelcad request can be updated', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Update Test',
    ]);
    $request = JewelCadRequest::factory()->create([
        'doc_no' => 'JWCUPDATE'.Str::upper(Str::random(4)),
        'notes' => 'Catatan lama',
        'operator' => $operator->nama_lengkap,
    ]);
    $oldProduction = Production::factory()->managerApproved()->create([
        'last_process' => JewelCadSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);
    $newProduction = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
    ]);
    $oldDetail = JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $oldProduction->row_id,
        'material' => 'Yellow Gold',
        'qty' => 1,
        'estimation_brj' => '10.00',
    ]);

    $this->put(route('jewelcad.update', $request), [
        'doc_no' => $request->doc_no,
        'operator' => $operator->nama_lengkap,
        'trans_date' => '2026-08-21',
        'notes' => 'Catatan baru',
        'details' => [
            [
                'spk_id' => $newProduction->row_id,
                'material' => 'Rose Gold',
                'qty' => 3,
                'estimation_brj' => '21.75',
                'notes' => 'Revisi',
            ],
        ],
    ])->assertRedirect(route('jewelcad.show', $request));

    $request->refresh();
    $oldDetail->refresh();

    expect($request->notes)->toBe('Catatan baru')
        ->and($request->operator)->toBe('Operator Update Test')
        ->and($oldDetail->is_deleted)->toBe(1);

    $newDetail = JewelCadRequestDetail::query()
        ->notDeleted()
        ->where('row_id', $request->row_id)
        ->where('spk_id', $newProduction->row_id)
        ->first();

    expect($newDetail)->not->toBeNull()
        ->and($newDetail->material)->toBe('Rose Gold')
        ->and($newDetail->qty)->toBe(3);
});

test('jewelcad request can be soft deleted', function () {
    $request = JewelCadRequest::factory()->create();
    $detail = JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
    ]);

    $this->delete(route('jewelcad.destroy', $request))
        ->assertRedirect(route('jewelcad.index'));

    $request->refresh();
    $detail->refresh();

    expect($request->is_deleted)->toBe(1)
        ->and($detail->is_deleted)->toBe(1);
});
