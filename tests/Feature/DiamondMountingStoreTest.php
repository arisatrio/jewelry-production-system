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
        'weight_diamond' => '0.02',
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
        ->and((float) $document->weight_diamond)->toBe(0.02)
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

test('pasang batu store accepts diamond weight with up to three decimals', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTDEC'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('pasang-batu.store'), [
        'spk_id' => $production->row_id,
        'weight_frame' => '3.16',
        'weight_diamond' => '0.023',
        'weight_finish_goods' => '3.10',
    ]);

    $document = DiamondMounting::query()
        ->where('spk_id', $production->row_id)
        ->where('is_deleted', 0)
        ->latest('row_id')
        ->first();

    expect($document)->not->toBeNull();
    $response->assertRedirect(route('pasang-batu.show', $document));
    expect((float) $document->weight_diamond)->toBe(0.023);

    $document->delete();
    $production->delete();
});

test('pasang batu store rejects diamond weight with more than three decimals', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTDE4'.Str::upper(Str::random(3)),
    ]);

    $this->from(route('pasang-batu.create'))
        ->post(route('pasang-batu.store'), [
            'spk_id' => $production->row_id,
            'weight_frame' => '3.16',
            'weight_diamond' => '0.0234',
        ])
        ->assertRedirect(route('pasang-batu.create'))
        ->assertSessionHasErrors(['weight_diamond']);

    $production->delete();
});

test('pasang batu show and index calculate shrink when legacy document has no stored shrink', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTSHR'.Str::upper(Str::random(3)),
    ]);
    $document = DiamondMounting::factory()->create([
        'doc_no' => 'DMD'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'weight_frame' => '19.83',
        'weight_diamond' => '0.833',
        'total_weigth_frame_diamond' => null,
        'weight_finish_goods' => '20.46',
        'mounting_shrink' => null,
    ]);

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('diamondMountingItem.totalWeight', '20.66')
            ->where('diamondMountingItem.shrink', '0.20')
            ->where('diamondMountingItem.shrinkPercent', '0.97%')
        );

    $this->get(route('pasang-batu.index', ['search' => $document->doc_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.shrink', '0.20')
        );

    $document->delete();
    $production->delete();
});

test('pasang batu show leaves shrink empty when finish weight is missing', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTSHN'.Str::upper(Str::random(3)),
    ]);
    $document = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'weight_frame' => '19.83',
        'weight_finish_goods' => null,
        'mounting_shrink' => null,
    ]);

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('diamondMountingItem.shrink', null)
            ->where('diamondMountingItem.shrinkPercent', null)
        );

    $document->delete();
    $production->delete();
});

test('pasang batu show and edit display diamond weight with three decimals', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTDSP'.Str::upper(Str::random(3)),
    ]);
    $document = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'weight_diamond' => '0.833',
    ]);

    $this->get(route('pasang-batu.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('diamondMountingItem.weightDiamond', '0.833')
        );

    $this->get(route('pasang-batu.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('form.weightDiamond', '0.833')
        );

    $document->delete();
    $production->delete();
});
