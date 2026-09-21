<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranMaterialGoldSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran create page includes material options', function () {
    $this->get(route('coran.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/create')
            ->has('materialOptions')
            ->has('form.materials')
        );
});

test('coran store persists material gold transactions and totals', function () {
    if (
        ! Schema::connection('third')->hasTable('trmaterialgold')
        || ! Schema::connection('third')->hasTable('msmaterialgold')
    ) {
        $this->markTestSkipped('Material gold tables are not available.');
    }

    $materialId = DB::connection('third')
        ->table('msmaterialgold')
        ->when(
            Schema::connection('third')->hasColumn('msmaterialgold', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->orderBy('row_id')
        ->value('row_id');

    if ($materialId === null) {
        $this->markTestSkipped('No material gold master rows available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORMAT'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '1.25',
                'status' => 'OK',
            ],
        ],
        'materials' => [
            [
                'section' => 'bahan_rosegold',
                'materialgold_id' => (int) $materialId,
                'weight' => '12.34',
                'notes' => 'Bahan awal rose',
            ],
            [
                'section' => 'sisa_whitegold',
                'materialgold_id' => (int) $materialId,
                'weight' => '4.56',
                'notes' => null,
            ],
        ],
    ]);

    $coran = Coran::query()
        ->notDeleted()
        ->whereHas('details', fn ($query) => $query
            ->notDeleted()
            ->where('spk_id', $production->row_id))
        ->orderByDesc('row_id')
        ->first();

    expect($coran)->not->toBeNull();

    $response->assertRedirect(route('coran.show', $coran));

    $coran->refresh();

    expect((string) $coran->submit_material_rosegold)->toBe('12.34')
        ->and((string) $coran->result_material_whitegold)->toBe('4.56');

    $rows = DB::connection('third')
        ->table('trmaterialgold')
        ->where('ref_row_id', $coran->row_id)
        ->whereIn('transtype_id', CoranMaterialGoldSynchronizer::transtypeIds())
        ->when(
            Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->get();

    expect($rows)->toHaveCount(2);

    if (Schema::connection('third')->hasColumn('trmaterialgold', 'notes')) {
        expect($rows->firstWhere('transtype_id', 1)?->notes)->toBe('Bahan awal rose');
    }

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    DB::connection('third')
        ->table('trmaterialgold')
        ->where('ref_row_id', $coran->row_id)
        ->delete();
    $coran->delete();
    $production->delete();
});

test('coran store rejects invalid material section', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORINV'.Str::upper(Str::random(3)),
    ]);

    $this->from(route('coran.create'))
        ->post(route('coran.store'), [
            'trans_date' => now()->format('Y-m-d'),
            'details' => [
                [
                    'spk_id' => $production->row_id,
                    'weight' => '1.00',
                    'status' => null,
                ],
            ],
            'materials' => [
                [
                    'section' => 'invalid_section',
                    'materialgold_id' => 1,
                    'weight' => '1.00',
                ],
            ],
        ])
        ->assertRedirect(route('coran.create'))
        ->assertSessionHasErrors('materials.0.section');

    $production->delete();
});
