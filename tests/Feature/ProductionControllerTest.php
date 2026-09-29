<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaSpk;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkService;
use App\Support\StoreOrderRequestRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

test('spk index search matches sku code and sku name', function () {
    $sku = SkuMaster::factory()->create([
        'sku_code' => 'TEST-SRCH-SKU-9X7Q',
        'item_original' => 'Cincin Uji Pencarian Zeta',
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/SKU-SEARCH',
        'item_name' => 'Earring',
        'description' => 'Tanpa kata kunci',
        'sku_id' => $sku->id,
        'is_deleted' => 0,
    ]);

    try {
        foreach (['srch-sku-9x7', 'Uji Pencarian Zeta'] as $keyword) {
            $this->get(route('spk.index', ['search' => $keyword]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('spk/index')
                    ->where('productions.total', 1)
                    ->where('productions.data.0.produksiNo', 'TEST/SPK/SKU-SEARCH')
                );
        }
    } finally {
        $production->delete();
        $sku->delete();
    }
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

test('spk index exposes default sort filter options and bulk actions', function () {
    $this->get(route('spk.index', ['per_page' => 999, 'sort' => 'unknown', 'direction' => 'sideways', 'date_from' => 'bukan-tanggal']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.per_page', 50)
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->has('filterOptions.sort', 5)
            ->has('filterOptions.direction', 2)
            ->has('filterOptions.per_page', 4)
            ->has('receiptEmployeeOptions')
            ->where('bulkActions.canApprove', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canDelete', true)
            ->has('bulkActions.canSubmit')
            ->has('statusCounts.draft')
            ->has('statusCounts.done')
            ->where('statuses', fn ($statuses) => collect($statuses)->contains('Done'))
        );
});

test('spk index receipt employee options list active employees from all departments', function () {
    $headOffice = Employee::factory()->create(['department_id' => 1]);
    $inactive = Employee::factory()->create(['status' => 'inactive']);
    $deleted = Employee::factory()->create(['is_deleted' => 1]);

    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receiptEmployeeOptions', fn ($options) => collect($options)->contains($headOffice->nama_lengkap)
                && ! collect($options)->contains($inactive->nama_lengkap)
                && ! collect($options)->contains($deleted->nama_lengkap))
        );

    collect([$headOffice, $inactive, $deleted])->each->delete();
});

test('spk index done filter lists spk with completed production', function () {
    $customer = 'Filter Done '.strtoupper(fake()->unique()->lexify('??????'));
    $done = Production::factory()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Chrome',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);
    $approved = Production::factory()->managerApproved()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);

    $processId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-PFG-DONE-'.$done->row_id,
        'process_name' => 'Poles Chrome',
        'spk_id' => $done->row_id,
        'status' => 'PFGDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['status' => 'Done', 'search' => $customer]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.status', 'Done')
            ->where('productions.total', 1)
            ->where('productions.data.0.produksiNo', $done->spk_no)
            ->where('productions.data.0.status', 'DONE (Barang Jadi)')
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $processId)->delete();
    $done->delete();
    $approved->delete();
});

test('spk status list returns paginated spk rows for the status modal', function () {
    $customer = 'Modal Status '.strtoupper(fake()->unique()->lexify('??????'));
    $inProgress = Production::factory()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'status' => 'SPKDONE',
        'last_process' => 'Coran',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);
    $approved = Production::factory()->managerApproved()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);

    $this->getJson(route('spk.status-list', [
        'statusKey' => 'inProgress',
        'search' => $customer,
    ]))
        ->assertOk()
        ->assertJsonPath('label', 'In Progress')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 25)
        ->assertJsonPath('data.0.rowId', (int) $inProgress->row_id)
        ->assertJsonPath('data.0.produksiNo', $inProgress->spk_no)
        ->assertJsonPath('data.0.status', 'In Progress');

    $inProgress->delete();
    $approved->delete();
});

test('spk status list returns 404 for unknown status', function () {
    $this->getJson(route('spk.status-list', ['statusKey' => 'unknown']))
        ->assertNotFound();
});

