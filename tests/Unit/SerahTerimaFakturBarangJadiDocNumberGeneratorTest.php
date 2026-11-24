<?php

use App\Models\SerahTerimaFakturBarangJadi;
use Tests\TestCase;

uses(TestCase::class);
use App\Support\SerahTerimaFakturBarangJadiDocNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

test('faktur barang jadi doc number follows brj route and monthly sequence', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    SerahTerimaFakturBarangJadi::query()
        ->withTrashed()
        ->where('doc_no', 'like', 'FAKTUR/%/BRJ/NOVEMBER26/%')
        ->forceDelete();

    $generator = app(SerahTerimaFakturBarangJadiDocNumberGenerator::class);
    $at = Carbon::createFromFormat('Y-m-d', '2026-11-15');

    expect($generator->generate($at, 'Workshop', 'Head Office'))
        ->toBe('FAKTUR/WO-HO/BRJ/NOVEMBER26/0001');

    SerahTerimaFakturBarangJadi::query()->create([
        'doc_no' => 'FAKTUR/WO-HO/BRJ/NOVEMBER26/0001',
        'tanggal' => '2026-11-15',
        'jumlah_spk' => 0,
        'spk_row_ids' => [],
        'items' => [],
        'created_by' => 'unit-test',
    ]);

    expect($generator->generate($at, 'Workshop', 'Head Office'))
        ->toBe('FAKTUR/WO-HO/BRJ/NOVEMBER26/0002')
        ->and($generator->generate($at, 'Workshop', 'Store'))
        ->toBe('FAKTUR/WO-ST/BRJ/NOVEMBER26/0001');

    SerahTerimaFakturBarangJadi::query()
        ->withTrashed()
        ->where('doc_no', 'like', 'FAKTUR/%/BRJ/NOVEMBER26/%')
        ->forceDelete();
});
