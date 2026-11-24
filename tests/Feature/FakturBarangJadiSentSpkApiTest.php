<?php

use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiWorkflowStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    if (! FakturBarangJadiWorkflowStatus::columnAvailable()) {
        $this->markTestSkipped('Column workflow_status is not available.');
    }
});

test('api returns spk with faktur barang jadi status sent', function () {
    $spkSuffix = Str::upper(Str::random(6));
    $sentSpkNo = 'TEST-API-FBJ-SENT-'.$spkSuffix;

    $sentProduction = Production::factory()->create([
        'spk_no' => $sentSpkNo,
        'spk_type' => 'Exchange',
        'item_name' => 'Cincin API Sent',
        'location' => 'Workshop',
        'plu' => 'API-SENT-01',
        'is_deleted' => 0,
    ]);

    $otherProduction = Production::factory()->create([
        'spk_no' => 'TEST-API-FBJ-CREATED',
        'spk_type' => 'Exchange',
        'is_deleted' => 0,
    ]);

    $sentReceipt = SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/API/SENT/'.uniqid(),
        'tanggal' => now()->toDateString(),
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => 'Tester',
        'diketahui_oleh' => 'Tester',
        'jumlah_spk' => 1,
        'spk_row_ids' => [(int) $sentProduction->row_id],
        'items' => [
            [
                'spkRowId' => (int) $sentProduction->row_id,
                'spkNo' => $sentProduction->spk_no,
                'type' => 'Exchange',
                'status' => 'DONE (Barang Jadi)',
                'item' => 'Cincin API Sent',
                'plu' => 'API-SENT-01',
                'location' => 'Workshop',
            ],
        ],
        'workflow_status' => FakturBarangJadiWorkflowStatus::SENT,
        'created_by' => 'test',
    ]);

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/API/CREATED/'.uniqid(),
        'tanggal' => now()->toDateString(),
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'jumlah_spk' => 1,
        'spk_row_ids' => [(int) $otherProduction->row_id],
        'items' => [],
        'workflow_status' => FakturBarangJadiWorkflowStatus::CREATED,
        'created_by' => 'test',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.faktur-barang-jadi.spk.sent', ['search' => $sentSpkNo]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.rowId', (int) $sentProduction->row_id)
        ->assertJsonPath('data.0.spkNo', $sentSpkNo)
        ->assertJsonPath('data.0.fakturBarangJadiStatus', FakturBarangJadiWorkflowStatus::SENT)
        ->assertJsonPath('data.0.faktur.docNo', $sentReceipt->doc_no)
        ->assertJsonPath('data.0.faktur.workflowStatus', FakturBarangJadiWorkflowStatus::SENT);
});

test('api faktur sent spk requires authentication', function () {
    auth()->logout();

    $this->getJson(route('api.faktur-barang-jadi.spk.sent'))
        ->assertUnauthorized();
});
