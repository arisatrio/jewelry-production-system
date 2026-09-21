<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran store persists kadar and status per gold color', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'kadar_rosegold')) {
        $this->markTestSkipped('Column coranspk.kadar_rosegold is not available.');
    }

    if (! Schema::connection('third')->hasColumn('coranspk', 'status_rosegold')) {
        $this->markTestSkipped('Column coranspk.status_rosegold is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORKSC'.Str::upper(Str::random(3)),
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
                'kadar_rosegold' => '75.00',
                'kadar_whitegold' => '91.60',
                'kadar_yellowgold' => '37.50',
                'status_rosegold' => 'OK',
                'status_whitegold' => 'NOK',
                'status_yellowgold' => 'OK',
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
        ->and((string) $detail->kadar_rosegold)->toBe('75.00')
        ->and((string) $detail->kadar_whitegold)->toBe('91.60')
        ->and((string) $detail->kadar_yellowgold)->toBe('37.50')
        ->and((string) $detail->kadar)->toBe('75.00')
        ->and((string) $detail->status_rosegold)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->status_whitegold)->toBe(CoranSpk::STATUS_NOK)
        ->and((string) $detail->status_yellowgold)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->status)->toBe(CoranSpk::STATUS_NOK);

    $this->get(route('coran.show', $coran))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/show')
            ->where('coranItem.details.0.kadarRosegold', '75.00')
            ->where('coranItem.details.0.kadarWhitegold', '91.60')
            ->where('coranItem.details.0.kadarYellowgold', '37.50')
            ->where('coranItem.details.0.statusRosegold', CoranSpk::STATUS_OK)
            ->where('coranItem.details.0.statusWhitegold', CoranSpk::STATUS_NOK)
            ->where('coranItem.details.0.statusYellowgold', CoranSpk::STATUS_OK)
            ->where('coranItem.details.0.status', CoranSpk::STATUS_NOK)
        );

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran update persists kadar and status color changes', function () {
    if (! Schema::connection('third')->hasColumn('coranspk', 'kadar_rosegold')) {
        $this->markTestSkipped('Column coranspk.kadar_rosegold is not available.');
    }

    if (! Schema::connection('third')->hasColumn('coranspk', 'status_rosegold')) {
        $this->markTestSkipped('Column coranspk.status_rosegold is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORUSC'.Str::upper(Str::random(3)),
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
        'kadar' => '37.50',
        'kadar_rosegold' => '37.50',
        'status' => CoranSpk::STATUS_OK,
        'status_rosegold' => CoranSpk::STATUS_OK,
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
                'kadar_rosegold' => '91.60',
                'kadar_whitegold' => '75.00',
                'kadar_yellowgold' => '37.50',
                'status_rosegold' => 'OK',
                'status_whitegold' => 'OK',
                'status_yellowgold' => 'OK',
            ],
        ],
        'materials' => [],
    ])->assertRedirect(route('coran.show', $coran));

    $detail = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->kadar_rosegold)->toBe('91.60')
        ->and((string) $detail->kadar_whitegold)->toBe('75.00')
        ->and((string) $detail->kadar_yellowgold)->toBe('37.50')
        ->and((string) $detail->status_rosegold)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->status_whitegold)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->status_yellowgold)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->status)->toBe(CoranSpk::STATUS_OK)
        ->and((string) $detail->weight)->toBe('1.50');

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});
