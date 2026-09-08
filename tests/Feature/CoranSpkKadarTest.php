<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran store persists kadar for each spk detail', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'kadar')) {
        $this->markTestSkipped('Column coranspk.kadar is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORKAD'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '2.500',
                'kadar' => '75.00',
                'status' => 'OK',
            ],
        ],
        'materials' => [],
    ]);

    $coran = Coran::query()
        ->notDeleted()
        ->whereHas('details', fn ($query) => $query
            ->notDeleted()
            ->where('spk_id', $production->row_id))
        ->orderByDesc('row_id')
        ->first();

    expect($coran)->not->toBeNull();

    $response->assertRedirect(route('coran.show', $coran));

    $detail = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->kadar)->toBe('75.00');

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.details.0.kadar', '75.00')
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran update persists kadar changes', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'kadar')) {
        $this->markTestSkipped('Column coranspk.kadar is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORUKD'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '1.000',
        'kadar' => '37.50',
    ]);

    $this->put(route('coran.update', $coran), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '1.250',
                'kadar' => '91.60',
                'status' => 'OK',
            ],
        ],
        'materials' => [],
    ])->assertRedirect(route('coran.show', $coran));

    $detail = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->kadar)->toBe('91.60')
        ->and((string) $detail->weight)->toBe('1.250');

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
