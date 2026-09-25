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
            ->where('filters.search', '')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('filters.status', [])
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->where('filters.operator', null)
            ->where('filters.per_page', 50)
            ->has('filterOptions.status')
            ->has('filterOptions.operator')
            ->has('filterOptions.per_page')
            ->has('filterOptions.sort')
            ->has('filterOptions.direction')
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

test('jewelcad index can filter by status', function () {
    $unique = 'jwfilter'.Str::lower(Str::random(6));

    $draft = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-DRAFT",
        'status' => 'DRAFT',
    ]);
    $done = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-DONE",
        'status' => JewelCadApprovalService::STATUS_DONE,
    ]);

    $draftSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-DRAFT",
    ]);
    $doneSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-DONE",
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $draft->row_id,
        'spk_id' => $draftSpk->row_id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $done->row_id,
        'spk_id' => $doneSpk->row_id,
    ]);

    $this->get(route('jewelcad.index', [
        'status' => ['done'],
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/index')
            ->where('filters.status', ['done'])
            ->where('requests.total', 1)
            ->where('requests.data.0.requestId', $done->row_id)
            ->where('requests.data.0.docNo', $done->doc_no)
            ->where('requests.data.0.spkNo', $doneSpk->spk_no)
        );

    $this->get(route('jewelcad.index', [
        'status' => ['draft'],
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', ['draft'])
            ->where('requests.total', 1)
            ->where('requests.data.0.requestId', $draft->row_id)
        );
});

test('jewelcad index can filter by trans date range', function () {
    $unique = 'jwdate'.Str::lower(Str::random(6));

    $inside = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-IN",
        'trans_date' => '2026-09-15',
        'status' => 'DRAFT',
    ]);
    $outside = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-OUT",
        'trans_date' => '2026-08-01',
        'status' => 'DRAFT',
    ]);

    $insideSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-IN",
    ]);
    $outsideSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-OUT",
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $inside->row_id,
        'spk_id' => $insideSpk->row_id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $outside->row_id,
        'spk_id' => $outsideSpk->row_id,
    ]);

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->where('requests.total', 1)
            ->where('requests.data.0.requestId', $inside->row_id)
        );
});

test('jewelcad index can filter by operator', function () {
    $unique = 'jwop'.Str::lower(Str::random(6));
    $operator = "Operator {$unique}";

    $match = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-A",
        'operator' => $operator,
        'status' => 'DRAFT',
    ]);
    $other = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-B",
        'operator' => "Other {$unique}",
        'status' => 'DRAFT',
    ]);

    $matchSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-A",
    ]);
    $otherSpk = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-B",
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $match->row_id,
        'spk_id' => $matchSpk->row_id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $other->row_id,
        'spk_id' => $otherSpk->row_id,
    ]);

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'operator' => $operator,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.operator', $operator)
            ->where('requests.total', 1)
            ->where('requests.data.0.requestId', $match->row_id)
        );
});

test('jewelcad index can sort by document number', function () {
    $unique = 'jwsort'.Str::lower(Str::random(6));

    $first = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-A",
        'status' => 'DRAFT',
    ]);
    $second = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-B",
        'status' => 'DRAFT',
    ]);

    $spkFirst = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-A",
    ]);
    $spkSecond = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-B",
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $first->row_id,
        'spk_id' => $spkFirst->row_id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $second->row_id,
        'spk_id' => $spkSecond->row_id,
    ]);

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'sort' => 'id',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'asc')
            ->where('requests.data.0.requestId', $first->row_id)
            ->where('requests.data.1.requestId', $second->row_id)
        );

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'sort' => 'id',
        'direction' => 'desc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('requests.data.0.requestId', $second->row_id)
            ->where('requests.data.1.requestId', $first->row_id)
        );
});

test('jewelcad index can sort by spk number', function () {
    $unique = 'jwspk'.Str::lower(Str::random(6));

    $spkA = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-A",
    ]);
    $spkB = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-B",
    ]);

    $requestA = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-A",
        'status' => 'DRAFT',
    ]);
    $requestB = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}-B",
        'status' => 'DRAFT',
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $requestA->row_id,
        'spk_id' => $spkA->row_id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $requestB->row_id,
        'spk_id' => $spkB->row_id,
    ]);

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'sort' => 'spk',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'spk')
            ->where('filters.direction', 'asc')
            ->where('requests.data.0.requestId', $requestA->row_id)
            ->where('requests.data.0.spkNo', $spkA->spk_no)
            ->where('requests.data.1.requestId', $requestB->row_id)
            ->where('requests.data.1.spkNo', $spkB->spk_no)
        );
});

