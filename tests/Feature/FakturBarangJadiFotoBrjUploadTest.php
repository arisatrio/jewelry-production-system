<?php

use App\Models\Production;
use App\Models\User;
use App\Support\GoogleCloudStorageService;
use App\Support\SpkService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

test('authenticated user can upload foto brj for completed spk', function () {
    if (! Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $this->markTestSkipped('Column spk.foto_brj is not available.');
    }

    $production = Production::factory()->create([
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insert([
        'doc_no' => 'TEST-FBJ-FOTO-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $expectedStoredName = 'brj/'.app(SpkService::class)->sanitizeSpkNoForFotoBrjFilename((string) $production->spk_no).'.jpg';

    $gcs = Mockery::mock(GoogleCloudStorageService::class);
    $gcs->shouldReceive('uploadFile')
        ->once()
        ->withArgs(function ($file, string $folder, string $filename) use ($production): bool {
            $baseName = app(SpkService::class)->sanitizeSpkNoForFotoBrjFilename((string) $production->spk_no);

            return $file instanceof UploadedFile
                && $folder === 'produksi/brj'
                && $filename === "{$baseName}.jpg";
        })
        ->andReturnUsing(function ($file, string $folder, string $filename): string {
            return "https://storage.googleapis.com/system-mahakarya/{$folder}/{$filename}";
        });
    $this->instance(GoogleCloudStorageService::class, $gcs);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('foto-brj.jpg', 120, 120);

    $this->actingAs($user)
        ->from(route('faktur-barang-jadi.create'))
        ->post(route('faktur-barang-jadi.spk.foto-brj.update', $production->row_id), [
            'foto' => $file,
        ])
        ->assertRedirect(route('faktur-barang-jadi.create'));

    expect($production->refresh()->foto_brj)->toBe($expectedStoredName);

    $production->delete();
});

test('authenticated user can update last weight when confirming spk for faktur', function () {
    if (! Schema::connection('third')->hasColumn('spk', 'last_weight')) {
        $this->markTestSkipped('Column spk.last_weight is not available.');
    }

    $production = Production::factory()->create([
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'last_weight' => null,
        'foto_brj' => Schema::connection('third')->hasColumn('spk', 'foto_brj')
            ? 'brj/existing-foto.jpg'
            : null,
    ]);

    DB::connection('third')->table('polishfinishedgood')->insert([
        'doc_no' => 'TEST-FBJ-WGHT-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('faktur-barang-jadi.create'))
        ->post(route('faktur-barang-jadi.spk.foto-brj.update', $production->row_id), [
            'last_weight' => '4,66',
            'with_last_weight' => true,
        ])
        ->assertRedirect(route('faktur-barang-jadi.create'));

    expect((float) $production->refresh()->last_weight)->toBe(4.66);

    $production->delete();
});
