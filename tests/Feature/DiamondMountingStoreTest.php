<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Support\DiamondMountingSpkEligibility;
use Illuminate\Support\Str;

test('pasang batu create page is accessible', function () {
    $this->get(route('pasang-batu.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/create')
            ->has('formDocumentNo')
            ->has('craftsmanOptions')
            ->has('form.sendCraftsmanDate')
            ->where('form.spk', null)
            ->missing('form.materials')
            ->missing('statusItemOptions')
        );
});

test('pasang batu store creates document with spk and marks process started', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTSTR'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'weight_frame' => '3.16',
        'weight_diamond' => '0.023',
        'weight_finish_goods' => '3.10',
        'notes' => 'Catatan store pasang batu',
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('pasang-batu.show', $document));

    $production->refresh();

    expect($document->doc_no)->toMatch('/^DMD\d{7}$/')
        ->and($document->status)->toBeNull()
        ->and($document->is_from_new_system)->toBe(1)
        ->and($document->process_name)->toBe('Pasang Batu')
        ->and((string) $document->weight_frame)->toBe('3.16')
        ->and((float) $document->weight_diamond)->toBe(0.023)
        ->and((string) $document->total_weigth_frame_diamond)->toBe('3.18')
        ->and((string) $document->weight_finish_goods)->toBe('3.10')
        ->and((string) $document->mounting_shrink)->toBe('0.08')
        ->and($document->notes)->toBe('Catatan store pasang batu')
        ->and($production->last_process)->toBe(DiamondMountingSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1)
        ->and((float) $production->last_weight)->toBe(3.10);

    $document->delete();
    $production->delete();
});

test('pasang batu store requires spk', function () {
    $this->from(route('pasang-batu.create'))
        ->post(route('pasang-batu.store'), [
            'spk_id' => null,
        ])
        ->assertRedirect(route('pasang-batu.create'))
        ->assertSessionHasErrors('spk_id');
});

test('pasang batu store allows null weight_finish_goods', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTNUL'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '2.90',
        'weight_finish_goods' => null,
    ]);

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();
    $response->assertRedirect(route('pasang-batu.show', $document));
    expect($document->weight_finish_goods)->toBeNull()
        ->and((string) $document->mounting_shrink)->toBe('0.00');

    $document->delete();
    $production->delete();
});
