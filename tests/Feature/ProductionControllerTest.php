<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Facades\DB;

test('spk index page is accessible and returns production list props', function () {
    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->has('productions.data')
            ->has('productions.total')
            ->has('filters.search')
            ->has('filters.per_page')
            ->has('filters.type')
            ->has('types')
        );
});

test('spk index page can filter productions by type', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_type', 'Stock')
        ->whereNotNull('spk_no')
        ->first();

    if ($production === null) {
        $production = app(SpkService::class)->createStock('system');
    }

    $types = SpkService::TYPES;

    $this->get(route('spk.index', ['type' => 'Stock', 'search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.type', 'Stock')
            ->where('types', $types)
            ->where('productions.data.0.tipeProduksi', 'Stock')
            ->where('productions.data.0.produksiNo', $production->spk_no)
        );
});

test('spk index page ignores unknown production type', function () {
    $this->get(route('spk.index', ['type' => 'Unknown']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.type', '')
        );
});

test('spk index page can filter productions by search query', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.search', $production->spk_no)
            ->has('productions.data.0')
            ->where('productions.data.0.produksiNo', $production->spk_no)
            ->has('productions.data.0.description')
            ->has('productions.data.0.estimatedDelivery')
            ->missing('productions.data.0.workEstimated')
        );
});

test('spk index description shows type sku description for new system', function () {
    $category = SkuPrefixCategory::query()->firstOrCreate([
        'category' => 'Ladies Ring',
        'prefix' => 'LDR',
    ], [
        'description' => null,
        'usage_count' => 0,
        'is_active' => 1,
    ]);

    $sku = SkuMaster::factory()->create([
        'sku_code' => '2T-LDR-ATF-REG',
        'item_original' => 'Ladies Ring',
        'category_prefix_id' => $category->id,
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/NEW-SYS',
        'spk_type' => 'Stock',
        'item_name' => 'Earring',
        'description' => 'LADIES RING',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'is_from_new_system' => 1,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.typeSkuLabel', 'LDR | 2T-LDR-ATF-REG')
            ->where('productions.data.0.itemDescription', 'LADIES RING')
            ->where('productions.data.0.skuAssigned', true)
        );

    $production->delete();
    $sku->delete();
});

test('spk index description shows type and description only for old system', function () {
    $category = SkuPrefixCategory::query()->firstOrCreate([
        'category' => 'Ladies Ring',
        'prefix' => 'LDR',
    ], [
        'description' => null,
        'usage_count' => 0,
        'is_active' => 1,
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/OLD-SYS',
        'spk_type' => 'Stock',
        'item_name' => 'Earring',
        'description' => 'LADIES RING',
        'category_prefix_id' => $category->id,
        'sku_id' => null,
        'is_from_new_system' => 0,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.typeSkuLabel', null)
            ->where('productions.data.0.itemDescription', 'LADIES RING')
            ->where('productions.data.0.skuAssigned', false)
        );

    $production->delete();
});

test('spk index marks new system rows without sku as unassigned', function () {
    $category = SkuPrefixCategory::query()->firstOrCreate([
        'category' => 'Ladies Ring',
        'prefix' => 'LDR',
    ], [
        'description' => null,
        'usage_count' => 0,
        'is_active' => 1,
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/NO-SKU',
        'spk_type' => 'Stock',
        'item_name' => 'Earring',
        'description' => 'LADIES RING',
        'category_prefix_id' => $category->id,
        'sku_id' => null,
        'is_from_new_system' => 1,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.typeSkuLabel', null)
            ->where('productions.data.0.itemDescription', 'LADIES RING')
            ->where('productions.data.0.skuAssigned', false)
        );

    $production->delete();
});

test('spk index page respects per page option', function () {
    $this->get(route('spk.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.per_page', 25)
            ->where('productions.per_page', 25)
        );
});

test('spk index page shows request order number with customer name for pesanan type', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Pesanan',
        'request_order_no' => 'DP-0009303',
        'customer_name' => 'Vera',
        'status' => '',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['type' => 'Pesanan', 'search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.tipeProduksi', 'Pesanan')
            ->where('productions.data.0.customer', "DP-0009303\n(Vera)")
        );

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.customer', 'Vera')
            ->where('production.requestOrderNo', 'DP-0009303')
        );

    $production->delete();
});

test('spk index page keeps customer name only for non pesanan type', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'request_order_no' => null,
        'customer_name' => 'James Wijaya',
        'status' => '',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['type' => 'Stock', 'search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.tipeProduksi', 'Stock')
            ->where('productions.data.0.customer', 'James Wijaya')
        );

    $production->delete();
});

