<?php

use App\Models\Employee;
use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use App\Models\User;
use App\Support\GoogleCloudStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

test('jewelcad index page is accessible', function () {
    $this->get(route('jewelcad.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jewelcad/index')
            ->has('requests.data')
            ->has('requests.total')
            ->has('filters.search')
            ->has('filters.per_page')
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
            ->has('approvalFooter', 3)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', 'Operator Produksi')
            ->where('approvalFooter.0.date', fn ($date) => is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $date) === 1)
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->where('approvalFooter.2.title', 'Operator JewelCAD')
            ->where('approvalFooter.2.name', '-')
            ->where('approvalFooter.2.date', '-')
            ->missing('spkOptions')
        );
});

test('jewelcad spk selector endpoint returns spk data', function () {
    $production = Production::factory()->create([
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

    $production->delete();
});

test('jewelcad spk detail endpoint returns editable gold and stones payload', function () {
    $production = Production::factory()->create([
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

    $production->delete();
});

test('jewelcad request store updates master spk gold and stones', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator JewelCAD Test',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'gold_color' => 'Yellow Gold',
        'gold_weight' => 5.0,
        'qty' => 2,
        'notes' => 'Catatan SPK',
    ]);

    $this->post(route('jewelcad.store'), [
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
    ])->assertRedirect(route('jewelcad.index'));

    $request = JewelCadRequest::query()
        ->notDeleted()
        ->where('notes', 'Request baru')
        ->orderByDesc('row_id')
        ->first();

    expect($request)->not->toBeNull()
        ->and($request->doc_no)->toStartWith('JWC')
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
        ->and($production->jwcad_3d)->toBe('JWC-TEST-001');

    $detail->delete();
    $request->delete();
    $production->delete();
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
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/'.Str::upper(Str::random(5)),
        'gold_color' => 'Rose Gold',
        'gold_weight' => 4.5,
        'qty' => 1,
        'file_name' => null,
    ]);

    $file = UploadedFile::fake()->image('spk-jewelcad.png', 100, 100);

    $this->post(route('jewelcad.store'), [
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
    ])->assertRedirect(route('jewelcad.index'));

    $production->refresh();

    expect($production->jwcad_3d)->toBe('CAD-UPLOAD-1')
        ->and($production->file_name)->not->toBeNull()
        ->and($production->file_name)->toMatch('/^\d+\.png$/');

    JewelCadRequestDetail::query()
        ->where('spk_id', $production->row_id)
        ->delete();
    JewelCadRequest::query()
        ->where('notes', 'Upload foto SPK')
        ->delete();
    $production->delete();
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
            ->has('approvalFooter', 3)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', $request->created_by)
            ->where('approvalFooter.1.title', 'Manager Produksi')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->where('approvalFooter.2.title', 'Operator JewelCAD')
            ->where('approvalFooter.2.name', '-')
            ->where('approvalFooter.2.date', '-')
        );

    $detail->delete();
    $request->delete();
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
    $oldProduction = Production::factory()->create();
    $newProduction = Production::factory()->create([
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
    ])->assertRedirect(route('jewelcad.index'));

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

    $oldDetail->delete();
    $newDetail->delete();
    $request->delete();
    $oldProduction->delete();
    $newProduction->delete();
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

    $detail->delete();
    $request->delete();
});
