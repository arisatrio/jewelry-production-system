<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Support\InventoryPluSkuLookup;
use Illuminate\Support\Facades\DB;

test('inventory barang jadi index page is accessible', function () {
    $this->get(route('inventory.barang-jadi.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/barang-jadi/index')
            ->has('items.data')
            ->has('items.total')
            ->where('filters.search', '')
            ->where('filters.per_page', 50)
            ->where('filters.status', [])
            ->has('filterOptions.status')
            ->has('filterOptions.per_page')
            ->has('pluSummary.belumDisetPlu')
            ->has('pluSummary.sudahDisetPlu')
            ->where('filters.plu_status', null)
        );
});

test('inventory barang jadi lists done rangka spk', function (string $processStatus) {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'plu' => 'PLU-TEST-'.fake()->numerify('####'),
    ]);

    $processId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'INV-BJ-PRK-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => $processStatus,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    try {
        $this->get(route('inventory.barang-jadi.index', ['search' => $production->spk_no]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.spkNo', (string) $production->spk_no)
                ->where('items.data.0.plu', $production->plu)
            );
    } finally {
        DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
        $production->delete();
    }
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('inventory barang jadi resolves sku from plu via store inventory', function (string $processStatus) {
    $lookup = app(InventoryPluSkuLookup::class);

    if (! $lookup->tableAvailable() || ! $lookup->hasColumn('sku_id')) {
        expect(true)->toBeTrue();

        return;
    }

    $pluColumn = $lookup->hasColumn('plu') ? 'plu' : (
        $lookup->hasColumn('identity_code') ? 'identity_code' : null
    );

    if ($pluColumn === null) {
        expect(true)->toBeTrue();

        return;
    }

    $plu = 'PLU-BJ-SKU-'.fake()->numerify('######');
    $sku = SkuMaster::factory()->create();
    $inventoryRowId = null;

    $insert = [
        $pluColumn => $plu,
        'sku_id' => $sku->id,
    ];

    if ($lookup->hasColumn('is_deleted')) {
        $insert['is_deleted'] = 0;
    }

    if ($lookup->hasColumn('company_id')) {
        $insert['company_id'] = 1;
    }

    foreach (['calc_price' => 0, 'production_cost' => 0, 'markup' => 0] as $column => $value) {
        if ($lookup->hasColumn($column)) {
            $insert[$column] = $value;
        }
    }

    $inventoryRowId = DB::connection('second')->table('inventory')->insertGetId($insert);

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'plu' => $plu,
    ]);

    $processId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'INV-BJ-SKU-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => $processStatus,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    try {
        $this->get(route('inventory.barang-jadi.index', ['search' => $production->spk_no]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.plu', $plu)
                ->where('items.data.0.skuFromPlu.skuAssigned', true)
                ->where('items.data.0.skuFromPlu.typeSkuLabel', fn (mixed $value): bool => is_string($value) && str_contains($value, $sku->sku_code))
            );
    } finally {
        DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
        $production->delete();
        DB::connection('second')->table('inventory')->where('row_id', $inventoryRowId)->delete();
        $sku->delete();
    }
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('inventory barang jadi plu summary counts set vs unset plu', function (string $processStatus) {
    $withPlu = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'plu' => 'PLU-COUNT-'.fake()->numerify('####'),
    ]);

    $withoutPlu = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'plu' => null,
    ]);

    $processIds = [];

    foreach ([$withPlu, $withoutPlu] as $production) {
        $processIds[] = DB::connection('third')->table('polishframe')->insertGetId([
            'doc_no' => 'INV-BJ-PLU-COUNT-'.$production->row_id,
            'spk_id' => $production->row_id,
            'status' => $processStatus,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
        ], 'row_id');
    }

    try {
        $this->get(route('inventory.barang-jadi.index', ['search' => 'PLU-COUNT']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pluSummary.sudahDisetPlu', fn (int $count): bool => $count >= 1)
                ->where('pluSummary.belumDisetPlu', fn (int $count): bool => $count >= 1)
            );

        $this->get(route('inventory.barang-jadi.index', [
            'search' => (string) $withoutPlu->spk_no,
            'plu_status' => 'belum',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.plu_status', 'belum')
                ->where('items.data.0.plu', null)
            );
    } finally {
        DB::connection('third')->table('polishframe')->whereIn('row_id', $processIds)->delete();
        $withPlu->delete();
        $withoutPlu->delete();
    }
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('inventory barang jadi shows keterangan when spk sku differs from plu inventory sku', function (string $processStatus) {
    $lookup = app(InventoryPluSkuLookup::class);

    if (! $lookup->tableAvailable() || ! $lookup->hasColumn('sku_id')) {
        expect(true)->toBeTrue();

        return;
    }

    $pluColumn = $lookup->hasColumn('plu') ? 'plu' : (
        $lookup->hasColumn('identity_code') ? 'identity_code' : null
    );

    if ($pluColumn === null) {
        expect(true)->toBeTrue();

        return;
    }

    $plu = 'PLU-MISMATCH-'.fake()->numerify('######');
    $spkSku = SkuMaster::factory()->create();
    $inventorySku = SkuMaster::factory()->create();
    $inventoryRowId = null;

    $insert = [
        $pluColumn => $plu,
        'sku_id' => $inventorySku->id,
    ];

    if ($lookup->hasColumn('is_deleted')) {
        $insert['is_deleted'] = 0;
    }

    if ($lookup->hasColumn('company_id')) {
        $insert['company_id'] = 1;
    }

    foreach (['calc_price' => 0, 'production_cost' => 0, 'markup' => 0] as $column => $value) {
        if ($lookup->hasColumn($column)) {
            $insert[$column] = $value;
        }
    }

    $inventoryRowId = DB::connection('second')->table('inventory')->insertGetId($insert);

    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
        'plu' => $plu,
        'sku_id' => $spkSku->id,
    ]);

    $processId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'INV-BJ-MISMATCH-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => $processStatus,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    try {
        $this->get(route('inventory.barang-jadi.index', ['search' => $production->spk_no]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.keterangan', 'SKU Tidak Sama')
            );
    } finally {
        DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
        $production->delete();
        DB::connection('second')->table('inventory')->where('row_id', $inventoryRowId)->delete();
        $spkSku->delete();
        $inventorySku->delete();
    }
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('inventory barang jadi filters by done rangka status', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'INV-BJ-FILTER-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => 'PRKDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    try {
        $this->get(route('inventory.barang-jadi.index', [
            'search' => $production->spk_no,
            'status' => ['DONE (Rangka)'],
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', ['DONE (Rangka)'])
                ->where('items.data.0.spkNo', (string) $production->spk_no)
            );
    } finally {
        DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
        $production->delete();
    }
});