test('spk index maps status to dashboard backlog labels', function (array $attributes, string $label) {
    $production = Production::factory()->create([
        ...$attributes,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', $label)
            ->where('productions.data.0.prosesTerakhir', $attributes['last_process'] ?? '')
            ->where('productions.data.0.prosesTerakhirDate', '')
        );

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.status', $production->status ?: '-')
        );

    $production->delete();
})->with([
    'manager approved without poles chrome is confirmed' => [
        [
            'status' => 'SPKDONE',
            'status_order' => 'NO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'Approved',
    ],
    'poles chrome in progress is not done' => [
        [
            'status' => 'SPKDONE',
            'status_order' => 'NO',
            'last_process' => 'Poles Chrome',
            'is_inprocess' => 0,
        ],
        'In Progress',
    ],
    'repeat order still waiting manager is draft' => [
        [
            'status' => 'SPK010',
            'status_order' => 'RO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'Draft',
    ],
    'in progress' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => 'Coran',
            'is_inprocess' => 0,
        ],
        'In Progress',
    ],
    'draft' => [
        [
            'status' => 'SPK010',
            'status_order' => 'NO',
            'last_process' => null,
            'is_inprocess' => 0,
        ],
        'Draft',
    ],
]);

test('spk index confirmed filter uses manager approval not repeat order', function () {
    $pendingRepeat = Production::factory()->create([
        'status' => 'SPK010',
        'status_order' => 'RO',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);
    $approved = Production::factory()->create([
        'status' => 'SPKDONE',
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', [
        'status' => 'Approved',
        'search' => $pendingRepeat->spk_no,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.total', 0)
        );

    $this->get(route('spk.index', [
        'status' => 'Approved',
        'search' => $approved->spk_no,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.produksiNo', $approved->spk_no)
            ->where('productions.data.0.status', 'Approved')
        );

    $pendingRepeat->delete();
    $approved->delete();
});

test('spk index marks status done when poles chrome is completed or handed to jb', function (string $processStatus) {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Chrome',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-PFG-'.$production->row_id,
        'process_name' => 'Poles Chrome',
        'spk_id' => $production->row_id,
        'status' => $processStatus,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'Done')
            ->where('productions.data.0.prosesTerakhir', 'Poles Chrome')
            ->where('productions.data.0.prosesTerakhirDate', now()->format('d-M-Y'))
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'poles bj completed' => ['PFGDONE'],
    'serahkan jb' => ['PFG040'],
]);

test('spk index marks status done when poles rangka is completed or handed to jb', function (string $processStatus) {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'TEST-PRK-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => $processStatus,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'Done')
            ->where('productions.data.0.prosesTerakhir', 'Poles Rangka')
        );

    DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('spk index marks status done when reference type poles barang jadi is rpfdone', function (string $spkType) {
    $production = Production::factory()->create([
        'spk_type' => $spkType,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-RPF-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'Done')
            ->where('productions.data.0.prosesTerakhir', 'Poles Barang Jadi')
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'exchange' => ['Exchange'],
    'refund' => ['Refund'],
    'reparasi' => ['Reparasi'],
]);

test('spk index marks status done when reference type finishing is rfhdone', function (string $spkType) {
    $production = Production::factory()->create([
        'spk_type' => $spkType,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('finishinghandmade')->insertGetId([
        'doc_no' => 'TEST-RFH-'.$production->row_id,
        'process_name' => 'Finishing',
        'spk_id' => $production->row_id,
        'status' => 'RFHDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'Done')
            ->where('productions.data.0.prosesTerakhir', 'Finishing')
        );

    DB::connection('third')->table('finishinghandmade')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'exchange' => ['Exchange'],
    'refund' => ['Refund'],
    'reparasi' => ['Reparasi'],
]);

test('spk index does not mark stock as done for rpfdone poles barang jadi', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-RPF-STOCK-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'In Progress')
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
});

test('spk index does not mark stock as done for rfhdone finishing', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('finishinghandmade')->insertGetId([
        'doc_no' => 'TEST-RFH-STOCK-'.$production->row_id,
        'process_name' => 'Finishing',
        'spk_id' => $production->row_id,
        'status' => 'RFHDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'In Progress')
        );

    DB::connection('third')->table('finishinghandmade')->where('row_id', $processId)->delete();
    $production->delete();
});

test('spk index includes last process date from the process table', function () {
    $processAt = now()->subDays(3)->startOfDay()->setTime(9, 30);
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Chrome',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-PFG-DATE-'.$production->row_id,
        'process_name' => 'Poles Chrome',
        'spk_id' => $production->row_id,
        'status' => 'PFG010',
        'is_deleted' => 0,
        'created_date' => $processAt,
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'In Progress')
            ->where('productions.data.0.prosesTerakhir', 'Poles Chrome')
            ->where('productions.data.0.prosesTerakhirDate', $processAt->format('d-M-Y'))
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $production->delete();
});

test('spk show page displays production detail', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.id', (string) $production->row_id)
            ->where('production.produksiNo', $production->spk_no)
            ->has('production.customer')
            ->has('production.item')
            ->has('production.status')
            ->has('item.name')
            ->has('item.qty')
            ->has('item.diameter')
            ->has('item.dimensi')
            ->has('item.ringSize')
            ->has('item.diameterLengthRingSize')
            ->has('item.goldWeight')
            ->has('item.masterGoldWeight')
            ->has('item.goldColor')
            ->has('item.jwcad3d')
            ->has('item.description')
            ->has('item.imageUrl')
            ->has('item.finishingType')
            ->has('stones')
            ->has('navigation.position')
            ->has('navigation.total')
            ->has('navigation.previousUrl')
            ->has('navigation.nextUrl')
            ->has('navigation.backUrl')
            ->where('detailUrl', route('spk.show', $production, absolute: true))
        );
});

test('status card route opens first spk for selected status', function () {
    $draft = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(86000, 86999)),
        'status' => '',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);
    $pending = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(87000, 87999)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.show-status', 'pendingManager'))
        ->assertRedirect(route('spk.show', [
            'production' => $pending->spk_no,
            'status' => 'pendingManager',
        ]));

    $draft->delete();
    $pending->delete();
});

test('spk show navigation is scoped to selected status', function () {
    $oldPending = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(88000, 88999)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);
    $currentPending = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(89000, 89999)),
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);
    $draft = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(90000, 90999)),
        'status' => '',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.show', [
        'production' => $currentPending->spk_no,
        'status' => 'pendingManager',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('navigation.total', 2)
            ->where('navigation.previousUrl', route('spk.show', [
                'production' => $oldPending->spk_no,
                'status' => 'pendingManager',
            ]))
            ->where('navigation.nextUrl', null)
            ->where('navigation.backUrl', route('spk.index', [
                'status' => 'Menunggu Approval',
            ]))
        );

    $oldPending->delete();
    $currentPending->delete();
    $draft->delete();
});

