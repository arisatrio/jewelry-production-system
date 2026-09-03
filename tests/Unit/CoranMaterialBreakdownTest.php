<?php

use App\Models\Coran;
use App\Support\CoranMaterialBreakdown;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('coran material breakdown returns empty sections when no material rows', function () {
    $service = app(CoranMaterialBreakdown::class);
    $empty = $service->empty();

    expect($empty)->toHaveCount(3)
        ->and($empty[0]['color'])->toBe('Rose Gold')
        ->and($empty[0]['bahan'])->toBe([])
        ->and($empty[0]['sisa'])->toBe([]);
});

test('coran material breakdown loads bahan and sisa lines for a document', function () {
    if (
        ! Schema::connection('third')->hasTable('trmaterialgold')
        || ! Schema::connection('third')->hasTable('msmaterialgold')
    ) {
        $this->markTestSkipped('Material gold tables are not available.');
    }

    $coran = Coran::query()
        ->notDeleted()
        ->orderByDesc('row_id')
        ->first();

    if ($coran === null) {
        $this->markTestSkipped('No coran documents available.');
    }

    $breakdown = app(CoranMaterialBreakdown::class)
        ->forIds([(int) $coran->row_id])[(int) $coran->row_id] ?? [];

    expect($breakdown)->toHaveCount(3)
        ->and($breakdown[0])->toHaveKeys(['color', 'colorKey', 'bahan', 'sisa']);

    $hasLines = collect($breakdown)->contains(
        fn (array $section): bool => $section['bahan'] !== [] || $section['sisa'] !== [],
    );

    if (! $hasLines) {
        $this->markTestSkipped('Selected coran has no material gold breakdown lines.');
    }

    expect($hasLines)->toBeTrue();
});
