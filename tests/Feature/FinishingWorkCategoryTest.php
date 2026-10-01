<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Str;

test('finishing create page exposes work category options', function () {
    $this->get(route('finishing.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('workCategoryOptions', 5)
            ->has('workTypeOptionsByCategory')
            ->where('form.workCategory', null)
            ->where('form.workType', null)
        );
});

test('finishing store saves work category and work type', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINWORK'.Str::upper(Str::random(3)),
    ]);

    $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'work_category' => 'Finishing',
        'work_type' => 'Finishing 1',
    ]))->assertSessionHasNoErrors();

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    try {
        expect($document)->not->toBeNull()
            ->and($document->work_category)->toBe('Finishing')
            ->and($document->work_type)->toBe('Finishing 1');
    } finally {
        $document?->delete();
        $production->delete();
    }
});

test('finishing store accepts custom work type', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINWORKX'.Str::upper(Str::random(3)),
    ]);

    $this->post(route('finishing.store'), validFinishingSerahPayload([
        'spk_id' => $production->row_id,
        'work_category' => 'Finishing',
        'work_type' => 'Pekerjaan Khusus',
    ]))->assertSessionHasNoErrors();

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    try {
        expect($document)->not->toBeNull()
            ->and($document->work_type)->toBe('Pekerjaan Khusus');
    } finally {
        $document?->delete();
        $production->delete();
    }
});

test('finishing store requires all serah ke pengrajin fields', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINREQ'.Str::upper(Str::random(3)),
    ]);

    try {
        $this->from(route('finishing.create'))
            ->post(route('finishing.store'), [
                'spk_id' => $production->row_id,
                'process_name' => 'Finishing',
            ])
            ->assertSessionHasErrors([
                'craftsman_id',
                'send_craftsman_date',
                'start_weight',
                'item_category',
                'work_category',
                'work_type',
                'notes',
            ]);
    } finally {
        $production->delete();
    }
});

test('finishing store rejects work type without work category', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINWORKY'.Str::upper(Str::random(3)),
    ]);

    try {
        $this->from(route('finishing.create'))
            ->post(route('finishing.store'), validFinishingSerahPayload([
                'spk_id' => $production->row_id,
                'work_category' => null,
                'work_type' => 'Finishing 1',
            ]))
            ->assertSessionHasErrors('work_category');
    } finally {
        $production->delete();
    }
});
