<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\DiamondMountingApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('pasang batu index exposes filters, filter options and bulk actions', function () {
    $this->get(route('pasang-batu.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/index')
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'desc')
            ->where('filters.process', [])
            ->where('filters.status', ['open', 'ppic'])
            ->where('defaultFilters.status', ['open', 'ppic'])
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->where('filters.craftsman', [])
            ->where('filters.per_page', 50)
            ->has('filterOptions.process', 2)
            ->has('filterOptions.status', 3)
            ->has('filterOptions.craftsman')
            ->has('filterOptions.sort', 4)
            ->where('bulkActions.canSubmit', true)
            ->where('bulkActions.canManagerApprove', true)
            ->where('bulkActions.canComplete', true)
            ->where('bulkActions.canDelete', true)
        );
});

test('pasang batu index lists item column data from spk', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first();
    $sku = $category !== null
        ? SkuMaster::query()
            ->where('category_prefix_id', $category->id)
            ->whereNotNull('sku_code')
            ->whereNotNull('item_original')
            ->orderBy('id')
            ->first()
        : null;

    if ($category === null || $sku === null) {
        $this->markTestSkipped('Membutuhkan data SKU master dan kategori prefix yang sudah ada.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTSKU'.Str::upper(Str::random(3)),
        'description' => 'Deskripsi item pasang batu',
        'file_name' => 'pasang-batu-sku-test.jpg',
        'spk_type' => 'Pesanan',
        'request_order_no' => 'DP-DMT01',
        'customer_name' => 'Customer Pasang Batu',
        'sku_id' => $sku->id,
        'category_prefix_id' => $category->id,
    ]);
    $document = DiamondMounting::factory()->create([
        'doc_no' => 'DMD'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
    ]);

    $this->get(route('pasang-batu.index', ['search' => $document->doc_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.spkNo', $production->spk_no)
            ->where('documents.data.0.skuCode', $sku->sku_code)
            ->where('documents.data.0.typeCode', $category->prefix)
            ->where('documents.data.0.productItemName', $sku->item_original)
            ->where('documents.data.0.itemDescription', 'Deskripsi item pasang batu')
            ->where('documents.data.0.orderReference', 'DP-DMT01 (Customer Pasang Batu)')
            ->where(
                'documents.data.0.spkImageUrl',
                rtrim((string) config('spk.production_image_base_url'), '/').'/pasang-batu-sku-test.jpg',
            )
        );

    $document->delete();
    $production->delete();
});

test('pasang batu index lists process, craftsman, handover dates and weight gain', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTGN'.Str::upper(Str::random(3)),
    ]);
    $craftsman = DB::connection('third')
        ->table('mscraftsman')
        ->where('is_deleted', 0)
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->first(['row_id', 'name']);

    expect($craftsman)->not->toBeNull();

    $document = DiamondMounting::factory()->create([
        'doc_no' => 'DMD9999921',
        'process_name' => 'Reparation',
        'spk_id' => $production->row_id,
        'craftman_id' => (int) $craftsman->row_id,
        'weight_frame' => '2.00',
        'weight_diamond' => '0.100',
        'total_weigth_frame_diamond' => '2.10',
        'weight_finish_goods' => '2.20',
        'mounting_shrink' => '-0.10',
        'send_craftsman_date' => '2026-09-10 08:30:00',
        'received_craftsman_date' => '2026-09-11 15:45:00',
    ]);

    $this->get(route('pasang-batu.index', ['search' => 'DMD9999921']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/index')
            ->where('documents.data.0.id', $document->row_id)
            ->where('documents.data.0.processName', 'Reparation')
            ->where('documents.data.0.craftsmanName', (string) $craftsman->name)
            ->where('documents.data.0.sendCraftsmanDate', '2026-09-10 08:30')
            ->where('documents.data.0.receivedCraftsmanDate', '2026-09-11 15:45')
            ->where('documents.data.0.weightFrame', '2.00')
            ->where('documents.data.0.weightDiamond', '0.100')
            ->where('documents.data.0.totalWeight', '2.10')
            ->where('documents.data.0.weightFinishGoods', '2.20')
            ->where('documents.data.0.shrink', '+0.10')
            ->where('documents.data.0.hasWeightGain', true)
        );

    $document->delete();
    $production->delete();
});

test('pasang batu index can filter by process and status', function () {
    $unique = 'dmtstatus'.Str::lower(Str::random(6));
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTST'.Str::upper(Str::random(3)),
    ]);

    $openDocument = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'process_name' => 'Pasang Batu',
        'status' => null,
        'notes' => $unique,
    ]);
    $doneDocument = DiamondMounting::factory()->done()->create([
        'spk_id' => $production->row_id,
        'process_name' => 'Reparation',
        'notes' => $unique,
    ]);

    $this->get(route('pasang-batu.index', ['status' => ['done'], 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', ['done'])
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $doneDocument->row_id)
        );

    $this->get(route('pasang-batu.index', ['status' => ['open'], 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $openDocument->row_id)
        );

    $this->get(route('pasang-batu.index', ['process' => ['Pasang Batu'], 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.process', ['Pasang Batu'])
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $openDocument->row_id)
        );

    $openDocument->delete();
    $doneDocument->delete();
    $production->delete();
});

test('pasang batu index can filter by send craftsman date range and craftsman', function () {
    $unique = 'dmtdate'.Str::lower(Str::random(6));
    $craftsmanIds = DB::connection('third')
        ->table('mscraftsman')
        ->where('is_deleted', 0)
        ->limit(2)
        ->pluck('row_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    expect($craftsmanIds)->toHaveCount(2);

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTDT'.Str::upper(Str::random(3)),
    ]);

    $inside = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftman_id' => $craftsmanIds[0],
        'send_craftsman_date' => '2026-09-15 10:00:00',
    ]);
    $otherCraftsman = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftman_id' => $craftsmanIds[1],
        'send_craftsman_date' => '2026-09-16 10:00:00',
    ]);
    $outside = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'notes' => $unique,
        'craftman_id' => $craftsmanIds[0],
        'send_craftsman_date' => '2026-08-01 10:00:00',
    ]);

    $this->get(route('pasang-batu.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('documents.data', 2)
        );

    $this->get(route('pasang-batu.index', [
        'search' => $unique,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'craftsman' => $craftsmanIds[0],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.craftsman', [$craftsmanIds[0]])
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $inside->row_id)
        );

    $inside->delete();
    $otherCraftsman->delete();
    $outside->delete();
    $production->delete();
});

