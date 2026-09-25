<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('finishing index page is accessible', function () {
    $this->get(route('finishing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->has('documents.data')
            ->has('documents.total')
            ->where('documents.per_page', 50)
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
            ->where('filters.search', '')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('filters.process', [])
            ->where('filters.status', [])
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->where('filters.craftsman', null)
            ->where('filters.per_page', 50)
            ->has('filterOptions.process')
            ->has('filterOptions.status')
            ->has('filterOptions.craftsman')
            ->has('filterOptions.per_page')
            ->has('filterOptions.sort')
            ->has('filterOptions.direction')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
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
        'craftsman_id' => 1,
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'submit_materialgold' => '0.03',
        'result_materialgold' => '0.52',
        'shrink' => '0.22',
        'shrink_tolerance' => '6.90',
        'notes' => 'Catatan finishing list',
        'send_craftsman_date' => '2026-08-25 10:16:58',
        'received_craftsman_date' => '2026-08-26 14:30:00',
    ]);

    $craftsmanName = DB::connection('third')
        ->table('mscraftsman')
        ->where('row_id', 1)
        ->value('name');

    $this->get(route('finishing.index', ['search' => 'FIN9999911']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.docNo', 'FIN9999911')
            ->where('documents.data.0.spkNo', '2026/PRD/FINTOTA')
            ->where('documents.data.0.craftsmanName', (string) $craftsmanName)
            ->where('documents.data.0.sendCraftsmanDate', '2026-08-25 10:16')
            ->where('documents.data.0.receivedCraftsmanDate', '2026-08-26 14:30')
            ->where('documents.data.0.startWeight', '3.16')
            ->where('documents.data.0.finishWeight', '2.45')
            ->where('documents.data.0.submitMaterial', '0.03')
            ->where('documents.data.0.resultMaterial', '0.52')
            ->where('documents.data.0.shrink', '0.22')
            ->where('documents.data.0.shrinkTolerance', '6.90')
            ->where('documents.data.0.statusLabel', 'Completed')
            ->where('documents.data.0.notes', 'Catatan finishing list')
            ->where('documents.data.0.transDate', '2026-08-25')
            ->where('filters.search', 'FIN9999911')
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
            ->where('filters.search', $production->spk_no)
        );

    $document->delete();
    $production->delete();
});

test('finishing index can filter by process name', function () {
    $finishingProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/FINPROC'.Str::upper(Str::random(3)),
    ]);
    $reparationProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/REPP'.Str::upper(Str::random(3)),
    ]);

    $finishingDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $finishingProduction->row_id,
        'process_name' => 'Finishing',
    ]);
    $reparationDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'REP'.Str::upper(Str::random(7)),
        'spk_id' => $reparationProduction->row_id,
        'process_name' => 'Reparation',
    ]);

    $this->get(route('finishing.index', ['process' => ['Reparation']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.process', ['Reparation'])
            ->where('documents.data.0.id', $reparationDocument->row_id)
            ->where('documents.data.0.processName', 'Reparation')
        );

    $finishingDocument->delete();
    $reparationDocument->delete();
    $finishingProduction->delete();
    $reparationProduction->delete();
});

test('finishing index can filter by status', function () {
    $unique = 'statustoken'.Str::lower(Str::random(6));

    $openProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/OPEN'.Str::upper(Str::random(3)),
    ]);
    $doneProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/DONE'.Str::upper(Str::random(3)),
    ]);

    $openDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FOP'.Str::upper(Str::random(7)),
        'spk_id' => $openProduction->row_id,
        'status' => FinishingHandmade::STATUS_OPEN,
        'notes' => $unique,
    ]);
    $doneDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FDN'.Str::upper(Str::random(7)),
        'spk_id' => $doneProduction->row_id,
        'status' => FinishingHandmade::STATUS_DONE,
        'notes' => $unique,
    ]);

    $this->get(route('finishing.index', [
        'status' => ['done'],
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.status', ['done'])
            ->where('documents.data.0.id', $doneDocument->row_id)
            ->where('documents.data.0.statusLabel', 'Completed')
        );

    $openDocument->delete();
    $doneDocument->delete();
    $openProduction->delete();
    $doneProduction->delete();
});

test('finishing index can filter by send craftsman date range', function () {
    $unique = 'datetoken'.Str::lower(Str::random(6));

    $insideProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/DIN'.Str::upper(Str::random(3)),
    ]);
    $outsideProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/DOUT'.Str::upper(Str::random(3)),
    ]);

    $insideDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FDI'.Str::upper(Str::random(7)),
        'spk_id' => $insideProduction->row_id,
        'notes' => $unique,
        'send_craftsman_date' => '2026-09-15 10:00:00',
    ]);
    $outsideDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FDO'.Str::upper(Str::random(7)),
        'spk_id' => $outsideProduction->row_id,
        'notes' => $unique,
        'send_craftsman_date' => '2026-08-01 10:00:00',
    ]);

    $this->get(route('finishing.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $insideDocument->row_id)
        );

    $insideDocument->delete();
    $outsideDocument->delete();
    $insideProduction->delete();
    $outsideProduction->delete();
});