test('spk process queue list matches module spk queue', function (string $module, string $selectRoute, string $queue) {
    $expectedIds = collect(
        $this->getJson(route($selectRoute, ['queue' => $queue, 'limit' => 25]))
            ->assertOk()
            ->json('data'),
    )->pluck('rowId')->all();

    $response = $this->getJson(route('spk.process-queue', ['module' => $module, 'queue' => $queue]))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
        ]);

    $rows = collect($response->json('data'));

    expect($rows->pluck('rowId')->all())->toBe($expectedIds);

    if ($rows->isNotEmpty()) {
        expect($rows->first())->toHaveKeys(['produksiNo', 'status', 'paymentStatus', 'documentId', 'documentNo']);
    }
})->with([
    'jewelcad' => ['jewelcad', 'jewelcad.select.spks'],
    'resin' => ['resin', 'resin.select.spks'],
    'coran' => ['coran', 'coran.select.spks'],
    'finishing' => ['finishing', 'finishing.select.spks'],
    'poles rangka' => ['poles-rangka', 'poles-rangka.select.spks'],
    'pasang batu' => ['pasang-batu', 'pasang-batu.select.spks'],
    'poles chrome' => ['poles-chrome', 'poles-chrome.select.spks'],
])->with(['pending', 'inProgress', 'completed']);

test('spk process queue list includes module document reference for in progress queue', function () {
    $row = collect(
        $this->getJson(route('spk.process-queue', ['module' => 'finishing', 'queue' => 'inProgress']))
            ->assertOk()
            ->json('data'),
    )->first(fn (array $row): bool => $row['documentNo'] !== null);

    if ($row === null) {
        $this->markTestSkipped('Tidak ada SPK finishing yang sedang proses.');
    }

    expect($row['documentId'])->toBeInt()->toBeGreaterThan(0);
});

test('spk process queue list rejects unknown module or queue', function () {
    $this->getJson('/spk/process-queue/unknown/pending')->assertNotFound();
    $this->getJson('/spk/process-queue/finishing/unknown')->assertNotFound();
});

