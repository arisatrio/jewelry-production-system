<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Str;

test('finishing index page is accessible', function () {
    $this->get(route('finishing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->has('documents.data')
            ->has('documents.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('finishing index lists document weights and status labels', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINTOTA',
    ]);

    $document = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FIN9999911',
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'start_weight' => '3.160',
        'finish_weight' => '2.450',
        'submit_materialgold' => '0.030',
        'result_materialgold' => '0.520',
        'shrink' => '0.220',
        'notes' => 'Catatan finishing list',
        'send_craftsman_date' => '2026-08-25 10:16:58',
    ]);

    $this->get(route('finishing.index', ['search' => 'FIN9999911']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.docNo', 'FIN9999911')
            ->where('documents.data.0.spkNo', '2026/PRD/FINTOTA')
            ->where('documents.data.0.startWeight', '3.160')
            ->where('documents.data.0.finishWeight', '2.450')
            ->where('documents.data.0.submitMaterial', '0.030')
            ->where('documents.data.0.resultMaterial', '0.520')
            ->where('documents.data.0.shrink', '0.220')
            ->where('documents.data.0.statusLabel', 'Completed')
            ->where('documents.data.0.notes', 'Catatan finishing list')
            ->where('documents.data.0.transDate', '2026-08-25')
        );

    $document->delete();
    $production->delete();
});

test('finishing index can search by spk number', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FIN'.Str::upper(Str::random(4)),
    ]);

    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
    ]);

    $this->get(route('finishing.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.spkNo', $production->spk_no)
        );

    $document->delete();
    $production->delete();
});

test('finishing show page is accessible', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINSHOW',
        'description' => 'Deskripsi item finishing',
    ]);

    $document = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FIN9999912',
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'start_weight' => '4.000',
        'finish_weight' => '3.500',
        'submit_materialgold' => '1.000',
        'result_materialgold' => '0.400',
        'shrink' => '0.100',
        'shrink_tolerance' => '5.00',
        'notes' => 'Catatan detail finishing',
        'item_category' => 'Barang Kecil - lvl 1',
    ]);

    $this->get(route('finishing.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/show')
            ->where('finishingItem.id', $document->row_id)
            ->where('finishingItem.docNo', 'FIN9999912')
            ->where('finishingItem.statusLabel', 'Completed')
            ->where('finishingItem.startWeight', '4.000')
            ->where('finishingItem.finishWeight', '3.500')
            ->where('finishingItem.submitMaterial', '1.000')
            ->where('finishingItem.resultMaterial', '0.400')
            ->where('finishingItem.shrink', '0.100')
            ->where('finishingItem.shrinkPercent', '2.50%')
            ->where('finishingItem.notes', 'Catatan detail finishing')
            ->where('finishingItem.spk.spkNo', '2026/PRD/FINSHOW')
            ->where('workflowStatus.key', 'done')
            ->has('workflowStatus.stages')
            ->where('canEdit', false)
            ->has('finishingItem.materials.bahan')
            ->has('finishingItem.materials.sisa')
        );

    $document->delete();
    $production->delete();
});

test('finishing show returns not found for deleted documents', function () {
    $document = FinishingHandmade::factory()->deleted()->create([
        'doc_no' => 'FIN9999913',
    ]);

    $this->get(route('finishing.show', $document))->assertNotFound();

    $document->delete();
});
