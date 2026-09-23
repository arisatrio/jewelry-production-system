<?php

use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Support\PolishFinishedGoodSpkEligibility;
use Illuminate\Support\Str;

test('poles chrome create page is accessible', function () {
    $this->get(route('poles-chrome.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-chrome/create')
            ->has('formDocumentNo')
            ->has('craftsmanOptions')
            ->has('statusItemOptions')
            ->has('form.sendCraftsmanDate')
            ->where('form.spk', null)
            ->missing('form.materials')
        );
});

test('poles chrome store creates document with spk and marks process started', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGSTR'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('poles-chrome.store'), [
        'spk_id' => $production->row_id,
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'status_item' => 'OK',
        'notes' => 'Catatan store poles chrome',
    ]);

    $document = PolishFinishedGood::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('poles-chrome.show', $document));

    $production->refresh();

    expect($document->doc_no)->toMatch('/^PFG\d{7}$/')
        ->and($document->status)->toBeNull()
        ->and($document->is_from_new_system)->toBe(1)
        ->and((string) $document->start_weight)->toBe('3.16')
        ->and((string) $document->finish_weight)->toBe('2.45')
        ->and((string) $document->shrink)->toBe('0.71')
        ->and($document->status_item)->toBe('OK')
        ->and($document->notes)->toBe('Catatan store poles chrome')
        ->and($production->last_process)->toBe(PolishFinishedGoodSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1)
        ->and((float) $production->last_weight)->toBe(2.45);

    $document->delete();
    $production->delete();
});

test('poles chrome store requires spk', function () {
    $this->from(route('poles-chrome.create'))
        ->post(route('poles-chrome.store'), [
            'spk_id' => null,
        ])
        ->assertRedirect(route('poles-chrome.create'))
        ->assertSessionHasErrors('spk_id');
});

test('poles chrome store allows null finish_weight', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PFGNUL'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('poles-chrome.store'), [
        'spk_id' => $production->row_id,
        'start_weight' => '2.90',
        'finish_weight' => null,
    ]);

    $document = PolishFinishedGood::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();
    $response->assertRedirect(route('poles-chrome.show', $document));
    expect($document->finish_weight)->toBeNull()
        ->and((string) $document->shrink)->toBe('0.00');

    $document->delete();
    $production->delete();
});