test('spk index filters by created date range and sorts by spk number', function () {
    $customer = 'Filter Tanggal '.strtoupper(fake()->unique()->lexify('??????'));
    $inRangeA = Production::factory()->create([
        'spk_no' => 'TEST/SPK/SORT-B',
        'customer_name' => $customer,
        'created_date' => '2026-03-10 09:00:00',
        'is_deleted' => 0,
    ]);
    $inRangeB = Production::factory()->create([
        'spk_no' => 'TEST/SPK/SORT-A',
        'customer_name' => $customer,
        'created_date' => '2026-03-12 09:00:00',
        'is_deleted' => 0,
    ]);
    $outOfRange = Production::factory()->create([
        'spk_no' => 'TEST/SPK/SORT-C',
        'customer_name' => $customer,
        'created_date' => '2026-04-01 09:00:00',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', [
        'search' => $customer,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'sort' => 'spk',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.date_from', '2026-03-01')
            ->where('filters.date_to', '2026-03-31')
            ->where('filters.sort', 'spk')
            ->where('filters.direction', 'asc')
            ->where('productions.total', 2)
            ->where('productions.data.0.produksiNo', 'TEST/SPK/SORT-A')
            ->where('productions.data.1.produksiNo', 'TEST/SPK/SORT-B')
        );

    $inRangeA->delete();
    $inRangeB->delete();
    $outOfRange->delete();
});

test('spk index filters by target selesai date range', function () {
    $customer = 'Filter Target '.strtoupper(fake()->unique()->lexify('??????'));
    $inRange = Production::factory()->create([
        'spk_no' => 'TEST/SPK/TARGET-IN',
        'customer_name' => $customer,
        'estimated_delivery_time' => '2026-05-15 00:00:00',
        'is_deleted' => 0,
    ]);
    $beforeRange = Production::factory()->create([
        'spk_no' => 'TEST/SPK/TARGET-BEFORE',
        'customer_name' => $customer,
        'estimated_delivery_time' => '2026-04-30 00:00:00',
        'is_deleted' => 0,
    ]);
    $afterRange = Production::factory()->create([
        'spk_no' => 'TEST/SPK/TARGET-AFTER',
        'customer_name' => $customer,
        'estimated_delivery_time' => '2026-06-01 00:00:00',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', [
        'search' => $customer,
        'target_from' => '2026-05-01',
        'target_to' => '2026-05-31',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('filters.target_period', 'custom')
            ->where('filters.target_from', '2026-05-01')
            ->where('filters.target_to', '2026-05-31')
            ->where('productions.total', 1)
            ->where('productions.data.0.produksiNo', 'TEST/SPK/TARGET-IN')
        );

    $this->get(route('spk.index', ['target_from' => 'bukan-tanggal']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.target_period', '')
            ->where('filters.target_from', null)
            ->where('filters.target_to', null)
            ->has('filterOptions.target_period', 8)
        );

    $inRange->delete();
    $beforeRange->delete();
    $afterRange->delete();
});

test('spk index filters by target selesai period preset', function (string $period, array $expectedSpkNos) {
    $this->travelTo('2026-09-30 10:00:00');

    $customer = 'Filter Periode '.strtoupper(fake()->unique()->lexify('??????'));
    $targets = [
        'TEST/SPK/PERIOD-PAST' => '2026-09-20',
        'TEST/SPK/PERIOD-TODAY' => '2026-09-30',
        'TEST/SPK/PERIOD-THIS-WEEK' => '2026-10-04',
        'TEST/SPK/PERIOD-NEXT-WEEK' => '2026-10-07',
        'TEST/SPK/PERIOD-NEXT-MONTH' => '2026-10-20',
    ];
    $productions = collect($targets)->map(fn (string $date, string $spkNo) => Production::factory()->create([
        'spk_no' => $spkNo,
        'customer_name' => $customer,
        'estimated_delivery_time' => "{$date} 00:00:00",
        'is_deleted' => 0,
    ]));

    $this->get(route('spk.index', [
        'search' => $customer,
        'target_period' => $period,
        'target_from' => '2020-01-01',
        'sort' => 'estimated',
        'direction' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.target_period', $period)
            ->where('filters.target_from', null)
            ->where('filters.target_to', null)
            ->where('productions.total', count($expectedSpkNos))
            ->where(
                'productions.data',
                fn ($rows) => collect($rows)->pluck('produksiNo')->values()->all() === $expectedSpkNos,
            )
        );

    $productions->each->delete();
})->with([
    'lewat target' => ['overdue', ['TEST/SPK/PERIOD-PAST']],
    'hari ini' => ['today', ['TEST/SPK/PERIOD-TODAY']],
    '7 hari ke depan' => ['next_7_days', ['TEST/SPK/PERIOD-TODAY', 'TEST/SPK/PERIOD-THIS-WEEK', 'TEST/SPK/PERIOD-NEXT-WEEK']],
    'minggu ini' => ['this_week', ['TEST/SPK/PERIOD-TODAY', 'TEST/SPK/PERIOD-THIS-WEEK']],
    'minggu depan' => ['next_week', ['TEST/SPK/PERIOD-NEXT-WEEK']],
    'bulan ini' => ['this_month', ['TEST/SPK/PERIOD-PAST', 'TEST/SPK/PERIOD-TODAY']],
    'bulan depan' => ['next_month', ['TEST/SPK/PERIOD-THIS-WEEK', 'TEST/SPK/PERIOD-NEXT-WEEK', 'TEST/SPK/PERIOD-NEXT-MONTH']],
]);

test('spk index lists item column data from spk', function () {
    $category = SkuPrefixCategory::query()->firstOrCreate([
        'category' => 'Ladies Ring',
        'prefix' => 'LDR',
    ], [
        'description' => null,
        'usage_count' => 0,
        'is_active' => 1,
    ]);

    $sku = SkuMaster::factory()->create([
        'sku_code' => '2T-LDR-IDX-ITEM',
        'item_original' => 'Ladies Ring Index',
        'category_prefix_id' => $category->id,
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/IDX-ITEM',
        'spk_type' => 'Stock',
        'customer_name' => 'Nadia',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'file_name' => 'spk-index-item.png',
        'created_by' => 'Genza',
        'is_from_new_system' => 1,
        'is_deleted' => 0,
    ]);

    $expectedUrl = rtrim((string) config('spk.production_image_base_url'), '/').'/spk-index-item.png';

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.rowId', (int) $production->row_id)
            ->where('productions.data.0.skuCode', '2T-LDR-IDX-ITEM')
            ->where('productions.data.0.typeCode', 'LDR')
            ->where('productions.data.0.productItemName', 'Ladies Ring Index')
            ->where('productions.data.0.spkImageUrl', $expectedUrl)
            ->where('productions.data.0.orderReference', null)
            ->where('productions.data.0.paymentStatus', null)
            ->where('productions.data.0.customer', 'Nadia')
            ->where('productions.data.0.createdBy', 'Genza')
        );

    $production->delete();
    $sku->delete();
});

test('spk index returns days left until target completion date', function (int $offsetDays) {
    $production = Production::factory()->create([
        'estimated_delivery_time' => now()->addDays($offsetDays)->format('Y-m-d'),
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.targetDaysLeft', $offsetDays)
        );

    $production->delete();
})->with([
    'future' => [2],
    'today' => [0],
    'overdue' => [-3],
]);

test('spk bulk status sends draft spk to production and approves pending spk', function () {
    $draft = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => '',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);
    $pending = Production::factory()->create([
        'spk_type' => 'Stock',
        'request_order_no' => null,
        'status' => 'SPK010',
        'last_process' => null,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ]);

    $this->post(route('spk.bulk-status'), [
        'ids' => [$draft->row_id, $pending->row_id],
        'action' => 'approve',
    ])->assertRedirect();

    expect($draft->refresh()->status)->toBe('SPK010')
        ->and($pending->refresh()->status)->toBe('SPK010');

    $this->post(route('spk.bulk-status'), [
        'ids' => [$pending->row_id],
        'action' => 'manager_approve',
    ])->assertRedirect();

    expect($pending->refresh()->status)->toBe('SPKDONE');

    DB::connection('third')->table('sysapproval')
        ->where('doc_name', 'spk')
        ->whereIn('doc_id', [$draft->row_id, $pending->row_id])
        ->delete();
    $draft->delete();
    $pending->delete();
});

test('spk bulk status deletes unapproved spk and skips approved spk', function () {
    $draft = Production::factory()->create([
        'status' => '',
        'is_deleted' => 0,
    ]);
    $approved = Production::factory()->managerApproved()->create([
        'is_deleted' => 0,
    ]);

    $this->post(route('spk.bulk-status'), [
        'ids' => [$draft->row_id, $approved->row_id],
        'action' => 'delete',
    ])->assertRedirect();

    expect((int) $draft->refresh()->is_deleted)->toBe(1)
        ->and((int) $approved->refresh()->is_deleted)->toBe(0);

    $draft->delete();
    $approved->delete();
});

test('spk bulk status validates action and ids', function () {
    $this->post(route('spk.bulk-status'), [
        'ids' => [],
        'action' => 'complete',
    ])->assertSessionHasErrors(['ids', 'action']);
});

test('spk index page shows request order number with customer name and payment status for pesanan type', function () {
    $docNo = 'DP-TEST-'.strtoupper(fake()->unique()->bothify('????????'));

    $orderId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => 'CUSTOM',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => 1,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $production = Production::factory()->create([
        'spk_type' => 'Pesanan',
        'request_order_no' => $docNo,
        'customer_name' => 'Vera',
        'status' => '',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['type' => 'Pesanan', 'search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.tipeProduksi', 'Pesanan')
            ->where('productions.data.0.customer', "{$docNo} (Vera) (Lunas)")
            ->where('productions.data.0.orderReference', "{$docNo} (Vera)")
            ->where('productions.data.0.paymentStatus', 'Lunas')
        );

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.customer', 'Vera')
            ->where('production.requestOrderNo', $docNo)
            ->where('production.requestOrderLabel', "{$docNo} (Vera) (Lunas)")
        );

    $production->delete();
    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
});

test('spk index page marks pesanan as belum lunas when request order is not fully paid', function () {
    $docNo = 'DP-TEST-'.strtoupper(fake()->unique()->bothify('????????'));

    $orderId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => 'CUSTOM',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $production = Production::factory()->create([
        'spk_type' => 'Pesanan',
        'request_order_no' => $docNo,
        'customer_name' => 'Rina',
        'status' => '',
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['type' => 'Pesanan', 'search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.orderReference', "{$docNo} (Rina)")
            ->where('productions.data.0.paymentStatus', 'Belum Lunas')
        );

    $production->delete();
    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
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
            ->where('productions.data.0.status', 'DONE (Barang Jadi)')
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
            ->where('productions.data.0.status', 'DONE (Rangka)')
            ->where('productions.data.0.prosesTerakhir', 'Poles Rangka')
        );

    DB::connection('third')->table('polishframe')->where('row_id', $processId)->delete();
    $production->delete();
})->with([
    'poles rangka completed' => ['PRKDONE'],
    'serahkan jb poles rangka' => ['PRK040'],
]);

test('spk index keeps in progress when poles rangka done but pasang batu or poles chrome exists', function () {
    $production = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPK010',
        'status_order' => 'NO',
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
        'is_deleted' => 0,
    ]);

    $rangkaId = DB::connection('third')->table('polishframe')->insertGetId([
        'doc_no' => 'TEST-PRK-GAP-'.$production->row_id,
        'spk_id' => $production->row_id,
        'status' => 'PRKDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $chromeId = DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-PFG-GAP-'.$production->row_id,
        'process_name' => 'Poles Chrome',
        'spk_id' => $production->row_id,
        'status' => 'OPEN',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.status', 'In Progress')
            ->where('productions.data.0.prosesTerakhir', 'Poles Rangka')
        );

    DB::connection('third')->table('polishfinishedgood')->where('row_id', $chromeId)->delete();
    DB::connection('third')->table('polishframe')->where('row_id', $rangkaId)->delete();
    $production->delete();
});

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
            ->where('productions.data.0.status', 'DONE (Barang Jadi)')
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
            ->where('productions.data.0.status', 'DONE (Barang Jadi)')
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

test('spk index shows handover as last process when spk has receipt and no production process', function () {
    $handedOver = Production::factory()->create([
        'spk_type' => 'Stock',
        'last_process' => null,
        'is_deleted' => 0,
    ]);
    $inProcess = Production::factory()->create([
        'spk_type' => 'Stock',
        'last_process' => 'Coran',
        'is_deleted' => 0,
    ]);
    $deletedReceiptSpk = Production::factory()->create([
        'spk_type' => 'Stock',
        'last_process' => null,
        'is_deleted' => 0,
    ]);

    $olderReceipt = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000031',
        'tanggal' => '2099-09-01',
        'untuk' => 'Store',
        'spk_row_ids' => [(int) $handedOver->row_id],
    ]);
    $latestReceipt = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000032',
        'tanggal' => '2099-09-30',
        'untuk' => 'Workshop',
        'spk_row_ids' => [(int) $handedOver->row_id, (int) $inProcess->row_id],
    ]);
    $deletedReceipt = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000033',
        'spk_row_ids' => [(int) $deletedReceiptSpk->row_id],
    ]);
    $deletedReceipt->delete();

    $lastProcessFor = function (Production $production): array {
        $row = collect($this->get(route('spk.index', ['search' => $production->spk_no]))
            ->assertOk()
            ->viewData('page')['props']['productions']['data'])
            ->firstWhere('rowId', (int) $production->row_id);

        return [$row['prosesTerakhir'], $row['prosesTerakhirDate']];
    };

    expect($lastProcessFor($handedOver))->toBe(['Diserahkan ke Workshop', '30-Sep-2099'])
        ->and($lastProcessFor($inProcess)[0])->toBe('Coran')
        ->and($lastProcessFor($deletedReceiptSpk))->toBe(['', '']);

    SerahTerimaSpk::query()->withTrashed()
        ->whereKey([$olderReceipt->id, $latestReceipt->id, $deletedReceipt->id])
        ->forceDelete();
    collect([$handedOver, $inProcess, $deletedReceiptSpk])->each->delete();
});

