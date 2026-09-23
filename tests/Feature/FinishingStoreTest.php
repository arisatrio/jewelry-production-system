<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Str;

test('finishing create page is accessible', function () {
    $this->get(route('finishing.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/create')
            ->has('formDocumentNo')
            ->has('processOptions')
            ->has('itemCategoryOptions')
            ->has('craftsmanOptions')
            ->has('materialOptions')
            ->has('form.sendCraftsmanDate')
            ->has('form.materials')
            ->where('form.spk', null)
            ->missing('form.details')
        );
});

test('finishing store creates document with spk', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINSTORE'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('finishing.store'), [
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'shrink_tolerance' => '6.90',
        'notes' => 'Catatan store finishing',
        'materials' => [],
    ]);

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('finishing.show', $document));

    expect($document->doc_no)->toMatch('/^FIN\d{7}$/')
        ->and($document->status)->toBeNull()
        ->and($document->is_from_new_system)->toBe(1)
        ->and($document->process_name)->toBe('Finishing')
        ->and((string) $document->start_weight)->toBe('3.16')
        ->and((string) $document->finish_weight)->toBe('2.45')
        ->and($document->notes)->toBe('Catatan store finishing');

    $production->refresh();

    expect((float) $production->last_weight)->toBe(2.45);

    $document->delete();
    $production->delete();
});

test('finishing store requires spk', function () {
    $this->from(route('finishing.create'))
        ->post(route('finishing.store'), [
            'process_name' => 'Finishing',
            'spk_id' => null,
        ])
        ->assertRedirect(route('finishing.create'))
        ->assertSessionHasErrors('spk_id');
});

test('finishing search spks returns json', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINSEL'.Str::upper(Str::random(3)),
        'last_weight' => 3.25,
    ]);

    $this->getJson(route('finishing.select.spks', [
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
