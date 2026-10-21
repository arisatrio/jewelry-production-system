<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\SpkDiamond;
use App\Models\SpkDiamondStone;
use App\Support\DiamondMountingStoneSynchronizer;
use App\Support\SpkDiamondLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('trstone')) {
        $this->markTestSkipped('trstone table is not available.');
    }

    DB::connection('third')->beginTransaction();
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

test('pasang batu from spk diamond applies terpasang to mounted detail and trstone', function () {
    $this->mock(SpkDiamondLedger::class, function (MockInterface $mock): void {
        $mock->shouldReceive('applySubmit')->andReturnNull();
    });

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PBSD'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
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
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('row_id')
        ->value('row_id');

    expect($stoneId)->toBeGreaterThan(0)
        ->and($shapeId)->toBeGreaterThan(0);

    $spkDiamond = SpkDiamond::query()->create([
        'no_spk_diamond' => 'MK-DM.PSB'.Str::upper(Str::random(3)),
        'spk_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'tanggal' => now()->toDateString(),
        'status' => SpkDiamond::STATUS_SUBMITTED,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $stoneLine = SpkDiamondStone::query()->create([
        'spk_diamond_id' => $spkDiamond->id,
        'stone_id' => $stoneId,
        'pcs' => '4',
        'crt' => '0.200',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $trstoneId = (int) DB::connection('third')->table('trstone')->insertGetId([
        'period_id' => 1,
        'transtype_id' => SpkDiamondLedger::TRANSTYPE_OUT,
        'ref_row_id' => $spkDiamond->id,
        'stone_id' => $stoneId,
        'spk_id' => $production->row_id,
        'pcs' => 4,
        'crt' => '0.200',
        'txt' => null,
        'notes' => 'SPK Diamond',
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
        'deleted_date' => null,
        'deleted_by' => null,
    ]);

    $stoneLine->update([
        'trstone_row_id' => $trstoneId,
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $parcel = (string) DB::connection('third')->table('msstone')->where('row_id', $stoneId)->value('parcel');

    $response = $this->post(route('pasang-batu.store'), [
        'spk_diamond_id' => $spkDiamond->id,
        'weight_frame' => '2.70',
        'weight_diamond' => '0.20',
        'setting_stones' => [],
        'return_stones' => [],
        'diamonds' => [],
        'mounted_stones' => [
            [
                'diamond_code' => $parcel !== '' ? $parcel : 'B01',
                'shape_id' => $shapeId,
                'pcs' => '4',
                'crt' => '0.200',
                'size' => '1.00',
            ],
        ],
        'setting_awal_lines' => [
            [
                'source_type' => 'stone',
                'source_line_id' => $stoneLine->id,
                'stone_id' => $stoneId,
                'pcs_terpasang' => '4',
                'crt_terpasang' => '0.200',
                'pcs_retur' => null,
                'crt_retur' => null,
            ],
        ],
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_diamond_id', $spkDiamond->id)
        ->first();

    expect($document)->not->toBeNull();
    $response->assertRedirect(route('pasang-batu.show', $document));

    $setting = DB::connection('third')
        ->table('trstone')
        ->where('row_id', $trstoneId)
        ->where('is_deleted', 0)
        ->first();

    $mounted = DB::connection('third')
        ->table('diamondmountingdetail')
        ->where('row_id', $document->row_id)
        ->where('is_deleted', 0)
        ->get();

    expect($setting)->not->toBeNull()
        ->and((int) $setting->transtype_id)->toBe(DiamondMountingStoneSynchronizer::TRANSTYPE_SETTING)
        ->and((int) $setting->ref_row_id)->toBe((int) $document->row_id)
        ->and((int) $setting->pcs)->toBe(4)
        ->and($mounted)->toHaveCount(1)
        ->and((int) $mounted[0]->pcs)->toBe(4);
});
