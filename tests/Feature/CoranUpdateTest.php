<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranApprovalService;
use App\Support\CoranMaterialGoldSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran edit page is accessible for open documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/COREDIT'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '1.500',
    ]);

    $this->get(route('coran.edit', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/edit')
            ->where('form.id', $coran->row_id)
            ->where('form.docNo', $coran->doc_no)
            ->has('materialOptions')
            ->has('form.materials')
            ->has('form.details')
            ->where('approval.canEdit', true)
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran edit page is accessible for done documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORDNE'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->done()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '1.500',
    ]);

    $this->get(route('coran.edit', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/edit')
            ->where('form.id', $coran->row_id)
            ->where('approval.canEdit', true)
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran show exposes edit and delete for done documents', function () {
    $coran = Coran::factory()->done()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('approval.canOpenEdit', true)
            ->where('approval.canDelete', true)
        );

    $coran->delete();
});

test('coran edit is forbidden for submitted documents', function () {
    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => CoranApprovalService::STATUS_SUBMITTED,
    ]);

    $this->get(route('coran.edit', $coran))->assertForbidden();

    $coran->delete();
});

test('coran destroy soft deletes document and details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORDEL'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->done()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);
    $detail = CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->delete(route('coran.destroy', $coran))
        ->assertRedirect(route('coran.index'));

    $coran->refresh();
    $detail->refresh();

    expect($coran->is_deleted)->toBe(1)
        ->and($detail->is_deleted)->toBe(1);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran show exposes edit approval for open documents', function () {
    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('approval.canOpenEdit', true)
            ->where('approval.canDelete', true)
        );

    $coran->delete();
});

test('coran update replaces details materials and totals', function () {
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

    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/CORUPA'.Str::upper(Str::random(3)),
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/CORUPB'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
        'submit_material_rosegold' => '1.000',
        'result_material_rosegold' => '0.000',
        'shrink' => '0.00',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionA->row_id,
        'weight' => '1.000',
    ]);

    $response = $this->put(route('coran.update', $coran), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $productionB->row_id,
                'weight' => '2.500',
                'status' => 'OK',
            ],
        ],
        'materials' => [
            [
                'section' => 'bahan_whitegold',
                'materialgold_id' => (int) $materialId,
                'weight' => '8.750',
            ],
            [
                'section' => 'sisa_whitegold',
                'materialgold_id' => (int) $materialId,
                'weight' => '3.250',
            ],
        ],
    ]);

    $response->assertRedirect(route('coran.show', $coran));

    $coran->refresh();

    expect((string) $coran->weight)->toBe('2.500')
        ->and((string) $coran->submit_material_whitegold)->toBe('8.750')
        ->and((string) $coran->result_material_whitegold)->toBe('3.250')
        ->and((string) $coran->submit_material_rosegold)->toBe('0.000');

    $activeDetails = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->get();

    expect($activeDetails)->toHaveCount(1)
        ->and((int) $activeDetails->first()->spk_id)->toBe((int) $productionB->row_id);

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

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    DB::connection('third')
        ->table('trmaterialgold')
        ->where('ref_row_id', $coran->row_id)
        ->delete();
    $coran->delete();
    $productionA->delete();
    $productionB->delete();
});
