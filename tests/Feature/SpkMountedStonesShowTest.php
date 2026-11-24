<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\SpkStone;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('spk show includes mounted stones from latest pasang batu document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SMTSTN'.Str::upper(Str::random(3)),
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

    SpkStone::factory()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shapeId,
        'pcs' => 4,
    ]);

    $this->actingAs(User::factory()->create());

    $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '2.70',
        'weight_diamond' => '0.05',
        'weight_finish_goods' => '2.60',
        'setting_stones' => [
            [
                'stone_id' => $stoneId,
                'pcs' => '4',
                'crt' => '0.020',
                'notes' => 'Setting test',
            ],
        ],
        'return_stones' => [],
        'diamonds' => [],
        'mounted_stones' => [
            [
                'diamond_code' => 'B01',
                'shape_id' => $shapeId,
                'pcs' => '4',
                'crt' => '0.020',
                'size' => '1.00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where(
                'production.stoneIndexStatus.allocation.status',
                'installed',
            )
            ->where('production.stoneIndexStatus.allocation.label', 'Terpasang')
            ->has('mountedStonesDetail')
            ->has('mountedStonesDetail.pasangBatu')
            ->where('mountedStonesDetail.pasangBatu.id', (int) $document->row_id)
            ->where('mountedStonesDetail.pasangBatu.docNo', (string) $document->doc_no)
            ->has('mountedStonesDetail.mounted', 1)
            ->where(
                'mountedStonesDetail.mounted',
                fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => ($row['kode'] ?? '') === 'B01',
                ),
            )
        );

    DB::connection('third')
        ->table('trstone')
        ->where('ref_row_id', $document->row_id)
        ->delete();
    DB::connection('third')
        ->table('diamondmountingdetail')
        ->where('row_id', $document->row_id)
        ->delete();
    $document->delete();
    $production->delete();
});

test('spk show mounted stones detail is empty when no pasang batu document', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SMTEMP'.Str::upper(Str::random(3)),
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('mountedStonesDetail.pasangBatu', null)
            ->has('mountedStonesDetail.mounted', 0)
            ->where('mountedStonesDetail.mountedTotals.pcs', 0)
        );

    $production->delete();
});
