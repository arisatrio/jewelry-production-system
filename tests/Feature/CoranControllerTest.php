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
        'submit_material_rosegold' => '10.000',
        'submit_material_whitegold' => '5.500',
        'submit_material_yellowgold' => '0.000',
        'result_material_rosegold' => '8.000',
        'result_material_whitegold' => '4.250',
        'result_material_yellowgold' => '0.000',
        'shrink' => '0.350',
        'status' => Coran::STATUS_DONE,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionA->row_id,
        'weight' => '1.500',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $productionB->row_id,
        'weight' => '2.750',
    ]);

    $this->get(route('coran.index', ['search' => 'COR9999911']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/index')
            ->where('corans.data.0.id', $coran->row_id)
            ->where('corans.data.0.docNo', 'COR9999911')
            ->where('corans.data.0.totalSpkWeight', '4.250')
            ->where('corans.data.0.totalSubmitMaterial', '15.500')
            ->where('corans.data.0.totalResultMaterial', '12.250')
            ->where('corans.data.0.shrink', '0.350')
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
        'submit_material_rosegold' => '12.000',
        'submit_material_whitegold' => '0.000',
        'submit_material_yellowgold' => '0.000',
        'result_material_rosegold' => '10.500',
        'result_material_whitegold' => '0.000',
        'result_material_yellowgold' => '0.000',
        'shrink' => '0.200',
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '5.550',
        'status' => CoranSpk::STATUS_OK,
    ]);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.id', $coran->row_id)
            ->where('coranItem.docNo', 'COR9999912')
            ->where('coranItem.statusLabel', 'Completed')
            ->where('coranItem.shrink', '0.200')
            ->where('coranItem.totalSubmitMaterial', '12.000')
            ->where('coranItem.totalResultMaterial', '10.500')
            ->where('coranItem.totalSpkWeight', '5.550')
            ->where('coranItem.spkCount', 1)
            ->where('coranItem.okSpkPercent', '100.00%')
            ->where('coranItem.details.0.spkNo', '2026/PRD/CORSHOW')
            ->where('coranItem.details.0.weight', '5.550')
            ->where('coranItem.details.0.statusLabel', 'OK')
            ->where('workflowStatus.key', 'done')
            ->has('workflowStatus.stages')
            ->has('approvalHistory')
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
        'weight' => '3.100',
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
