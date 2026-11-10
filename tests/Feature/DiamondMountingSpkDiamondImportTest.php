<?php

use App\Models\DiamondMounting;
use App\Models\MsStone;
use App\Models\Production;
use App\Models\SpkDiamond;
use App\Models\User;
use App\Support\SpkDiamondPasangBatuImport;
use App\Support\SpkDiamondStatus;
use App\Support\SpkStoneAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('pasang batu can search and import eligible spk diamond payload', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    if (! Schema::connection('third')->hasTable('msstone')) {
        $this->markTestSkipped('Table msstone is not available.');
    }

    DB::connection('third')->beginTransaction();

    $microStone = MsStone::factory()->create([
        'name' => 'Mikro Pasang Batu Import',
    ]);

    $production = Production::factory()->create([
        'is_deleted' => 0,
        'last_weight' => 5.12,
    ]);

    $spkDiamond = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/IMP/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-05',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 1,
        'status' => SpkDiamondStatus::RECEIVED_BY_WORKSHOP,
        'diamondmounting_id' => null,
        'items' => [[
            'allocationRowId' => 888001,
            'source' => SpkStoneAllocationService::SOURCE_MICRO,
            'label' => (string) $microStone->row_id,
            'code' => (string) $microStone->row_id,
            'msStoneId' => (int) $microStone->row_id,
            'shape' => 'Round (R)',
            'reservedPcs' => '2',
            'reservedCrt' => '0.120',
            'positionName' => 'Tengah',
        ]],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('pasang-batu.select.spk-diamonds', [
            'search' => $spkDiamond->doc_no,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.rowId', (int) $spkDiamond->row_id);

    $this->actingAs($user)
        ->getJson(route('pasang-batu.select.spk-diamonds.import', $spkDiamond))
        ->assertOk()
        ->assertJsonPath('data.spkDiamondId', (int) $spkDiamond->row_id)
        ->assertJsonPath('data.spk.rowId', (int) $production->row_id)
        ->assertJsonPath('data.settingStones.0.stoneId', (int) $microStone->row_id)
        ->assertJsonPath('data.settingStones.0.pcs', '2')
        ->assertJsonPath('data.weightDiamond', '0.120');

    DB::connection('third')->rollBack();
    $user->delete();
});

test('pasang batu store links spk diamond to mounting document', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    if (! Schema::connection('third')->hasColumn('spk_diamond', 'diamondmounting_id')) {
        $this->markTestSkipped('Column spk_diamond.diamondmounting_id is not available.');
    }

    DB::connection('third')->beginTransaction();

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SPD'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $spkDiamond = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/LINK/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-05',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 0,
        'status' => SpkDiamondStatus::RECEIVED_BY_WORKSHOP,
        'diamondmounting_id' => null,
        'items' => [],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'spk_diamond_id' => $spkDiamond->row_id,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'weight_frame' => '3.00',
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('pasang-batu.show', $document));

    $spkDiamond->refresh();

    expect((int) $spkDiamond->diamondmounting_id)->toBe((int) $document->row_id);

    DB::connection('third')->rollBack();
    $user->delete();
});

test('pasang batu edit exposes linked spk diamond summary', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    if (! Schema::connection('third')->hasColumn('spk_diamond', 'diamondmounting_id')) {
        $this->markTestSkipped('Column spk_diamond.diamondmounting_id is not available.');
    }

    DB::connection('third')->beginTransaction();

    $production = Production::factory()->create(['is_deleted' => 0]);

    $mounting = DiamondMounting::query()->create([
        'doc_no' => 'DMD'.random_int(1000000, 9999999),
        'trans_date' => now()->toDateString(),
        'process_name' => 'Pasang Batu',
        'spk_id' => $production->row_id,
        'is_from_new_system' => 1,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $spkDiamond = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/EDIT/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-05',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 0,
        'status' => SpkDiamondStatus::INSTALLED,
        'diamondmounting_id' => $mounting->row_id,
        'items' => [],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $summary = app(SpkDiamondPasangBatuImport::class)->linkedSummaryForMounting($mounting);

    expect($summary)->toBe([
        'id' => (int) $spkDiamond->row_id,
        'docNo' => (string) $spkDiamond->doc_no,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pasang-batu.edit', $mounting))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('form.spkDiamond.id', (int) $spkDiamond->row_id)
            ->where('form.spkDiamond.docNo', $spkDiamond->doc_no));

    DB::connection('third')->rollBack();
    $user->delete();
});
