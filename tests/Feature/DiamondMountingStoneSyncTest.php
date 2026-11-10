<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\User;
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

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('pasang-batu.store'), [
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
    ]);

    $response->assertSessionHasNoErrors();

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
        ->and($mounted)->toHaveCount(2);

    $mountedDossier = $mounted->firstWhere('diamond_code', 'B01');

    expect($mountedDossier)->not->toBeNull()
        ->and((int) $mountedDossier->shape_id)->toBe($shapeId)
        ->and((string) $mountedDossier->size)->toBe('1.00');

    $stoneName = DB::connection('third')
        ->table('msstone')
        ->where('row_id', $stoneId)
        ->value('name');

    $mountedMicro = $mounted->first(
        fn (object $row): bool => str_starts_with((string) $row->diamond_code, (string) $stoneName),
    );

    expect($mountedMicro)->not->toBeNull()
        ->and((int) $mountedMicro->pcs)->toBe(4)
        ->and((float) $mountedMicro->crt)->toBe(0.02)
        ->and((string) $mountedMicro->diamond_code)->toContain('Setting test');

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/show')
            ->has('diamondMountingItem.stones.setting', 1)
            ->has('diamondMountingItem.stones.mounted', 2)
            ->has('diamondMountingItem.batuDetail')
            ->has('diamondMountingItem.batuDetail.mounted', 2)
            ->where('diamondMountingItem.stones.setting.0.notes', 'Setting test')
            ->where('diamondMountingItem.stones.setting.0.crt', '0.020')
            ->where(
                'diamondMountingItem.stones.mounted',
                fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => ($row['kode'] ?? '') === 'B01',
                ),
            )
            ->where(
                'diamondMountingItem.batuDetail.mounted',
                fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => ($row['kode'] ?? '') === 'B01',
                ),
            )
        );

    $this->get(route('pasang-batu.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/edit')
            ->has('form.settingStones', 1)
            ->where('form.settingStones.0.notes', 'Setting test')
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

test('pasang batu merges duplicate micro setting lines into one trstone row', function () {
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

    expect($stoneId)->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '2.70',
        'setting_stones' => [
            [
                'stone_id' => $stoneId,
                'pcs' => '2',
                'crt' => '0.010',
                'notes' => 'Pos A',
            ],
            [
                'stone_id' => $stoneId,
                'pcs' => '3',
                'crt' => '0.015',
                'notes' => 'Pos B',
            ],
        ],
        'return_stones' => [],
        'diamonds' => [],
        'mounted_stones' => [],
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $setting = DB::connection('third')
        ->table('trstone')
        ->where('ref_row_id', $document->row_id)
        ->where('transtype_id', DiamondMountingStoneSynchronizer::TRANSTYPE_SETTING)
        ->where('is_deleted', 0)
        ->get();

    expect($setting)->toHaveCount(1)
        ->and((int) $setting[0]->stone_id)->toBe($stoneId)
        ->and((int) $setting[0]->pcs)->toBe(5)
        ->and((float) $setting[0]->crt)->toBe(0.025)
        ->and($setting[0]->notes)->toBe('Pos A, Pos B');

    $mounted = DB::connection('third')
        ->table('diamondmountingdetail')
        ->where('row_id', $document->row_id)
        ->where('is_deleted', 0)
        ->get();

    expect($mounted)->toHaveCount(1)
        ->and((int) $mounted[0]->pcs)->toBe(5);

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
