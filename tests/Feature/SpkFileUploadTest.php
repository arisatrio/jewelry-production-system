<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('spk update can store uploaded image path longer than 50 characters', function () {
    Storage::fake('public');

    $production = app(SpkService::class)->createStock('system');
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
    ]);

    $file = UploadedFile::fake()->image('spk-gambar.png', 120, 120);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Upload file name length',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter' => '',
        'dimensi' => 'Panjang 150',
        'ring_size' => '',
        'diameter_length_ringsize' => ' / Panjang 150 / ',
        'gold_weight' => 2.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        'file' => $file,
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $production->refresh();

    expect($production->file_name)->not->toBeNull()
        ->and(strlen((string) $production->file_name))->toBeGreaterThan(50)
        ->and($production->file_name)->toStartWith('spk/'.$production->row_id.'/')
        ->and($production->diameter_length_ringsize)->toBe(' / Panjang 150 / ')
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    Storage::disk('public')->assertExists($production->file_name);

    $production->delete();
    $sku->delete();
});

test('spk update repairs corrupted diameter combined label without duplicating ukuran', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Repair Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Repair Product '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Repair ukuran',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter' => ' / Panjang 150 / ',
        'dimensi' => 'Panjang 150',
        'ring_size' => '',
        'diameter_length_ringsize' => ' / Panjang 150 / / Panjang 150 / ',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $production->refresh();

    expect($production->diameter_length_ringsize)->toBe(' / Panjang 150 / ')
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $production->delete();
    $sku->delete();
});
