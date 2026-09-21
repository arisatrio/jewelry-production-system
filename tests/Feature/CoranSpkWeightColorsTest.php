<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran store persists weight per gold color and totals weight', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'weight_rosegold')) {
        $this->markTestSkipped('Column coranspk.weight_rosegold is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORCLR'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight_rosegold' => '1.10',
                'weight_whitegold' => '0.40',
                'weight_yellowgold' => '1.00',
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
        ->and((string) $detail->weight_rosegold)->toBe('1.10')
        ->and((string) $detail->weight_whitegold)->toBe('0.40')
        ->and((string) $detail->weight_yellowgold)->toBe('1.00')
        ->and((string) $detail->weight)->toBe('2.50');

    $production->refresh();

    expect((float) $production->last_weight)->toBe(2.5);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.details.0.weightRosegold', '1.10')
            ->where('coranItem.details.0.weightWhitegold', '0.40')
            ->where('coranItem.details.0.weightYellowgold', '1.00')
            ->where('coranItem.details.0.weight', '2.50')
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran update persists weight color changes', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'weight_rosegold')) {
        $this->markTestSkipped('Column coranspk.weight_rosegold is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORUCL'.Str::upper(Str::random(3)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => null,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '1.00',
        'weight_rosegold' => '1.00',
        'weight_whitegold' => null,
        'weight_yellowgold' => null,
    ]);

    $this->put(route('coran.update', $coran), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight_rosegold' => '0.50',
                'weight_whitegold' => '0.75',
                'weight_yellowgold' => '0.25',
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
        ->and((string) $detail->weight_rosegold)->toBe('0.50')
        ->and((string) $detail->weight_whitegold)->toBe('0.75')
        ->and((string) $detail->weight_yellowgold)->toBe('0.25')
        ->and((string) $detail->weight)->toBe('1.50');

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
