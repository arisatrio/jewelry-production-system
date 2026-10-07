<?php

use App\Models\FinishingHandmade;
use App\Support\FinishingNotesWorkTypeBackfill;
use Illuminate\Support\Str;

test('finishing work type backfill stores the allowance when item category exists', function () {
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'notes' => 'FINISHING 1',
        'item_category' => 'Barang Kecil',
        'work_category' => null,
        'work_type' => null,
        'shrink_tolerance' => '22.47',
    ]);

    try {
        expect(app(FinishingNotesWorkTypeBackfill::class)->apply($document))->toBeTrue();

        $document->refresh();

        expect($document->work_category)->toBe('Finishing')
            ->and($document->work_type)->toBe('Finishing 1')
            ->and((string) $document->shrink_tolerance)->toBe(finishingShrinkAllowancePercent())
            ->and($document->modified_by)->toBe('system');
    } finally {
        $document->delete();
    }
});

test('finishing work type backfill keeps shrink tolerance when item category is empty', function () {
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'notes' => 'REP. BOLONG',
        'item_category' => null,
        'work_category' => null,
        'work_type' => null,
        'shrink_tolerance' => '4.25',
    ]);

    try {
        expect(app(FinishingNotesWorkTypeBackfill::class)->apply($document))->toBeTrue();

        $document->refresh();

        expect($document->work_category)->toBe('Repair')
            ->and($document->work_type)->toBe('Repair Bolong')
            ->and((string) $document->shrink_tolerance)->toBe('4.25');
    } finally {
        $document->delete();
    }
});

test('finishing work type backfill skips filled or ambiguous documents', function () {
    $filled = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'notes' => 'PASANG CHAIN',
        'work_category' => 'Finishing',
        'work_type' => 'Finishing 2',
        'item_category' => 'Barang Kecil',
    ]);
    $ambiguous = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'notes' => 'SETTING ENGSEL & REP. BOLONG',
        'work_category' => null,
        'work_type' => null,
        'shrink_tolerance' => '1.10',
    ]);

    try {
        $backfill = app(FinishingNotesWorkTypeBackfill::class);

        expect($backfill->apply($filled))->toBeFalse()
            ->and($backfill->apply($ambiguous))->toBeFalse();

        $filled->refresh();
        $ambiguous->refresh();

        expect($filled->work_type)->toBe('Finishing 2')
            ->and($ambiguous->work_type)->toBeNull()
            ->and((string) $ambiguous->shrink_tolerance)->toBe('1.10');
    } finally {
        $filled->delete();
        $ambiguous->delete();
    }
});