test('spk store stock requests proxies approved requests from store api', function () {
    $this->travelTo('2026-10-20 10:00:00');
    config([
        'services.store_api.base_url' => 'https://store.test/api/public',
        'services.store_api.key' => 'test-store-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'store.test/api/public/request/stock/list/approved' => Http::response([
            'data' => [[
                'row_id' => 33,
                'doc_no' => 'RS-0000033',
                'trans_date' => '2026-09-23',
                'estimated_date' => '2026-10-23',
                'type_order' => 'REQUEST STOCK',
                'status' => 'APPROVED',
                'status_info' => 'APPROVED 2/2',
                'nama_item' => 'EAR ELECTA OVAL 0.3 RG',
                'ref_sku' => 'RG-HOP-ETA-8BT-OVL-DMD-DS-030',
                'warna_emas' => 'ROSE GOLD',
                'kadar_emas' => 750,
                'berat_emas' => '4.64',
                'notes' => null,
                'photo_file' => 'https://storage.test/sku.png',
                'created_by' => 'Annisa Fitrie',
                'store' => ['row_id' => 2, 'name' => 'Plaza Indonesia'],
                'approvals' => [
                    ['sequence' => 2, 'status' => 'APPROVED', 'is_deleted' => 0, 'approval_date' => '2026-09-24 08:59:59', 'approver' => ['id' => 31, 'name' => 'Brandon']],
                    ['sequence' => 1, 'status' => 'APPROVED', 'is_deleted' => 0, 'approval_date' => '2026-09-24 08:30:00', 'approver' => ['id' => 12, 'name' => 'Supervisor Store']],
                ],
            ], [
                'row_id' => 32,
                'doc_no' => 'RS-0000032',
                'status' => 'APPROVED',
                'status_info' => 'APPROVED 2/2',
                'approvals' => [],
            ]],
            'meta' => ['current_page' => 2, 'last_page' => 3, 'per_page' => 25, 'total' => 51],
            'message' => 'Data request stock berhasil diambil',
            'code' => 200,
        ]),
    ]);

    $this->getJson(route('spk.store-stock-requests', ['search' => 'RS-0000033', 'page' => 2]))
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'rowId' => 33,
                'docNo' => 'RS-0000033',
                'transDate' => '23-Sep-2026',
                'estimatedDate' => '23-Oct-2026',
                'targetDaysLeft' => 3,
                'store' => 'Plaza Indonesia',
                'item' => 'EAR ELECTA OVAL 0.3 RG',
                'refSku' => 'RG-HOP-ETA-8BT-OVL-DMD-DS-030',
                'typeOrder' => 'REQUEST STOCK',
                'status' => 'Approved by Brandon',
                'approvedAt' => '24-Sep-2026 08:59',
                'notes' => null,
                'imageUrl' => 'https://storage.test/sku.png',
                'goldInfo' => 'ROSE GOLD · 750 · 4.64 gr',
                'createdBy' => 'Annisa Fitrie',
            ], [
                'rowId' => 32,
                'docNo' => 'RS-0000032',
                'transDate' => '-',
                'estimatedDate' => '-',
                'targetDaysLeft' => null,
                'store' => '-',
                'item' => '-',
                'refSku' => null,
                'typeOrder' => null,
                'status' => 'APPROVED 2/2',
                'approvedAt' => null,
                'notes' => null,
                'imageUrl' => null,
                'goldInfo' => null,
                'createdBy' => null,
            ]],
            'meta' => ['currentPage' => 2, 'lastPage' => 3, 'perPage' => 25, 'total' => 51],
        ]);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://store.test/api/public/request/stock/list/approved'
        && $request->hasHeader('X-API-KEY', 'test-store-key')
        && $request->data() === [
            'page' => 2,
            'per_page' => 25,
            'search' => 'RS-0000033',
            'sort_by' => 'created_date',
            'sort_order' => 'desc',
        ]);
});

