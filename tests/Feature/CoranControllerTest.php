<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Str;

test('coran index page is accessible', function () {
    $this->get(route('coran.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/index')
            ->has('corans.data')
            ->has('corans.total')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('coran index lists spk weights and material totals', function () {
    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/CORTOTA',
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/CORTOTB',
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR9999911',
        'submit_material_rosegold' => '10.00',
        'submit_material_whitegold' => '5.50',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '8.00',
        'result_material_whitegold' => '4.25',
        'result_material_yellowgold' => '0.00',
        'shrink' => '0.35',
        'status' => Coran::STATUS_DONE,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionA->row_id,
        'weight' => '1.50',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionB->row_id,
        'weight' => '2.75',
    ]);

    $this->get(route('coran.index', ['search' => 'COR9999911']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/index')
            ->where('corans.data.0.id', $coran->row_id)
            ->where('corans.data.0.docNo', 'COR9999911')
            ->where('corans.data.0.totalSpkWeight', '4.25')
            ->where('corans.data.0.totalSubmitMaterial', '15.50')
            ->where('corans.data.0.totalResultMaterial', '12.25')
            ->where('corans.data.0.shrink', '0.35')
            ->where('corans.data.0.statusLabel', 'Completed')
            ->where('corans.data.0.spkNos', ['2026/PRD/CORTOTA', '2026/PRD/CORTOTB'])
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $productionA->delete();
    $productionB->delete();
});

test('coran show page is accessible', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORSHOW',
        'description' => 'Deskripsi item coran',
    ]);

    $coran = Coran::factory()->done()->create([
        'doc_no' => 'COR9999912',
        'submit_material_rosegold' => '12.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '10.50',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'shrink' => '0.20',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '5.55',
        'status' => CoranSpk::STATUS_OK,
    ]);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.id', $coran->row_id)
            ->where('coranItem.docNo', 'COR9999912')
            ->where('coranItem.statusLabel', 'Completed')
            ->where('coranItem.shrink', '0.20')
            ->where('coranItem.totalSubmitMaterial', '12.00')
            ->where('coranItem.totalResultMaterial', '10.50')
            ->where('coranItem.totalSpkWeight', '5.55')
            ->where('coranItem.spkCount', 1)
            ->where('coranItem.okSpkPercent', '100.00%')
            ->where('coranItem.details.0.spkNo', '2026/PRD/CORSHOW')
            ->where('coranItem.details.0.weight', '5.55')
            ->where('coranItem.details.0.statusLabel', 'OK')
            ->where('workflowStatus.key', 'done')
            ->has('workflowStatus.stages')
            ->has('approvalHistory')
            ->has('approvalFooter')
            ->has('approval.canOpenEdit')
            ->has('approval.canSubmit')
            ->has('coranItem.coranBreakdown')
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran show returns not found for deleted documents', function () {
    $coran = Coran::factory()->deleted()->create([
        'doc_no' => 'COR9999913',
    ]);

    $this->get(route('coran.show', $coran))->assertNotFound();

    $coran->delete();
});

test('coran index can search by spk number', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/COR'.Str::upper(Str::random(4)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '3.10',
    ]);

    $this->get(route('coran.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/index')
            ->where('corans.data.0.id', $coran->row_id)
            ->where('corans.data.0.spkNos.0', $production->spk_no)
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran spk selector endpoint supports queue filter', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CQF'.Str::upper(Str::random(4)),
    ]);
    $coran = Coran::factory()->create([
        'status' => 'DRAFT',
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    $this->getJson(route('coran.select.spks', ['queue' => 'inProgress']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'coranId' => $coran->row_id,
            'docNo' => $coran->doc_no,
        ]);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