test('finishing index can filter by craftsman', function () {
    $unique = 'crafttoken'.Str::lower(Str::random(6));
    $targetCraftsmanId = 1;
    $otherCraftsmanId = DB::connection('third')
        ->table('mscraftsman')
        ->where('row_id', '!=', $targetCraftsmanId)
        ->where('is_deleted', 0)
        ->value('row_id');

    expect($otherCraftsmanId)->not->toBeNull();

    $matchingProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/CIN'.Str::upper(Str::random(3)),
    ]);
    $otherProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/COUT'.Str::upper(Str::random(3)),
    ]);

    $matchingDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FCI'.Str::upper(Str::random(7)),
        'spk_id' => $matchingProduction->row_id,
        'notes' => $unique,
        'craftsman_id' => $targetCraftsmanId,
    ]);
    $otherDocument = FinishingHandmade::factory()->create([
        'doc_no' => 'FCO'.Str::upper(Str::random(7)),
        'spk_id' => $otherProduction->row_id,
        'notes' => $unique,
        'craftsman_id' => (int) $otherCraftsmanId,
    ]);

    $this->get(route('finishing.index', [
        'search' => $unique,
        'craftsman' => $targetCraftsmanId,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.craftsman', $targetCraftsmanId)
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $matchingDocument->row_id)
        );

    $matchingDocument->delete();
    $otherDocument->delete();
    $matchingProduction->delete();
    $otherProduction->delete();
});

test('finishing index can change show entries per page', function () {
    $this->get(route('finishing.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.per_page', 25)
            ->where('documents.per_page', 25)
        );

    $this->get(route('finishing.index', ['per_page' => 999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.per_page', 50)
            ->where('documents.per_page', 50)
        );
});

test('finishing index can sort by id document number', function () {
    $unique = 'sorttoken'.Str::lower(Str::random(6));

    $firstProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/A'.Str::upper(Str::random(4)),
    ]);
    $secondProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/B'.Str::upper(Str::random(4)),
    ]);

    $lowerDoc = FinishingHandmade::factory()->create([
        'doc_no' => 'AAA'.Str::upper(Str::random(6)),
        'spk_id' => $firstProduction->row_id,
        'notes' => $unique,
    ]);
    $higherDoc = FinishingHandmade::factory()->create([
        'doc_no' => 'ZZZ'.Str::upper(Str::random(6)),
        'spk_id' => $secondProduction->row_id,
        'notes' => $unique,
    ]);

    $this->get(route('finishing.index', [
        'sort' => 'id',
        'direction' => 'desc',
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('documents.data.0.id', $higherDoc->row_id)
            ->where('documents.data.1.id', $lowerDoc->row_id)
        );

    $this->get(route('finishing.index', [
        'sort' => 'id',
        'direction' => 'asc',
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'asc')
            ->where('documents.data.0.id', $lowerDoc->row_id)
            ->where('documents.data.1.id', $higherDoc->row_id)
        );

    $lowerDoc->delete();
    $higherDoc->delete();
    $firstProduction->delete();
    $secondProduction->delete();
});

test('finishing index can sort by spk number', function () {
    $unique = 'spksort'.Str::lower(Str::random(6));

    $earlySpkProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/AAA'.Str::upper(Str::random(3)),
    ]);
    $lateSpkProduction = Production::factory()->create([
        'spk_no' => '2026/PRD/ZZZ'.Str::upper(Str::random(3)),
    ]);

    $earlyDoc = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $earlySpkProduction->row_id,
        'notes' => $unique,
    ]);
    $lateDoc = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $lateSpkProduction->row_id,
        'notes' => $unique,
    ]);

    $this->get(route('finishing.index', [
        'sort' => 'spk',
        'direction' => 'asc',
        'search' => $unique,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->where('filters.sort', 'spk')
            ->where('filters.direction', 'asc')
            ->where('documents.data.0.id', $earlyDoc->row_id)
            ->where('documents.data.1.id', $lateDoc->row_id)
        );

    $earlyDoc->delete();
    $lateDoc->delete();
    $earlySpkProduction->delete();
    $lateSpkProduction->delete();
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
        'start_weight' => '4.00',
        'finish_weight' => '3.50',
        'submit_materialgold' => '1.00',
        'result_materialgold' => '0.40',
        'shrink' => '0.10',
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
            ->where('finishingItem.startWeight', '4.00')
            ->where('finishingItem.finishWeight', '3.50')
            ->where('finishingItem.submitMaterial', '1.00')
            ->where('finishingItem.resultMaterial', '0.40')
            ->where('finishingItem.shrink', '0.10')
            ->where('finishingItem.shrinkPercent', '2.50%')
            ->where('finishingItem.notes', 'Catatan detail finishing')
            ->where('finishingItem.spk.spkNo', '2026/PRD/FINSHOW')
            ->where('workflowStatus.key', 'done')
            ->has('workflowStatus.stages')
            ->has('approvalFooter')
            ->has('approvalHistory')
            ->where('approval.canOpenEdit', false)
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
