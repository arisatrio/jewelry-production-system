<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Str;

test('finishing edit page is accessible for open documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINEDIT'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
        'process_name' => 'Finishing',
        'start_weight' => '1.25',
    ]);

    $this->get(route('finishing.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/edit')
            ->where('form.id', $document->row_id)
            ->where('form.docNo', $document->doc_no)
            ->where('form.processName', 'Finishing')
            ->where('form.startWeight', '1.25')
            ->where('form.spk.spkId', $production->row_id)
            ->where('form.spk.spkNo', $production->spk_no)
            ->missing('form.details')
            ->has('materialOptions')
            ->has('craftsmanOptions')
        );

    $document->delete();
    $production->delete();
});

test('finishing edit page is accessible for done documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINEDDN'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
    ]);

    $this->get(route('finishing.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/edit')
            ->where('form.id', $document->row_id)
        );

    $this->get(route('finishing.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approval.canOpenEdit', true)
        );

    $document->delete();
    $production->delete();
});

test('finishing edit page maps qc status and notes', function (?int $koreksiQc, string $expected) {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINEDQC'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
        'koreksi_qc' => $koreksiQc,
        'keterangan_qc' => 'kontruksi',
    ]);

    try {
        $this->get(route('finishing.edit', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('form.koreksiQc', $expected)
                ->where('form.keteranganQc', 'kontruksi')
            );
    } finally {
        $document->delete();
        $production->delete();
    }
})->with([
    'ok' => [0, '0'],
    'not ok' => [1, '1'],
    'legacy koreksi' => [2, '1'],
    'kosong' => [null, ''],
]);

test('finishing update saves qc status and keeps it when omitted', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINUPQC'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
        'process_name' => 'Finishing',
        'koreksi_qc' => 0,
        'keterangan_qc' => null,
    ]);

    try {
        $this->put(route('finishing.update', $document), [
            'spk_id' => $production->row_id,
            'process_name' => 'Finishing',
            'koreksi_qc' => '1',
            'keterangan_qc' => 'bolong',
        ])->assertSessionHasNoErrors();

        $document->refresh();

        expect($document->koreksi_qc)->toBe(1)
            ->and($document->keterangan_qc)->toBe('bolong');

        $this->put(route('finishing.update', $document), [
            'spk_id' => $production->row_id,
            'process_name' => 'Finishing',
        ])->assertSessionHasNoErrors();

        $document->refresh();

        expect($document->koreksi_qc)->toBe(1)
            ->and($document->keterangan_qc)->toBe('bolong');

        $this->put(route('finishing.update', $document), [
            'spk_id' => $production->row_id,
            'process_name' => 'Finishing',
            'koreksi_qc' => null,
            'keterangan_qc' => null,
        ])->assertSessionHasNoErrors();

        $document->refresh();

        expect($document->koreksi_qc)->toBeNull()
            ->and($document->keterangan_qc)->toBeNull();
    } finally {
        $document->delete();
        $production->delete();
    }
});

test('finishing update keeps done status', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINUPDN'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->done()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'start_weight' => '2.00',
        'finish_weight' => '1.80',
    ]);
    $status = $document->status;

    $this->put(route('finishing.update', $document), [
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'start_weight' => '2.00',
        'finish_weight' => '1.90',
    ])->assertRedirect(route('finishing.show', $document));

    $document->refresh();

    expect((string) $document->finish_weight)->toBe('1.90')
        ->and($document->status)->toBe($status)
        ->and((string) $document->shrink)->toBe('0.10');

    $document->delete();
    $production->delete();
});

test('finishing update changes document fields', function () {
    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/FINUPA'.Str::upper(Str::random(3)),
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/FINUPB'.Str::upper(Str::random(3)),
    ]);

    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $productionA->row_id,
        'status' => null,
        'process_name' => 'Finishing',
        'start_weight' => '1.00',
        'finish_weight' => '0.80',
        'notes' => 'Sebelum update',
    ]);

    $response = $this->put(route('finishing.update', $document), [
        'spk_id' => $productionB->row_id,
        'process_name' => 'Handmade',
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '2.50',
        'finish_weight' => '2.00',
        'notes' => 'Sesudah update',
        'item_category' => 'Barang Kecil',
        'materials' => [],
    ]);

    $document->refresh();

    $response->assertRedirect(route('finishing.show', $document));

    expect($document->spk_id)->toBe($productionB->row_id)
        ->and($document->process_name)->toBe('Handmade')
        ->and((string) $document->start_weight)->toBe('2.50')
        ->and((string) $document->finish_weight)->toBe('2.00')
        ->and((string) $document->shrink)->toBe('0.50')
        ->and((string) $document->shrink_tolerance)->toBe('20.00')
        ->and($document->notes)->toBe('Sesudah update')
        ->and($document->item_category)->toBe('Barang Kecil');

    $productionB->refresh();

    expect((float) $productionB->last_weight)->toBe(2.0);

    $document->delete();
    $productionA->delete();
    $productionB->delete();
});
