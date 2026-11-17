<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiWorkflowStatus;
use Illuminate\Support\Facades\Schema;

function deleteTestingSerahTerimaFakturBarangJadi(): void
{
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        return;
    }

    SerahTerimaFakturBarangJadi::query()
        ->withTrashed()
        ->where('doc_no', 'like', 'FAKTUR/%/BJ/%')
        ->forceDelete();
}

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    deleteTestingSerahTerimaFakturBarangJadi();
});

afterEach(fn () => deleteTestingSerahTerimaFakturBarangJadi());

test('faktur barang jadi receipt updates spk location only when received', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'plu' => '998877',
        'is_deleted' => 0,
    ]);

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

    $expectedDocNo = 'FAKTUR/WO-ST/BJ/102099-0001';

    $receipt = SerahTerimaFakturBarangJadi::query()->where('doc_no', $expectedDocNo)->firstOrFail();

    $response->assertCreated()
        ->assertJson([
            'id' => $receipt->id,
            'docNo' => $expectedDocNo,
            'printUrl' => route('faktur-barang-jadi.print.receipt', $receipt),
            'workflowStatus' => FakturBarangJadiWorkflowStatus::CREATED,
        ]);

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

    expect($production->location)->toBe('Workshop');

    $print = $this->actingAs($user)->get(route('faktur-barang-jadi.print.receipt', $receipt));

    $print->assertOk()
        ->assertSee($expectedDocNo)
        ->assertSee('Faktur Barang Jadi')
        ->assertSee($production->spk_no)
        ->assertSee('998877');

    $this->get(route('faktur-barang-jadi.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productions.data.0.fakturBarangJadiIssued', true)
            ->where('productions.data.0.fakturBarangJadiStatus', FakturBarangJadiWorkflowStatus::CREATED)
        );

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

    expect($production->location)->toBe('Workshop');

    $this->get(route('faktur-barang-jadi.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productions.data.0.fakturBarangJadiStatus', FakturBarangJadiWorkflowStatus::SENT)
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

    $this->get(route('faktur-barang-jadi.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productions.data.0.fakturBarangJadiStatus', FakturBarangJadiWorkflowStatus::RECEIVED)
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
});
