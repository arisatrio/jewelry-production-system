<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Support\DiamondMountingStoneSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('pasang batu store syncs setting and mounted stone details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTSTN'.Str::upper(Str::random(3)),
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

    $response = $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '2.70',
        'weight_diamond' => '0.050',
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
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();
    $response->assertRedirect(route('pasang-batu.show', $document));

    $setting = DB::connection('third')
        ->table('trstone')
        ->where('ref_row_id', $document->row_id)
        ->where('transtype_id', DiamondMountingStoneSynchronizer::TRANSTYPE_SETTING)
        ->where('is_deleted', 0)
        ->get();

    $mounted = DB::connection('third')
        ->table('diamondmountingdetail')
        ->where('row_id', $document->row_id)
        ->where('is_deleted', 0)
        ->get();

    expect($setting)->toHaveCount(1)
        ->and((int) $setting[0]->stone_id)->toBe($stoneId)
        ->and((int) $setting[0]->pcs)->toBe(4)
        ->and((float) $setting[0]->crt)->toBe(0.02)
        ->and($setting[0]->notes)->toBe('Setting test')
        ->and((int) $setting[0]->spk_id)->toBe((int) $production->row_id)
        ->and($mounted)->toHaveCount(1)
        ->and($mounted[0]->diamond_code)->toBe('B01')
        ->and((int) $mounted[0]->shape_id)->toBe($shapeId)
        ->and((string) $mounted[0]->size)->toBe('1.00');

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/show')
            ->has('diamondMountingItem.stones.setting', 1)
            ->has('diamondMountingItem.stones.mounted', 1)
            ->where('diamondMountingItem.stones.mounted.0.kode', 'B01')
        );

    // cleanup
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

test('pasang batu create page exposes stone and shape options', function () {
    $response = $this->get(route('pasang-batu.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/create')
            ->has('stoneOptions')
            ->has('shapeOptions')
            ->has('form.settingStones')
            ->has('form.mountedStones')
        );

    $props = $response->inertiaProps();
    $firstStone = $props['stoneOptions'][0] ?? null;

    expect($firstStone)->toBeArray()
        ->and($firstStone)->toHaveKeys(['value', 'label', 'stock']);
});
