<?php

use App\Models\PolishFrame;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('polishframe finish_weight column is nullable', function () {
    expect(Schema::connection('third')->hasColumn('polishframe', 'finish_weight'))->toBeTrue();

    $column = collect(Schema::connection('third')->getColumns('polishframe'))
        ->firstWhere('name', 'finish_weight');

    expect($column)->not->toBeNull()
        ->and($column['nullable'] ?? false)->toBeTrue();
});

test('poles rangka store allows null finish_weight', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKNUL'.Str::upper(Str::random(3)),
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
    ]);

    $response = $this->post(route('poles-rangka.store'), [
        'spk_id' => $production->row_id,
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '2.90',
        'finish_weight' => null,
        'status_item' => null,
        'notes' => null,
    ]);

    $document = PolishFrame::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('poles-rangka.show', $document));

    expect($document->finish_weight)->toBeNull()
        ->and((string) $document->shrink)->toBe('0.00');

    $document->delete();
    $production->delete();
});