test('spk store stock requests returns bad gateway when store api fails', function () {
    config([
        'services.store_api.base_url' => 'https://store.test/api/public',
        'services.store_api.key' => 'test-store-key',
    ]);
    Http::preventStrayRequests();
    Http::fake(['store.test/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $this->getJson(route('spk.store-stock-requests'))
        ->assertStatus(502)
        ->assertJsonPath('message', 'Gagal mengambil data request stok dari Store. Silakan coba lagi.');
});

test('spk index defers store stock request count from store api', function () {
    config([
        'services.store_api.base_url' => 'https://store.test/api/public',
        'services.store_api.key' => 'test-store-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'store.test/api/public/request/stock/list/approved' => Http::response([
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 11, 'per_page' => 1, 'total' => 11],
        ]),
    ]);

    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->missing('storeStockRequestCount')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('storeStockRequestCount', 11)
            )
        );

    Http::assertSentCount(1);
});

test('spk index store stock request count is null when store api key is missing', function () {
    config(['services.store_api.key' => null]);
    Http::preventStrayRequests();

    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('storeStockRequestCount', null)
            )
        );
});

test('spk store order requests lists store orders per spk tab sorted by newest date', function () {
    $this->travelTo('2026-10-20 10:00:00');

    $docNos = ['TEST-SOR-0001', 'TEST-SOR-0002', 'TEST-SOR-0003', 'TEST-SOR-0004', 'TEST-SOR-0005'];
    $baseRow = [
        'company_id' => 1,
        'store_id' => 0,
        'customer_id' => 0,
        'online_offline' => 'OFFLINE',
        'is_deleted' => 0,
        'created_by' => 'Annisa Fitrie',
        'estimated_date' => null,
        'nama_item' => null,
        'kadar_emas' => null,
        'notes' => null,
        'is_fully_paid' => null,
    ];

    DB::connection('second')->table('request_order')->whereIn('doc_no', $docNos)->delete();
    DB::connection('second')->table('request_order')->insert([
        [...$baseRow, 'doc_no' => 'TEST-SOR-0001', 'status' => 'ORDER', 'type_order' => 'DP PO', 'trans_date' => '2026-10-01', 'estimated_date' => '2026-10-23', 'nama_item' => 'Cincin Maryam', 'kadar_emas' => 750, 'notes' => 'Size 12', 'is_fully_paid' => 0],
        [...$baseRow, 'doc_no' => 'TEST-SOR-0002', 'status' => 'ORDER', 'type_order' => 'REPARASI', 'trans_date' => '2026-10-01'],
        [...$baseRow, 'doc_no' => 'TEST-SOR-0003', 'status' => 'ON GOING', 'type_order' => 'CUSTOM', 'trans_date' => '2026-10-01'],
        [...$baseRow, 'doc_no' => 'TEST-SOR-0004', 'status' => 'PAID', 'type_order' => 'CUSTOM', 'trans_date' => '2026-10-01'],
        [...$baseRow, 'doc_no' => 'TEST-SOR-0005', 'status' => 'ORDER', 'type_order' => 'CUSTOM', 'trans_date' => '2026-10-05'],
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/SOR-USED',
        'spk_type' => 'Pesanan',
        'request_order_no' => 'TEST-SOR-0003',
        'is_deleted' => 0,
    ]);

    try {
        $this->getJson(route('spk.store-order-requests', ['search' => 'TEST-SOR-']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.tabCounts', ['pending' => 2, 'with_spk' => 1])
            ->assertJsonPath('data.0.docNo', 'TEST-SOR-0005')
            ->assertJsonPath('data.1', [
                'rowId' => DB::connection('second')->table('request_order')->where('doc_no', 'TEST-SOR-0001')->value('row_id'),
                'docNo' => 'TEST-SOR-0001',
                'transDate' => '01-Oct-2026',
                'estimatedDate' => '23-Oct-2026',
                'targetDaysLeft' => 3,
                'store' => '-',
                'customer' => '-',
                'item' => 'Cincin Maryam',
                'refSku' => null,
                'typeOrder' => 'DP PO',
                'paymentStatus' => 'Belum Lunas',
                'notes' => 'Size 12',
                'imageUrl' => null,
                'goldInfo' => '750',
                'createdBy' => 'Annisa Fitrie',
                'spks' => [],
            ]);

        $this->getJson(route('spk.store-order-requests', ['tab' => 'with_spk', 'search' => 'TEST-SOR-']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.docNo', 'TEST-SOR-0003')
            ->assertJsonPath('data.0.spks', [[
                'spkNo' => 'TEST/SPK/SOR-USED',
                'status' => SpkDashboardAnalytics::backlogStatusLabel($production->fresh(), false),
            ]]);

        $this->getJson(route('spk.store-order-requests', ['tab' => 'with_spk', 'search' => 'TEST/SPK/SOR-USED']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.docNo', 'TEST-SOR-0003');
    } finally {
        $production->delete();
        DB::connection('second')->table('request_order')->whereIn('doc_no', $docNos)->delete();
    }
});

test('spk index defers store order request count', function () {
    config(['services.store_api.key' => null]);
    Http::preventStrayRequests();

    $this->get(route('spk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('storeOrderRequestCount')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('storeOrderRequestCount', app(StoreOrderRequestRepository::class)->pendingSpkCount())
            )
        );
});
