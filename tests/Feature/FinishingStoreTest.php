<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Facades\DB;
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

    $response = $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'notes' => 'Catatan store finishing',
    ]));

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
        ->and((string) $document->shrink)->toBe('0.71')
        ->and((string) $document->shrink_tolerance)->toBe('22.47')
        ->and($document->notes)->toBe('Catatan store finishing');

    $production->refresh();

    expect((float) $production->last_weight)->toBe(2.45);

    $document->delete();
    $production->delete();
});

test('finishing create page defaults qc status to ok', function () {
    $this->get(route('finishing.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('form.koreksiQc', '0')
            ->where('form.keteranganQc', '')
        );
});

test('finishing store saves qc status and notes', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINQC'.Str::upper(Str::random(3)),
    ]);

    $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'koreksi_qc' => '1',
        'keterangan_qc' => ' bolong ',
    ]))->assertSessionHasNoErrors();

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    try {
        expect($document)->not->toBeNull()
            ->and($document->koreksi_qc)->toBe(1)
            ->and($document->keterangan_qc)->toBe('bolong');
    } finally {
        $document?->delete();
        $production->delete();
    }
});

test('finishing qc note options include newly saved notes without case duplicates', function () {
    $note = 'Retak Uji '.Str::upper(Str::random(5));
    $documents = collect([
        FinishingHandmade::factory()->create(['koreksi_qc' => 1, 'keterangan_qc' => $note]),
        FinishingHandmade::factory()->create(['koreksi_qc' => 1, 'keterangan_qc' => ' '.mb_strtolower($note).' ']),
    ]);

    try {
        $options = $this->get(route('finishing.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('qcNoteOptions'))
            ->viewData('page')['props']['qcNoteOptions'];

        $matches = collect($options)
            ->filter(fn (string $option): bool => mb_strtolower($option) === mb_strtolower($note))
            ->values();

        expect($matches)->toHaveCount(1);

        $this->get(route('finishing.edit', $documents->first()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('qcNoteOptions'));
    } finally {
        $documents->each->delete();
    }
});

test('finishing store rejects invalid qc input', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINQCX'.Str::upper(Str::random(3)),
    ]);

    try {
        $this->from(route('finishing.create'))
            ->post(route('finishing.store'), validFinishingSerahPayload([
                'spk_id' => $production->row_id,
                'koreksi_qc' => '2',
                'keterangan_qc' => str_repeat('a', 101),
            ]))
            ->assertRedirect(route('finishing.create'))
            ->assertSessionHasErrors(['koreksi_qc', 'keterangan_qc']);
    } finally {
        $production->delete();
    }
});

test('finishing store requires qc notes when qc is not ok', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINQCN'.Str::upper(Str::random(3)),
    ]);

    try {
        $this->from(route('finishing.create'))
            ->post(route('finishing.store'), validFinishingSerahPayload([
                'spk_id' => $production->row_id,
                'koreksi_qc' => '1',
                'keterangan_qc' => '  ',
            ]))
            ->assertRedirect(route('finishing.create'))
            ->assertSessionHasErrors([
                'keterangan_qc' => 'Catatan QC wajib diisi jika status QC NOT OK.',
            ]);

        $this->from(route('finishing.create'))
            ->post(route('finishing.store'), validFinishingSerahPayload([
                'spk_id' => $production->row_id,
                'koreksi_qc' => '0',
                'keterangan_qc' => null,
            ]))
            ->assertSessionDoesntHaveErrors('keterangan_qc');
    } finally {
        FinishingHandmade::query()->where('spk_id', $production->row_id)->delete();
        $production->delete();
    }
});

test('finishing store calculates shrink tolerance from start weight and bahan', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINSTOL'.Str::upper(Str::random(3)),
    ]);

    $materialId = (int) DB::connection('third')
        ->table('msmaterialgold')
        ->where('is_deleted', 0)
        ->orderBy('row_id')
        ->value('row_id');

    expect($materialId)->toBeGreaterThan(0);

    $response = $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'start_weight' => '0.87',
        'finish_weight' => '0.73',
        'materials' => [
            [
                'section' => 'bahan',
                'materialgold_id' => $materialId,
                'weight' => '0.10',
            ],
            [
                'section' => 'sisa',
                'materialgold_id' => $materialId,
                'weight' => '0.09',
            ],
            [
                'section' => 'sisa',
                'materialgold_id' => $materialId,
                'weight' => '0.11',
            ],
        ],
    ]));

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('finishing.show', $document));

    expect((string) $document->submit_materialgold)->toBe('0.10')
        ->and((string) $document->result_materialgold)->toBe('0.20')
        ->and((string) $document->shrink)->toBe('0.04')
        ->and((string) $document->shrink_tolerance)->toBe('4.12');

    $document->delete();
    $production->delete();
});

test('finishing store keeps surplus shrink as negative value', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINNOSHR'.Str::upper(Str::random(3)),
    ]);

    $materialId = (int) DB::connection('third')
        ->table('msmaterialgold')
        ->where('is_deleted', 0)
        ->orderBy('row_id')
        ->value('row_id');

    expect($materialId)->toBeGreaterThan(0);

    $response = $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'start_weight' => '1.19',
        'finish_weight' => '3.03',
        'materials' => [
            [
                'section' => 'bahan',
                'materialgold_id' => $materialId,
                'weight' => '2.32',
            ],
            [
                'section' => 'sisa',
                'materialgold_id' => $materialId,
                'weight' => '0.66',
            ],
        ],
    ]));

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('finishing.show', $document));

    expect((string) $document->submit_materialgold)->toBe('2.32')
        ->and((string) $document->result_materialgold)->toBe('0.66')
        ->and((string) $document->shrink)->toBe('-0.18')
        ->and((string) $document->shrink_tolerance)->toBe('-5.13');

    $document->delete();
    $production->delete();
});

test('finishing store sets shrink to zero when finish weight is zero', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINZERO'.Str::upper(Str::random(3)),
    ]);

    $materialId = (int) DB::connection('third')
        ->table('msmaterialgold')
        ->where('is_deleted', 0)
        ->orderBy('row_id')
        ->value('row_id');

    $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'start_weight' => '2.00',
        'finish_weight' => '0',
        'materials' => [
            [
                'section' => 'bahan',
                'materialgold_id' => $materialId,
                'weight' => '3.60',
            ],
            [
                'section' => 'sisa',
                'materialgold_id' => $materialId,
                'weight' => '4.69',
            ],
        ],
    ]));

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull()
        ->and((string) $document->shrink)->toBe('0.00')
        ->and((string) $document->shrink_tolerance)->toBe('0.00');

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