test('spk show provides absolute detail url for qr code modal', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $detailUrl = route('spk.show', $production, absolute: true);

    expect($detailUrl)->toStartWith('http');

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('detailUrl', $detailUrl)
            ->where('production.produksiNo', $production->spk_no)
        );
});

test('spk show page uses spk file_name for item image', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'design_image' => '1782887215_design_show.jpg',
        'image_url' => 'https://example.com/old-image.jpg',
    ]);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'file_name' => 'uploaded-spk.png',
        'sku_id' => $sku->id,
        'category_prefix_id' => $category->id,
    ]);

    $expectedUrl = rtrim((string) config('spk.production_image_base_url'), '/').'/uploaded-spk.png';

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.imageUrl', $expectedUrl)
        );

    $production->delete();
    $sku->delete();
});

test('spk show returns null image url when spk file name is empty', function () {
    $production = app(SpkService::class)->createStock('system');
    $production->update(['file_name' => 'uploaded-spk.png', 'sku_id' => null]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.imageUrl', null)
        );

    $production->delete();
});

test('spk show page maps status order labels', function (string $code, string $label) {
    $production = Production::query()
        ->notDeleted()
        ->whereNotNull('spk_no')
        ->where('status_order', $code)
        ->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.statusOrder', $label)
            ->has('item.statusOrderLabel')
        );
})->with([
    'repeat order' => ['RO', 'Repeat Order'],
    'new order' => ['NO', 'New Order'],
]);

test('spk show page returns not found for deleted production', function () {
    $production = Production::query()->where('is_deleted', 1)->first();

    if (! $production) {
        $production = Production::query()->notDeleted()->first();

        expect($production)->not->toBeNull();

        $production->forceFill(['is_deleted' => 1])->save();
    }

    $this->get(route('spk.show', $production))->assertNotFound();
});