test('jewelcad index lists one row per spk detail', function () {
    $unique = 'jwflat'.Str::lower(Str::random(6));

    $request = JewelCadRequest::factory()->create([
        'doc_no' => "2026/JWC/{$unique}",
        'status' => 'DRAFT',
    ]);

    $skuA = SkuMaster::factory()->create([
        'sku_code' => "SKU-{$unique}-A",
        'item_original' => 'LR ELECTA OVAL 0.3 WG',
    ]);
    $skuB = SkuMaster::factory()->create([
        'sku_code' => "SKU-{$unique}-B",
        'item_original' => 'ER EMERALD CUT WG',
    ]);

    $spkA = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-A",
        'gold_weight' => 12.5,
        'satuan' => 'Pcs',
        'sku_id' => $skuA->id,
        'description' => 'White Gold Ladies Ring Electa',
        'jwcad_3d' => "FILE-{$unique}-A.3dm",
    ]);
    $spkB = Production::factory()->create([
        'spk_no' => "2026/PRD/{$unique}-B",
        'gold_weight' => 8.25,
        'satuan' => 'Pcs',
        'sku_id' => $skuB->id,
        'description' => 'White Gold Ear Ring Emerald',
        'jwcad_3d' => null,
    ]);

    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $spkA->row_id,
        'material' => 'Yellow Gold',
        'qty' => 2,
        'estimation_brj' => '18.125',
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $spkB->row_id,
        'material' => 'White Gold',
        'qty' => 1,
        'estimation_brj' => '9.500',
    ]);

    $this->get(route('jewelcad.index', [
        'search' => $unique,
        'sort' => 'spk',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('requests.total', 2)
            ->where('requests.data.0.requestId', $request->row_id)
            ->where('requests.data.0.docNo', $request->doc_no)
            ->where('requests.data.0.spkNo', $spkA->spk_no)
            ->where('requests.data.0.skuCode', $skuA->sku_code)
            ->where('requests.data.0.productItemName', 'LR ELECTA OVAL 0.3 WG')
            ->where('requests.data.0.itemDescription', 'White Gold Ladies Ring Electa')
            ->where('requests.data.0.material', 'Yellow Gold')
            ->where('requests.data.0.qty', 2)
            ->where('requests.data.0.qtyLabel', '2 Pcs')
            ->where('requests.data.0.jwcad3d', "FILE-{$unique}-A.3dm")
            ->where('requests.data.0.goldWeight', '12.50')
            ->where('requests.data.0.estimationBrj', '18.13')
            ->where('requests.data.1.requestId', $request->row_id)
            ->where('requests.data.1.docNo', $request->doc_no)
            ->where('requests.data.1.spkNo', $spkB->spk_no)
            ->where('requests.data.1.skuCode', $skuB->sku_code)
            ->where('requests.data.1.productItemName', 'ER EMERALD CUT WG')
            ->where('requests.data.1.itemDescription', 'White Gold Ear Ring Emerald')
            ->where('requests.data.1.material', 'White Gold')
            ->where('requests.data.1.qty', 1)
            ->where('requests.data.1.qtyLabel', '1 Pcs')
            ->where('requests.data.1.jwcad3d', null)
            ->where('requests.data.1.goldWeight', '8.25')
            ->where('requests.data.1.estimationBrj', '9.50')
        );
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
            'goldWeight' => '12.50',
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
        ->assertJsonPath('data.production.goldWeight', '8.00')
        ->assertJsonPath('data.item.masterGoldWeight', '8.00')
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
                'gold_weight' => '9.25',
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
        ->and((string) $detail->estimation_brj)->toBe('18.50');

    $production->refresh();

    expect((string) $production->gold_color)->toBe('White Gold')
        ->and(number_format((float) $production->gold_weight, 2, '.', ''))->toBe('9.25')
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
                'gold_weight' => '4.50',
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
                    'gold_weight' => '5.00',
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
        'description' => 'Deskripsi item JewelCAD',
        'sku_id' => $sku->id,
        'category_prefix_id' => $category->id,
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
        'spk_id' => $production->row_id,
        'material' => 'White Gold',
        'qty' => 1,
        'estimation_brj' => '12.50',
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
            ->where('requestItem.details.0.itemDescription', 'Deskripsi item JewelCAD')
            ->where('requestItem.details.0.satuan', '1 Pcs')
            ->where('requestItem.details.0.estimationBrj', '12.50')
            ->has('approvalFooter', 2)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->has('approvalHistory')
            ->has('approval')
            ->has('workflowStatus')
            ->where('workflowStatus.key', 'draft')
            ->where('workflowStatus.stageIndex', 0)
            ->has('workflowStatus.stages', 4)
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
        'estimation_brj' => '10.00',
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
