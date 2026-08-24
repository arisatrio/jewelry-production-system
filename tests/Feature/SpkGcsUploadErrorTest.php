<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\GoogleCloudStorageService;
use App\Support\SpkService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

use function Pest\Laravel\mock;

test('spk store returns validation error when gcs upload fails', function () {
    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->andThrow(new RuntimeException(
                'Gagal mengunggah gambar ke Google Cloud Storage: permission denied',
            ));
    });

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

    $this->from(route('spk.create'))
        ->post(route('spk.store'), [
            'spk_type' => 'Stock',
            'order_date' => '2026-08-03',
            'work_estimated' => 5,
            'priority' => 'NO',
            'description' => 'GCS error store',
            'category_prefix_id' => $category->id,
            'sku_id' => $sku->id,
            'qty' => 1,
            'satuan' => 'Pcs',
            'diameter_length_ringsize' => '16',
            'gold_weight' => 1.5,
            'gold_color' => 'Yellow Gold',
            'status_order' => 'NO',
            'file' => UploadedFile::fake()->image('spk-gagal.png', 80, 80),
        ])
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('file');

    $sku->delete();
});

test('spk update returns validation error when gcs upload fails', function () {
    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->andThrow(new RuntimeException(
                'Gagal mengunggah gambar ke Google Cloud Storage: permission denied',
            ));
    });

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

    $this->from(route('spk.form', $production->row_id))
        ->post(route('spk.update', $production->row_id), [
            'order_date' => '2026-08-03',
            'work_estimated' => 5,
            'priority' => 'NO',
            'description' => 'GCS error update',
            'category_prefix_id' => $category->id,
            'sku_id' => $sku->id,
            'qty' => 1,
            'satuan' => 'Pcs',
            'diameter_length_ringsize' => '16',
            'gold_weight' => 1.5,
            'gold_color' => 'Yellow Gold',
            'status_order' => 'NO',
            'file' => UploadedFile::fake()->image('spk-gagal.png', 80, 80),
        ])
        ->assertRedirect(route('spk.form', $production->row_id))
        ->assertSessionHasErrors('file');

    $production->delete();
    $sku->delete();
});