test('pasang batu index can change show entries per page', function () {
    $this->get(route('pasang-batu.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.per_page', 25)
            ->where('documents.per_page', 25)
        );

    $this->get(route('pasang-batu.index', ['per_page' => 999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.per_page', 50)
            ->where('documents.per_page', 50)
        );
});

test('pasang batu index can sort by id and spk number', function () {
    $unique = 'dmtsort'.Str::lower(Str::random(6));

    $earlySpk = Production::factory()->create([
        'spk_no' => '2026/PRD/AAA'.Str::upper(Str::random(3)),
    ]);
    $lateSpk = Production::factory()->create([
        'spk_no' => '2026/PRD/ZZZ'.Str::upper(Str::random(3)),
    ]);

    $lowerDoc = DiamondMounting::factory()->create([
        'doc_no' => 'AAA'.Str::upper(Str::random(6)),
        'spk_id' => $lateSpk->row_id,
        'notes' => $unique,
    ]);
    $higherDoc = DiamondMounting::factory()->create([
        'doc_no' => 'ZZZ'.Str::upper(Str::random(6)),
        'spk_id' => $earlySpk->row_id,
        'notes' => $unique,
    ]);

    $this->get(route('pasang-batu.index', ['sort' => 'id', 'direction' => 'asc', 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'id')
            ->where('filters.direction', 'asc')
            ->where('documents.data.0.id', $lowerDoc->row_id)
            ->where('documents.data.1.id', $higherDoc->row_id)
        );

    $this->get(route('pasang-batu.index', ['sort' => 'spk', 'direction' => 'asc', 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'spk')
            ->where('documents.data.0.id', $higherDoc->row_id)
            ->where('documents.data.1.id', $lowerDoc->row_id)
        );

    $this->get(route('pasang-batu.index', ['sort' => 'craftsman', 'search' => $unique]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'craftsman')
            ->has('documents.data', 2)
        );

    $lowerDoc->delete();
    $higherDoc->delete();
    $earlySpk->delete();
    $lateSpk->delete();
});

test('pasang batu bulk status can submit, approve and complete selected documents', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTBLK'.Str::upper(Str::random(3)),
    ]);

    $open = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'status' => null,
    ]);
    $alreadyDone = DiamondMounting::factory()->done()->create([
        'spk_id' => $production->row_id,
    ]);

    $this->from(route('pasang-batu.index'))
        ->post(route('pasang-batu.bulk-status'), [
            'ids' => [$open->row_id, $alreadyDone->row_id],
            'action' => 'submit',
        ])
        ->assertRedirect(route('pasang-batu.index'));

    expect($open->refresh()->status)->toBe(DiamondMountingApprovalService::STATUS_SUBMITTED)
        ->and($alreadyDone->refresh()->status)->toBe(DiamondMounting::STATUS_DONE);

    $this->from(route('pasang-batu.index'))
        ->post(route('pasang-batu.bulk-status'), [
            'ids' => [$open->row_id],
            'action' => 'manager_approve',
        ])
        ->assertRedirect(route('pasang-batu.index'));

    expect($open->refresh()->status)->toBe(DiamondMountingApprovalService::STATUS_MANAGER);

    $this->from(route('pasang-batu.index'))
        ->post(route('pasang-batu.bulk-status'), [
            'ids' => [$open->row_id],
            'action' => 'complete',
        ])
        ->assertRedirect(route('pasang-batu.index'));

    expect($open->refresh()->status)->toBe(DiamondMountingApprovalService::STATUS_DONE);

    DB::connection('third')
        ->table('sysapproval')
        ->where('doc_name', DiamondMountingApprovalService::DOC_NAME)
        ->where('doc_id', $open->row_id)
        ->delete();

    $open->delete();
    $alreadyDone->delete();
    $production->delete();
});

test('pasang batu bulk status can delete eligible documents only', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/DMTBDL'.Str::upper(Str::random(3)),
    ]);

    $open = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'status' => null,
    ]);
    $pending = DiamondMounting::factory()->create([
        'spk_id' => $production->row_id,
        'status' => DiamondMountingApprovalService::STATUS_SUBMITTED,
    ]);

    $this->from(route('pasang-batu.index'))
        ->post(route('pasang-batu.bulk-status'), [
            'ids' => [$open->row_id, $pending->row_id],
            'action' => 'delete',
        ])
        ->assertRedirect(route('pasang-batu.index'));

    expect((int) $open->refresh()->is_deleted)->toBe(1)
        ->and((int) $pending->refresh()->is_deleted)->toBe(0);

    $open->delete();
    $pending->delete();
    $production->delete();
});

test('pasang batu bulk status validates payload', function () {
    $this->from(route('pasang-batu.index'))
        ->post(route('pasang-batu.bulk-status'), [
            'ids' => [],
            'action' => 'invalid',
        ])
        ->assertRedirect(route('pasang-batu.index'))
        ->assertSessionHasErrors(['ids', 'action']);
});
