<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiWorkflowStatus;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }
});

test('faktur barang jadi receipt updates spk location only when received', function () {
    $productionAttributes = [
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'plu' => '998877',
        'is_deleted' => 0,
    ];

    if (Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $productionAttributes['foto_brj'] = 'test/foto-brj-'.$productionAttributes['plu'].'.jpg';
    }

    $production = Production::factory()->create($productionAttributes);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-RCP-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $receivedBy = Employee::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-10-15',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ]);

    $response->assertCreated()
        ->assertJsonPath('workflowStatus', FakturBarangJadiWorkflowStatus::CREATED);

    $receipt = SerahTerimaFakturBarangJadi::query()->findOrFail((int) $response->json('id'));
    $docNo = (string) $receipt->doc_no;

    expect($docNo)->toStartWith('FAKTUR/WO-ST/BRJ/OCTOBER99/')
        ->and($response->json('docNo'))->toBe($docNo)
        ->and($response->json('printUrl'))->toBe(route('faktur-barang-jadi.print.receipt', $receipt));

    expect($receipt->jumlah_spk)->toBe(1)
        ->and($receipt->spk_row_ids)->toBe([(int) $production->row_id])
        ->and($receipt->items[0]['plu'])->toBe('998877')
        ->and($receipt->items[0]['status'])->toBe('DONE (Barang Jadi)')
        ->and($receipt->diterima_oleh)->toBeNull();

    if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
        expect(FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status))
            ->toBe(FakturBarangJadiWorkflowStatus::CREATED);
    }

    $production->refresh();

    expect($production->location)->toBe('Store');

    $print = $this->actingAs($user)->get(route('faktur-barang-jadi.print.receipt', $receipt));

    $print->assertOk()
        ->assertSee($docNo)
        ->assertSee('Faktur Barang Jadi')
        ->assertSee('No PRD', false)
        ->assertSee($production->spk_no)
        ->assertSee('998877');

    $this->actingAs($user)
        ->getJson(route('faktur-barang-jadi.select.spks', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->get(route('faktur-barang-jadi.index', ['search' => $docNo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faktur-barang-jadi/index')
            ->where('receipts.data.0.docNo', $docNo)
            ->where('receipts.data.0.workflowStatus', FakturBarangJadiWorkflowStatus::CREATED)
        );

    expect($receipt->items[0]['location'])->toBe('Store');

    $this->actingAs($user)
        ->postJson(route('faktur-barang-jadi.print.receipt.send', $receipt))
        ->assertOk()
        ->assertJsonPath('workflowStatus', FakturBarangJadiWorkflowStatus::SENT);

    $receipt->refresh();

    if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
        expect(FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status))
            ->toBe(FakturBarangJadiWorkflowStatus::SENT);
    }

    $production->refresh();

    expect($production->location)->toBe('Store');

    $this->get(route('faktur-barang-jadi.index', ['search' => $docNo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipts.data.0.workflowStatus', FakturBarangJadiWorkflowStatus::SENT)
        );

    $this->actingAs($user)
        ->postJson(route('faktur-barang-jadi.print.receipt.receive', $receipt), [
            'diterima_oleh' => $receivedBy->nama_lengkap,
        ])
        ->assertOk()
        ->assertJsonPath('workflowStatus', FakturBarangJadiWorkflowStatus::RECEIVED);

    $receipt->refresh();

    expect($receipt->diterima_oleh)->toBe($receivedBy->nama_lengkap);

    if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
        expect(FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status))
            ->toBe(FakturBarangJadiWorkflowStatus::RECEIVED);
    }

    $production->refresh();

    expect($production->location)->toBe('Store');

    $this->get(route('faktur-barang-jadi.index', ['search' => $docNo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipts.data.0.workflowStatus', FakturBarangJadiWorkflowStatus::RECEIVED)
        );
});

test('faktur barang jadi receipt store rejects spk without foto barang jadi', function () {
    if (! Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $this->markTestSkipped('Column spk.foto_brj is not available.');
    }

    $production = Production::factory()->create([
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'foto_brj' => null,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-FOTO-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('faktur-barang-jadi.print.receipt.store'), [
            'ids' => [$production->row_id],
            'tanggal' => '2099-10-16',
            'dari' => 'Workshop',
            'untuk' => 'Store',
            'diserahkan_oleh' => $handedBy->nama_lengkap,
            'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);
});
