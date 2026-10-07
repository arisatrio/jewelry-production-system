<?php

use App\Support\FinishingNotesWorkTypeBackfill;
use Tests\TestCase;

uses(TestCase::class);

test('finishing notes resolve to one work category and work type', function (string $notes, string $workCategory, string $workType) {
    $resolved = app(FinishingNotesWorkTypeBackfill::class)->resolve($notes);

    expect($resolved)->toBe([
        'work_category' => $workCategory,
        'work_type' => $workType,
    ]);
})->with([
    'finishing 1' => ['FINISHING 1', 'Finishing', 'Finishing 1'],
    'finishing typo' => ['FINIHSING 1', 'Finishing', 'Finishing 1'],
    'finishing with detail' => ['FINISHING 1 KOMPONEN BRACELLET', 'Finishing', 'Finishing 1'],
    'pasang chain' => ['PASANG CHAIN 40 RING 38 CM', 'Pasang / Setting', 'Pasang / Ganti Chain'],
    'repair bolong punctuation' => ['REP. BOLONG', 'Repair', 'Repair Bolong'],
    'setting stopper typo' => ['SETTING STOPER', 'Pasang / Setting', 'Setting Stopper'],
    'set engsel' => ['SET ENGSEL', 'Pasang / Setting', 'Setting Engsel'],
    'resize' => ['RESIZE DARI 12 HK KE 15 HK', 'Ukuran', 'Resize Ukuran (HK)'],
    'finishing komponen' => ['FINISHING KOMPONEN NECKLACE', 'Finishing', 'Finishing Komponen'],
    'doff' => ['DOFF', 'Finishing', 'Poles / Doff / Permukaan'],
]);

test('finishing notes stay unresolved when the work type is not explicit', function (string $notes) {
    expect(app(FinishingNotesWorkTypeBackfill::class)->resolve($notes))->toBeNull();
})->with([
    'empty' => [''],
    'generic finishing' => ['FINISHING'],
    'generic repair' => ['REPAIR'],
    'work type in the middle' => ['ABIS POLES BOLONG'],
    'two work types' => ['SETTING ENGSEL & REP. BOLONG'],
    'unknown note' => ['GANTI BATANG'],
]);
