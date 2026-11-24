<?php

use App\Models\DiamondMounting;
use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\SpkStone;
use App\Models\User;
use App\Support\SpkItemImageUrl;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('faktur barang jadi print uses detail grid layout with prd and stone columns', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'gold_color' => 'White Gold',
        'gold_weight' => '4.17',
        'last_weight' => '4.66',
        'plu' => 'PLU-PRINT-TEST',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-PRINT-'.$production->row_id,
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

    $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-10-15',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->orderByDesc('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.print.receipt', $receipt))
        ->assertOk()
        ->assertSee('No PRD', false)
        ->assertSee('Item', false)
        ->assertSee('Detail', false)
        ->assertSee('No GIA', false)
        ->assertSee('Total Crt', false)
        ->assertSee('Crt per butir', false)
        ->assertSee('Berat Barang Jadi', false)
        ->assertSee($production->spk_no)
        ->assertSee('PLU-PRINT-TEST')
        ->assertSee('4,66')
        ->assertSee('15-Oct-2099', false);
});

test('faktur barang jadi print uses foto brj for item image column', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    if (! Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $this->markTestSkipped('Column spk.foto_brj is not available.');
    }

    $fotoBrj = 'brj/2026-PRD-PRINT-IMG.jpg';

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'file_name' => 'design-only-should-not-print.jpg',
        'foto_brj' => $fotoBrj,
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insert([
        'doc_no' => 'TEST-FBJ-IMG-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $user = User::factory()->create();
    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();

    $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-10-20',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->orderByDesc('id')->firstOrFail();
    $imageUrl = SpkItemImageUrl::fromFotoBrj($fotoBrj);

    expect($imageUrl)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.print.receipt', $receipt))
        ->assertOk()
        ->assertSee($imageUrl, false)
        ->assertDontSee('design-only-should-not-print.jpg', false);
});

test('faktur barang jadi print shows request order number for pesanan production type', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    $orderNo = 'DP-FBJ-PRINT-'.fake()->unique()->numerify('####');

    $production = Production::factory()->create([
        'spk_type' => 'Pesanan',
        'request_order_no' => $orderNo,
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-11-01',
        'dari' => 'Workshop',
        'untuk' => 'Store',
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->orderByDesc('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.print.receipt', $receipt))
        ->assertOk()
        ->assertSee('Pesanan', false)
        ->assertSee($orderNo, false)
        ->assertDontSee("Pesanan ({$orderNo})", false);
});

test('faktur barang jadi print uses mounted diamond code for no gia column', function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }

    if (! Schema::connection('third')->hasTable('diamondmountingdetail')) {
        $this->markTestSkipped('Table diamondmountingdetail is not available.');
    }

    $diamondDocNo = 'D0-FBJ-'.Str::upper(Str::random(4));
    $giaCode = (string) fake()->unique()->numerify('##########');

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $stoneId = (int) DB::connection('third')
        ->table('msstone')
        ->when(
            Schema::connection('third')->hasColumn('msstone', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('row_id')
        ->value('row_id');

    $shapeId = (int) DB::connection('third')
        ->table('msshape')
        ->when(
            Schema::connection('third')->hasColumn('msshape', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->whereNotNull('code')
        ->where('code', '!=', '')
        ->orderBy('row_id')
        ->value('row_id');

    expect($stoneId)->toBeGreaterThan(0)
        ->and($shapeId)->toBeGreaterThan(0);

    SpkStone::factory()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shapeId,
        'pcs' => 2,
        'carat' => 0.04,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insert([
        'doc_no' => 'TEST-FBJ-MNT-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '2.70',
        'weight_diamond' => '0.05',
        'weight_finish_goods' => '2.60',
        'setting_stones' => [
            [
                'stone_id' => $stoneId,
                'pcs' => '2',
                'crt' => '0.400',
                'notes' => 'Mount faktur test',
            ],
        ],
        'return_stones' => [],
        'diamonds' => [],
        'mounted_stones' => [
            [
                'diamond_code' => $giaCode,
                'shape_id' => $shapeId,
                'pcs' => '2',
                'crt' => '0.400',
                'size' => '1.00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->firstOrFail();

    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => $diamondDocNo,
        'diamond_type' => 'Dossier',
        'entry_date' => '2099-12-01',
        'supplier' => 'TEST',
        'crt' => '0.400',
        'shape_id' => $shapeId,
        'diamondmounting_id' => $document->row_id,
        'is_used' => 1,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
    ]);

    $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-12-01',
        'dari' => 'Workshop',
        'untuk' => 'Store',
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->orderByDesc('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.print.receipt', $receipt))
        ->assertOk()
        ->assertSee($giaCode, false)
        ->assertDontSee($diamondDocNo, false)
        ->assertSee('0,400', false);
});
