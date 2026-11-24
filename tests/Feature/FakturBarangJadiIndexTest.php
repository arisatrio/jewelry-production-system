<?php

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiWorkflowStatus;
use App\Support\SpkApprovalService;
use Illuminate\Support\Facades\Schema;

test('faktur barang jadi create form uses modal spk picker and foto brj', function () {
    $dialog = file_get_contents(resource_path('js/components/spk/faktur-barang-jadi-select-spk-dialog.tsx'));
    $createForm = file_get_contents(resource_path('js/components/spk/faktur-barang-jadi-create-form.tsx'));

    expect($dialog)->not->toBeFalse()
        ->and($dialog)->toContain('FakturBarangJadiSelectSpkDialog')
        ->and($dialog)->toContain('SpkTableItemCell')
        ->and($dialog)->toContain('SpkTableLastProcessCell')
        ->and($dialog)->toContain('Proses Terakhir')
        ->and($dialog)->toContain('Foto BRJ')
        ->and($dialog)->toContain('Lanjut')
        ->and($createForm)->toContain('SpkFotoBrjUploadDialog')
        ->and($createForm)->toContain('SpkTableItemCell')
        ->and($createForm)->toContain('Draft SPK Faktur')
        ->and($createForm)->toContain('spkFioriForm')
        ->and($createForm)->toContain('spkFioriFormCard')
        ->and($createForm)->toContain('FakturBarangJadiSelectSpkDialog')
        ->and($createForm)->toContain('FakturBarangJadiReceiptFormFields');
});

test('faktur barang jadi create page returns lightweight inertia props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faktur-barang-jadi/create')
            ->has('receiptEmployeeOptions')
            ->has('fakturBarangJadiTableAvailable')
            ->has('fakturBarangJadiFotoBrjAvailable')
            ->missing('productions')
        );
});

test('faktur barang jadi search spks returns only completed non reparasi spk', function () {
    $customer = 'Faktur BJ '.strtoupper(fake()->unique()->lexify('??????'));

    Production::factory()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'status' => SpkApprovalService::STATUS_DONE,
        'is_inprocess' => 0,
        'last_process' => null,
        'is_deleted' => 0,
    ]);

    $doneBarangJadi = Production::factory()->create([
        'spk_type' => 'Exchange',
        'customer_name' => $customer,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'location' => 'Store',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-'.$doneBarangJadi->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $doneBarangJadi->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $reparasi = Production::factory()->create([
        'spk_type' => 'Reparasi',
        'customer_name' => $customer,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-R-'.$reparasi->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $reparasi->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('faktur-barang-jadi.select.spks', ['search' => $customer]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.spkNo', $doneBarangJadi->spk_no)
        ->assertJsonPath('data.0.rowId', (int) $doneBarangJadi->row_id)
        ->assertJsonStructure([
            'data' => [[
                'spkImageUrl',
                'typeSkuLabel',
                'itemDescription',
                'skuAssigned',
                'prosesTerakhir',
                'prosesTerakhirDate',
            ]],
        ]);
});

test('faktur barang jadi search spks excludes spk already on open receipt', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    $customer = 'Faktur BJ Ex '.strtoupper(fake()->unique()->lexify('??????'));

    $production = Production::factory()->create([
        'spk_type' => 'Exchange',
        'customer_name' => $customer,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'location' => 'Workshop',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-EX-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/TEST-EX/'.$production->row_id,
        'tanggal' => now()->toDateString(),
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [(int) $production->row_id],
        'items' => [],
        'created_by' => 'system',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('faktur-barang-jadi.select.spks', ['search' => $customer]))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

test('faktur barang jadi index lists created receipts not spk rows', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    $docNo = 'FAKTUR/TEST-INDEX/'.fake()->unique()->numerify('#####');

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => $docNo,
        'tanggal' => now()->toDateString(),
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [1],
        'items' => [
            [
                'spkRowId' => 1,
                'spkNo' => 'SPK-TEST-1',
                'type' => 'Stock',
                'status' => 'DONE (Barang Jadi)',
                'item' => '-',
                'plu' => null,
                'location' => 'Store',
            ],
        ],
        'created_by' => 'system',
        'workflow_status' => FakturBarangJadiWorkflowStatus::CREATED,
    ]);

    $this->get(route('faktur-barang-jadi.index', ['search' => $docNo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faktur-barang-jadi/index')
            ->where('receipts.data.0.docNo', $docNo)
            ->missing('productions')
            ->has('filterOptions.sort')
        );
});

test('faktur barang jadi index filters by workflow status and location', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    if (! FakturBarangJadiWorkflowStatus::columnAvailable()) {
        $this->markTestSkipped('workflow_status column is not available.');
    }

    $included = SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/FILTER-IN/'.fake()->unique()->numerify('###'),
        'tanggal' => '2026-11-20',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [1],
        'items' => [],
        'workflow_status' => FakturBarangJadiWorkflowStatus::SENT,
        'created_by' => 'system',
    ]);

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/FILTER-OUT/'.fake()->unique()->numerify('###'),
        'tanggal' => '2026-11-21',
        'dari' => 'Head Office',
        'untuk' => 'Workshop',
        'jumlah_spk' => 1,
        'spk_row_ids' => [2],
        'items' => [],
        'workflow_status' => FakturBarangJadiWorkflowStatus::CREATED,
        'created_by' => 'system',
    ]);

    $this->get(route('faktur-barang-jadi.index', [
        'search' => $included->doc_no,
        'status' => [FakturBarangJadiWorkflowStatus::SENT],
        'dari' => ['Workshop'],
        'untuk' => ['Store'],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipts.total', 1)
            ->where('receipts.data.0.docNo', $included->doc_no)
        );
});

test('faktur barang jadi index sorts by tanggal ascending', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    $older = SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/SORT-OLD/'.fake()->unique()->numerify('###'),
        'tanggal' => '2026-11-01',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [1],
        'items' => [],
        'created_by' => 'system',
    ]);

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/SORT-NEW/'.fake()->unique()->numerify('###'),
        'tanggal' => '2026-11-30',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [2],
        'items' => [],
        'created_by' => 'system',
    ]);

    $this->get(route('faktur-barang-jadi.index', [
        'sort' => 'tanggal',
        'direction' => 'asc',
        'search' => 'FAKTUR/SORT-',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipts.data.0.docNo', $older->doc_no)
        );
});
