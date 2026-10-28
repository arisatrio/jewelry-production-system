<?php

use App\Models\MsChain;
use App\Models\MsComponent;
use App\Support\GoogleCloudStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

test('chain master store uploads photo to gcs inventory chain folder', function () {
    if (! Schema::connection('third')->hasColumn('mschain', 'photo')) {
        $this->markTestSkipped('Column mschain.photo is not available.');
    }

    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->withArgs(function ($file, string $folder, string $filename): bool {
                return $file instanceof UploadedFile
                    && $folder === 'inventory/chain'
                    && str_starts_with($filename, 'inv_');
            })
            ->andReturn('https://storage.googleapis.com/system-mahakarya/inventory/chain/inv_test.png');
    });

    $itemCode = 'PHO-'.Str::upper(Str::random(8));

    $this->post(route('inventory.chains.store'), [
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item' => 'Chain Photo Test',
        'item_code' => $itemCode,
        'length' => '18',
        'photo' => UploadedFile::fake()->image('chain.png', 100, 100),
    ])->assertRedirect(route('inventory.chains.index'));

    $item = MsChain::query()->notDeleted()->where('item_code', $itemCode)->first();

    expect($item)->not->toBeNull()
        ->and($item->photo)->toBe('https://storage.googleapis.com/system-mahakarya/inventory/chain/inv_test.png');

    $item->delete();
});

test('component master store uploads photo to gcs inventory component folder', function () {
    if (! Schema::connection('third')->hasColumn('mscomponent', 'photo')) {
        $this->markTestSkipped('Column mscomponent.photo is not available.');
    }

    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->withArgs(function ($file, string $folder, string $filename): bool {
                return $file instanceof UploadedFile
                    && $folder === 'inventory/component'
                    && str_starts_with($filename, 'inv_');
            })
            ->andReturn('https://storage.googleapis.com/system-mahakarya/inventory/component/inv_test.png');
    });

    $itemCode = 'PHO-'.Str::upper(Str::random(8));

    $this->post(route('inventory.components.store'), [
        'component_category' => MsComponent::CATEGORY_KOMPONEN,
        'item' => 'Komponen Photo Test',
        'item_code' => $itemCode,
        'photo' => UploadedFile::fake()->image('component.png', 100, 100),
    ])->assertRedirect(route('inventory.components.index'));

    $item = MsComponent::query()->notDeleted()->where('item_code', $itemCode)->first();

    expect($item)->not->toBeNull()
        ->and($item->photo)->toBe('https://storage.googleapis.com/system-mahakarya/inventory/component/inv_test.png');

    $item->delete();
});

test('chain show page exposes master photo url', function () {
    if (! Schema::connection('third')->hasColumn('mschain', 'photo')) {
        $this->markTestSkipped('Column mschain.photo is not available.');
    }

    $item = MsChain::factory()->create([
        'item_code' => 'SHW-'.Str::upper(Str::random(8)),
        'photo' => 'https://storage.googleapis.com/example/chain.jpg',
    ]);

    try {
        $this->get(route('inventory.chains.show', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/chain/show')
                ->where('item.photo', 'https://storage.googleapis.com/example/chain.jpg')
            );
    } finally {
        $item->delete();
    }
});
