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
                'weight_rosegold' => '1.100',
                'weight_whitegold' => '0.400',
                'weight_yellowgold' => '1.000',
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
        ->and((string) $detail->weight_rosegold)->toBe('1.100')
        ->and((string) $detail->weight_whitegold)->toBe('0.400')
        ->and((string) $detail->weight_yellowgold)->toBe('1.000')
        ->and((string) $detail->weight)->toBe('2.500');

    $production->refresh();

    expect((float) $production->last_weight)->toBe(2.5);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.details.0.weightRosegold', '1.100')
            ->where('coranItem.details.0.weightWhitegold', '0.400')
            ->where('coranItem.details.0.weightYellowgold', '1.000')
            ->where('coranItem.details.0.weight', '2.500')
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
        'weight' => '1.000',
        'weight_rosegold' => '1.000',
        'weight_whitegold' => null,
        'weight_yellowgold' => null,
    ]);

    $this->put(route('coran.update', $coran), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight_rosegold' => '0.500',
                'weight_whitegold' => '0.750',
                'weight_yellowgold' => '0.250',
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
        ->and((string) $detail->weight_rosegold)->toBe('0.500')
        ->and((string) $detail->weight_whitegold)->toBe('0.750')
        ->and((string) $detail->weight_yellowgold)->toBe('0.250')
        ->and((string) $detail->weight)->toBe('1.500');

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
