<?php

use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\PolishFrameSpkEligibility;
use Illuminate\Support\Str;

test('poles rangka create page is accessible', function () {
    $this->get(route('poles-rangka.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/create')
            ->has('formDocumentNo')
            ->has('craftsmanOptions')
            ->has('statusItemOptions')
            ->has('form.sendCraftsmanDate')
            ->where('form.spk', null)
            ->missing('form.materials')
        );
});

test('poles rangka store creates document with spk and marks process started', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKSTR'.Str::upper(Str::random(3)),
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('poles-rangka.store'), [
        'spk_id' => $production->row_id,
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'status_item' => 'OK',
        'notes' => 'Catatan store poles rangka',
    ]);

    $document = PolishFrame::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('poles-rangka.show', $document));

    $production->refresh();

    expect($document->doc_no)->toMatch('/^PRK\d{7}$/')
        ->and($document->status)->toBeNull()
        ->and((string) $document->start_weight)->toBe('3.16')
        ->and((string) $document->finish_weight)->toBe('2.45')
        ->and((string) $document->shrink)->toBe('0.71')
        ->and($document->status_item)->toBe('OK')
        ->and($document->notes)->toBe('Catatan store poles rangka')
        ->and($production->last_process)->toBe(PolishFrameSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1)
        ->and((float) $production->last_weight)->toBe(2.45);

    $document->delete();
    $production->delete();
});

test('poles rangka store requires spk', function () {
    $this->from(route('poles-rangka.create'))
        ->post(route('poles-rangka.store'), [
            'spk_id' => null,
        ])
        ->assertRedirect(route('poles-rangka.create'))
        ->assertSessionHasErrors('spk_id');
});

test('poles rangka search spks returns json', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKSEL'.Str::upper(Str::random(3)),
        'last_weight' => 3.25,
    ]);

    $this->getJson(route('poles-rangka.select.spks', [
        'search' => $production->spk_no,
        'limit' => 10,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'lastWeight' => '3.25',
        ]);

    $production->delete();
});

test('poles rangka update changes document fields and recalculates shrink', function () {
    $productionA = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKUPA'.Str::upper(Str::random(3)),
    ]);
    $productionB = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKUPB'.Str::upper(Str::random(3)),
    ]);

    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => $productionA->row_id,
        'status' => null,
        'start_weight' => '1.00',
        'finish_weight' => '0.80',
        'notes' => 'Sebelum update',
        'status_item' => 'OK',
    ]);

    $response = $this->put(route('poles-rangka.update', $document), [
        'spk_id' => $productionB->row_id,
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '2.50',
        'finish_weight' => '2.00',
        'status_item' => 'NOK',
        'notes' => 'Sesudah update',
    ]);

    $document->refresh();

    $response->assertRedirect(route('poles-rangka.show', $document));

    expect($document->spk_id)->toBe($productionB->row_id)
        ->and((string) $document->start_weight)->toBe('2.50')
        ->and((string) $document->finish_weight)->toBe('2.00')
        ->and((string) $document->shrink)->toBe('0.50')
        ->and($document->notes)->toBe('Sesudah update')
        ->and($document->status_item)->toBe('NOK');

    $productionB->refresh();

    expect((float) $productionB->last_weight)->toBe(2.0);

    $document->delete();
    $productionA->delete();
    $productionB->delete();
});

test('poles rangka edit is forbidden for done documents', function () {
    $document = PolishFrame::factory()->done()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
    ]);

    $this->get(route('poles-rangka.edit', $document))->assertForbidden();

    $document->delete();
});
