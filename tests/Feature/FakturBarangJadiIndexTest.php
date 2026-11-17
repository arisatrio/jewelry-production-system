<?php

use App\Models\Production;
use App\Support\SpkApprovalService;

test('faktur barang jadi index shows only completed spk with done rangka or barang jadi status', function () {
    $customer = 'Faktur BJ '.strtoupper(fake()->unique()->lexify('??????'));

    $draft = Production::factory()->create([
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
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
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

    $reparasiProcessId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-R-'.$reparasi->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $reparasi->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('faktur-barang-jadi.index', ['search' => $customer]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faktur-barang-jadi/index')
            ->where('indexContext', 'fakturBarangJadi')
            ->where('productions.total', 1)
            ->where('productions.data.0.produksiNo', $doneBarangJadi->spk_no)
            ->where('productions.data.0.status', 'DONE (Barang Jadi)')
            ->has('statusCounts.doneBarangJadi')
            ->has('statusCounts.doneRangka')
            ->has('statusCounts.belumDibuatFaktur')
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    DB::connection('third')->table('polishfinishedgood')->where('row_id', $reparasiProcessId)->delete();
    collect([$draft, $doneBarangJadi, $reparasi])->each->delete();
});
