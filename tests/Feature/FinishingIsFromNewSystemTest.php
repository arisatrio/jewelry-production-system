<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('finishinghandmade table has is_from_new_system column', function () {
    expect(Schema::connection('third')->hasColumn('finishinghandmade', 'is_from_new_system'))->toBeTrue();
});

test('finishing factory defaults is_from_new_system to zero', function () {
    $document = FinishingHandmade::factory()->create();

    expect($document->is_from_new_system)->toBe(0);

    $document->delete();
});

test('finishing store sets is_from_new_system to one', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINNEW'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('finishing.store'), [
        'spk_id' => $production->row_id,
        'process_name' => 'Finishing',
        'craftsman_id' => null,
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '3.16',
        'finish_weight' => '2.45',
        'notes' => 'Catatan finishing new system',
        'materials' => [],
    ]);

    $document = FinishingHandmade::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($document)->not->toBeNull()
        ->and($document->is_from_new_system)->toBe(1);

    $response->assertRedirect(route('finishing.show', $document));

    $document->delete();
    $production->delete();
});
