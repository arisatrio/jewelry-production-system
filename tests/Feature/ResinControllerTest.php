<?php

use App\Models\MsShape;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinStone;
use App\Support\GoogleCloudStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

test('resin index page is accessible', function () {
    $this->get(route('resin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/index')
            ->has('resins.data')
            ->has('resins.total')
            ->has('filters.search')
            ->has('filters.per_page')
        );
});

test('resin create page is accessible', function () {
    $this->get(route('resin.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/create')
            ->has('form.transDate')
            ->has('form.stones', 0)
            ->has('shapeOptions')
        );
});

test('resin spk selector endpoint returns spk data', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RSL'.Str::upper(Str::random(4)),
        'item_name' => 'Resin Selector Item',
        'customer_name' => 'Resin Customer',
    ]);

    $this->getJson(route('resin.select.spks', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
            'item' => 'Resin Selector Item',
            'customer' => 'Resin Customer',
        ]);

    $production->delete();
});

test('resin store creates document with stones', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RST'.Str::upper(Str::random(4)),
    ]);

    $shape = MsShape::query()->notDeleted()->orderBy('row_id')->first();

    $payload = [
        'trans_date' => '2026-08-24',
        'spk_id' => $production->row_id,
        'stones' => [
            [
                'shape_id' => $shape?->row_id,
                'pcs' => 2,
                'carat' => 4,
                'size' => '1.25',
            ],
        ],
    ];

    $this->post(route('resin.store'), $payload)
        ->assertRedirect(route('resin.index'));

    $resin = Resin::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($resin)->not->toBeNull()
        ->and($resin?->status)->toBe(Resin::STATUS_OPEN)
        ->and($resin?->doc_no)->toStartWith('RES');

    $stone = ResinStone::query()
        ->notDeleted()
        ->where('row_id', $resin?->row_id)
        ->first();

    expect($stone)->not->toBeNull()
        ->and($stone?->pcs)->toBe(2)
        ->and($stone?->carat)->toBe(4);

    if ($stone !== null) {
        $stone->delete();
    }

    if ($resin !== null) {
        $resin->delete();
    }

    $production->delete();
});

test('resin store uploads file to gcs', function () {
    mock(GoogleCloudStorageService::class, function ($mock): void {
        $mock->shouldReceive('uploadFile')
            ->once()
            ->andReturnUsing(function (UploadedFile $file, string $folder, string $filename): string {
                expect($folder)->toBe((string) config('gcs.folder', 'produksi'));

                return 'https://storage.googleapis.com/bucket/'.$folder.'/'.$filename;
            });
    });

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RFU'.Str::upper(Str::random(4)),
    ]);

    $file = UploadedFile::fake()->create('resin-file.pdf', 100, 'application/pdf');

    $this->post(route('resin.store'), [
        'trans_date' => '2026-08-24',
        'spk_id' => $production->row_id,
        'file' => $file,
        'stones' => [],
    ])->assertRedirect(route('resin.index'));

    $resin = Resin::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($resin)->not->toBeNull()
        ->and($resin?->file_upload)->not->toBeNull()
        ->and($resin?->file_upload)->toEndWith('.pdf');

    if ($resin !== null) {
        $resin->delete();
    }

    $production->delete();
});

test('resin edit page is accessible', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RED'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);

    $this->get(route('resin.edit', $resin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resin/edit')
            ->where('resinItem.id', $resin->row_id)
            ->where('resinItem.docNo', $resin->doc_no)
            ->has('shapeOptions')
        );

    $resin->delete();
    $production->delete();
});

test('resin update replaces stones', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RUP'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
        'status' => Resin::STATUS_OPEN,
    ]);

    $oldStone = ResinStone::factory()->create([
        'row_id' => $resin->row_id,
        'pcs' => 1,
        'carat' => 1,
        'size' => '1.00',
    ]);

    $this->put(route('resin.update', $resin), [
        'doc_no' => $resin->doc_no,
        'trans_date' => '2026-08-25',
        'spk_id' => $production->row_id,
        'stones' => [
            [
                'shape_id' => null,
                'pcs' => 5,
                'carat' => 8,
                'size' => '2.50',
            ],
        ],
    ])->assertRedirect(route('resin.index'));

    $resin->refresh();
    $oldStone->refresh();

    expect($resin->trans_date?->format('Y-m-d'))->toBe('2026-08-25')
        ->and($oldStone->is_deleted)->toBe(1);

    $newStone = ResinStone::query()
        ->notDeleted()
        ->where('row_id', $resin->row_id)
        ->first();

    expect($newStone)->not->toBeNull()
        ->and($newStone?->pcs)->toBe(5)
        ->and($newStone?->carat)->toBe(8);

    ResinStone::query()->where('row_id', $resin->row_id)->delete();
    $resin->delete();
    $production->delete();
});

test('resin destroy soft deletes document and stones', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RDL'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);

    $stone = ResinStone::factory()->create([
        'row_id' => $resin->row_id,
    ]);

    $this->delete(route('resin.destroy', $resin))
        ->assertRedirect(route('resin.index'));

    $resin->refresh();
    $stone->refresh();

    expect($resin->is_deleted)->toBe(1)
        ->and($stone->is_deleted)->toBe(1);

    $stone->delete();
    $resin->delete();
    $production->delete();
});
