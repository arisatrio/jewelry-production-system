<?php

use App\Models\SerahTerimaSpkDiamond;
use App\Support\SerahTerimaSpkDiamondDocNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

test('spk diamond receipt doc number follows faktur route and monthly sequence', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_spk_diamond')) {
        $this->markTestSkipped('Table serah_terima_spk_diamond is not available.');
    }

    SerahTerimaSpkDiamond::query()
        ->withTrashed()
        ->where('doc_no', 'like', 'FAKTUR/%/PBT/112026-%')
        ->forceDelete();

    $generator = app(SerahTerimaSpkDiamondDocNumberGenerator::class);
    $at = Carbon::createFromFormat('Y-m-d', '2026-11-15');

    expect($generator->generate($at, 'Head Office', 'Workshop'))
        ->toBe('FAKTUR/HO-WO/PBT/112026-0001');

    SerahTerimaSpkDiamond::query()->create([
        'doc_no' => 'FAKTUR/HO-WO/PBT/112026-0001',
        'tanggal' => '2026-11-15',
        'jumlah_dokumen' => 0,
        'spk_diamond_row_ids' => [],
        'items' => [],
        'created_by' => 'unit-test',
    ]);

    expect($generator->generate($at, 'Head Office', 'Workshop'))
        ->toBe('FAKTUR/HO-WO/PBT/112026-0002')
        ->and($generator->generate($at, 'Store', 'Head Office'))
        ->toBe('FAKTUR/ST-HO/PBT/112026-0001');

    SerahTerimaSpkDiamond::query()
        ->withTrashed()
        ->where('doc_no', 'like', 'FAKTUR/%/PBT/112026-%')
        ->forceDelete();
});
