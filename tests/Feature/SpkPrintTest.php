<?php

use App\Models\Employee;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\SerahTerimaSpk;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Support\SpkService;
use Illuminate\Support\Facades\DB;

test('spk print preview page renders document header', function () {
    $response = $this->get(route('spk.print'));

    $response
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee('Wanda House of Jewels', false)
        ->assertSee('FORM SPK', false)
        ->assertSee('Doc No. : WHOJ-PRD-FRM-001', false)
        ->assertSee('Date: '.now()->format('d-m-Y'), false)
        ->assertDontSee('Page 1 of 1', false)
        ->assertSee('Informasi Produksi', false)
        ->assertSee('No. SPK', false)
        ->assertSee(now()->format('Y').'/PRD/00000', false)
        ->assertSee('Detail Item', false)
        ->assertSee('Panjang (mm)', false)
        ->assertDontSee('Diameter (mm)', false)
        ->assertSee('spkPrintDetailGrid', false)
        ->assertSee('grid-template-columns: minmax(0, 8fr) minmax(0, 4fr)', false)
        ->assertSee('.spkPrintImageFrame {', false)
        ->assertSee('border: 1px solid #ccc', false)
        ->assertSee('.spkPrintMetaTable--item {', false)
        ->assertSee('padding: 4px 6px', false)
        ->assertSee('.spkPrintFieldSku', false)
        ->assertSee('font-size: 7pt', false)
        ->assertSee('Daftar Batu', false)
        ->assertSee('>Posisi</th>', false)
        ->assertSee('Tanggal Cor :', false)
        ->assertSee('No Form Cor :', false)
        ->assertSee('spkPrintCorFields', false)
        ->assertSee('spkPrintMetaTable--cor', false)
        ->assertSee('Catatan', false)
        ->assertSee('spkPrintBottom', false)
        ->assertSee('Dibuat Oleh', false)
        ->assertSee('Deskripsi Item', false)
        ->assertSee('Pesanan', false)
        ->assertSee('Tanggal Pesanan Dibuat', false)
        ->assertSee('Tanggal Diterima Produksi', false)
        ->assertDontSee('>No. Pesanan</th>', false)
        ->assertDontSee('>Customer</th>', false)
        ->assertDontSee('>Status Order</th>', false);

    expect($response->viewData('document')['info']['receivedByProductionDate'])->toBe('');
});

test('spk print uses configured issue date from config', function () {
    config(['spk.issue_date' => '15-08-2026']);

    $this->get(route('spk.print'))
        ->assertOk()
        ->assertSee('Date: 15-08-2026', false);
});

test('spk print preview accepts form document payload', function () {
    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Stock',
                'orderDate' => '05/08/2026',
                'workEstimated' => '5 hari kerja',
                'estimatedDelivery' => '12/08/2026',
                'priority' => 'NO',
                'statusOrder' => 'Repeat Order',
                'requestOrderNo' => 'DP-0009303',
                'customerName' => 'Vera',
                'itemType' => 'Bracelet',
                'itemVariance' => 'ETERNA',
                'qty' => '2 Pcs',
            ],
            'item' => [
                'typeVariant' => 'LDR | LR ATARA FLOW 0.7 CT',
                'typeCode' => 'LDR',
                'productItemName' => 'LR ATARA FLOW 0.7 CT',
                'skuCode' => '2T-LDR-ATF-REG-PRS-DMD-DS-070',
                'statusOrderLabel' => 'Repeat Order 003',
                'qty' => '2 Pcs',
                'diameter' => '10',
                'dimensi' => '150',
                'ringSize' => '12',
                'goldWeight' => '6.90',
                'goldColor' => 'White Gold',
                'jwcad3d' => 'FILE-001',
                'description' => 'Deskripsi uji print',
                'imageUrl' => '/storage/spk/test-item.jpg',
            ],
            'stones' => [
                [
                    'positionName' => 'Center',
                    'shapeName' => 'Round',
                    'size' => '2.35',
                    'caratPerPcs' => '0.05',
                    'pcs' => '45',
                    'totalCarat' => '2.25',
                ],
            ],
            'notes' => 'Catatan print uji',
            'approval' => [
                ['title' => 'Dibuat Oleh', 'name' => 'Tester', 'date' => '05/08/2026 15:00'],
                ['title' => 'Disetujui Oleh', 'name' => '-', 'date' => '-'],
                ['title' => 'Manager Produksi', 'name' => '-', 'date' => '-'],
            ],
        ],
    ])
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee('Stock', false)
        ->assertDontSee('Stock (Repeat Order)', false)
        ->assertSee('DP-0009303 (Vera)', false)
        ->assertDontSee('Tanggal Pesanan Dibuat', false)
        ->assertSee('Tanggal Diterima Produksi', false)
        ->assertSee('LDR | LR ATARA FLOW 0.7 CT', false)
        ->assertSee('spkPrintFieldSku', false)
        ->assertSee('2T-LDR-ATF-REG-PRS-DMD-DS-070', false)
        ->assertSee('Status Order', false)
        ->assertSee('Repeat Order 003', false)
        ->assertSee('White Gold', false)
        ->assertSee('6.90', false)
        ->assertSee('Panjang (mm)', false)
        ->assertDontSee('10.00', false)
        ->assertDontSee('150.00', false)
        ->assertSee('0.05', false)
        ->assertSee('2.25', false)
        ->assertDontSee('2.35', false)
        ->assertSee('>Posisi</th>', false)
        ->assertSee('Center', false)
        ->assertSee('Round', false)
        ->assertDontSee('Round - R', false)
        ->assertSee('Catatan', false)
        ->assertSee('Tanggal Cor :', false)
        ->assertSee('No Form Cor :', false)
        ->assertSee('Catatan print uji', false)
        ->assertSee('Deskripsi Item', false)
        ->assertSee('Deskripsi uji print', false)
        ->assertSee('Tester', false)
        ->assertSee('src="'.url('/storage/spk/test-item.jpg').'"', false)
        ->assertDontSee('PRIORITAS PRODUKSI', false)
        ->assertDontSee('>Prioritas</th>', false)
        ->assertDontSee('>No. Pesanan</th>', false)
        ->assertDontSee('>Customer</th>', false)
        ->assertDontSee('>Status Order</th>', false);
});

test('spk print preview shows high priority banner for paid custom pesanan', function () {
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

    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Pesanan',
                'requestOrderNo' => $docNo,
                'priority' => 'NO',
                'statusOrder' => 'New Order',
            ],
            'item' => [
                'typeVariant' => 'LDR | LR ATARA FLOW 0.7 CT',
                'typeCode' => 'LDR',
                'productItemName' => 'LR ATARA FLOW 0.7 CT',
                'skuCode' => '2T-LDR-ATF-REG-PRS-DMD-DS-070',
            ],
            'stones' => [],
            'notes' => '',
        ],
    ])
        ->assertOk()
        ->assertSee('Prioritas Tinggi', false)
        ->assertSee('spkPrintPage--priority-high', false)
        ->assertSee('spkPrintPriorityPageBorder', false)
        ->assertDontSee('PRIORITAS PRODUKSI', false);

    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
});

test('spk detail and print label pesanan with the sales name', function () {
    $salesName = 'Sales '.strtoupper(fake()->unique()->bothify('??????'));
    $docNo = 'DP-TEST-'.strtoupper(fake()->unique()->bothify('????????'));
    $salesId = null;
    $orderId = null;
    $production = null;

    try {
        $salesId = DB::connection('second')->table('sysuser')->insertGetId([
            'company_id' => 1,
            'name' => $salesName,
            'is_login' => 0,
            'is_deleted' => 0,
        ]);
        $orderId = DB::connection('second')->table('request_order')->insertGetId([
            'company_id' => 1,
            'doc_no' => $docNo,
            'sales_id' => $salesId,
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
            'customer_name' => 'Linda Nur Laili',
            'status' => '',
            'is_deleted' => 0,
        ]);
        $heading = "Pesanan (Sales: {$salesName})";

        $this->get(route('spk.show', $production))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('spk/show')
                ->where('production.salesName', $salesName)
                ->where('production.pesananHeading', $heading)
            );

        $this->get(route('spk.print.show', $production->row_id))
            ->assertOk()
            ->assertSee($heading, false)
            ->assertSee("{$docNo} (Linda Nur Laili) (Lunas)", false);
    } finally {
        $production?->delete();

        if ($orderId !== null) {
            DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
        }

        if ($salesId !== null) {
            DB::connection('second')->table('sysuser')->where('row_id', $salesId)->delete();
        }
    }
});

test('spk print preview shows medium priority banner for unpaid custom pesanan', function () {
    $docNo = 'DP-TEST-'.strtoupper(fake()->unique()->bothify('????????'));

    $orderId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => 'DP PO',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Pesanan',
                'requestOrderNo' => $docNo,
            ],
            'item' => [],
            'stones' => [],
            'notes' => '',
        ],
    ])
        ->assertOk()
        ->assertSee('Prioritas Sedang', false)
        ->assertSee('spkPrintPage--priority-medium', false);

    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
});

test('spk print preview shows low priority banner for nabung bareng pesanan', function () {
    $docNo = 'DP-TEST-'.strtoupper(fake()->unique()->bothify('????????'));

    $orderId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => 'Nabung Bareng',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Pesanan',
                'requestOrderNo' => $docNo,
            ],
            'item' => [],
            'stones' => [],
            'notes' => '',
        ],
    ])
        ->assertOk()
        ->assertSee('Prioritas Rendah', false)
        ->assertSee('spkPrintPage--priority-low', false);

    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
});

test('spk print page for existing production renders document body', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'design_image' => '1782887215_design_print.jpg',
    ]);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'file_name' => 'uploaded-spk.png',
        'sku_id' => $sku->id,
        'category_prefix_id' => $category->id,
    ]);

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee('FORM SPK', false)
        ->assertSee('Informasi Produksi', false)
        ->assertSee('Detail Item', false)
        ->assertSee('Form SPK '.$production->spk_no.' — Print', false)
        ->assertSee(
            'src="https://storage.googleapis.com/system-mahakarya/produksi/uploaded-spk.png"',
            false,
        )
        ->assertDontSee(
            'src="https://storage.googleapis.com/system-mahakarya/produksi/1782887215_design_print.jpg"',
            false,
        )
        ->assertSee('id="spkPrintQr"', false)
        ->assertSee('data-value="'.config('spk.print_qr_url').'"', false);

    $production->delete();
    $sku->delete();
});

test('spk print qr falls back to dynamic detail url when override is empty', function () {
    config(['spk.print_qr_url' => '']);

    $production = app(SpkService::class)->createStock('system');

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertSee(
            'data-value="'.route('spk.show', $production, absolute: true).'"',
            false,
        );

    $production->delete();
});

test('spk print formats stone diameter size with two decimal places', function () {
    $production = app(SpkService::class)->createStock('system');
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    SpkStone::query()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 36,
        'carat' => 0.16,
        'size' => '1.00',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
    ]);

    SpkStone::query()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 1,
        'carat' => 0.3,
        'size' => '5.2 x 3.7',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
    ]);

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertViewHas('document', function (array $document): bool {
            $sizes = collect($document['stones'] ?? [])
                ->pluck('size')
                ->all();

            return in_array('1.00', $sizes, true)
                && in_array('5.2 x 3.7', $sizes, true)
                && ! in_array('1', $sizes, true);
        })
        ->assertSee('1.00', false)
        ->assertSee('5.2 x 3.7', false);

    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Stock',
                'priority' => 'NO',
            ],
            'item' => [
                'typeVariant' => 'TEST',
            ],
            'stones' => [
                [
                    'positionName' => 'Side',
                    'shapeName' => 'Round',
                    'size' => '1',
                    'caratPerPcs' => '0.004',
                    'pcs' => '36',
                    'totalCarat' => '0.16',
                ],
            ],
            'notes' => '',
        ],
    ])
        ->assertOk()
        ->assertViewHas('document', function (array $document): bool {
            return ($document['stones'][0]['size'] ?? null) === '1.00';
        })
        ->assertSee('1.00', false);

    $production->stones()->delete();
    $production->delete();
});

test('spk print page excludes soft deleted stones', function () {
    $production = app(SpkService::class)->createStock('system');
    $shape = MsShape::query()->notDeleted()->orderBy('name')->first();

    expect($shape)->not->toBeNull();

    SpkStone::query()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 10,
        'carat' => 1.0,
        'size' => '1.00',
        'is_deleted' => 1,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
        'deleted_date' => now(),
        'deleted_by' => 'system',
    ]);

    SpkStone::query()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 45,
        'carat' => 2.25,
        'size' => '2.35',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
    ]);

    SpkStone::query()->create([
        'row_id' => $production->row_id,
        'shape_id' => $shape->row_id,
        'pcs' => 20,
        'carat' => 0.5,
        'size' => '0.90',
        'is_deleted' => 1,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
        'deleted_date' => now(),
        'deleted_by' => 'system',
    ]);

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertViewHas('document', function (array $document): bool {
            $stones = $document['stones'] ?? [];

            return count($stones) === 1
                && ($stones[0]['size'] ?? null) === '2.35'
                && ($stones[0]['pcs'] ?? null) === '45'
                && ($stones[0]['totalCarat'] ?? null) === '2.25';
        })
        ->assertSee('1 item', false)
        ->assertSee('2.35', false)
        ->assertSee('2.25', false)
        ->assertDontSee('0.90', false);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('stones', 1)
            ->where('stones.0.pcs', 45)
            ->where('stones.0.size', '2.35')
        );

    $production->stones()->delete();
    $production->delete();
});

test('spk print template page renders blank form format', function () {
    $this->get(route('spk.print.template'))
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertViewHas('blankTemplate', true)
        ->assertSee('Form SPK — Template', false)
        ->assertSee('FORM SPK', false)
        ->assertSee('Wanda House of Jewels', false)
        ->assertSee('Informasi Produksi', false)
        ->assertSee('No. SPK', false)
        ->assertSee('Tipe Produksi', false)
        ->assertSee('SPK Referensi', false)
        ->assertSee('Detail Item', false)
        ->assertSee('Panjang (mm)', false)
        ->assertSee('Tipe Item | SKU', false)
        ->assertSee('Deskripsi Item', false)
        ->assertSee('Status Order', false)
        ->assertSee('Berisi QR code yang jika di-scan akan menampilkan detail SPK di aplikasi', false)
        ->assertSee('Jenis SPK: Stock, Pesanan, Exchange, Refund, atau Reparasi', false)
        ->assertSee('Gambar desain item 1:1 dari SKU atau unggahan SPK', false)
        ->assertSee('Daftar Batu', false)
        ->assertSee('>Posisi</th>', false)
        ->assertSee('Tanggal Cor :', false)
        ->assertSee('No Form Cor :', false)
        ->assertSee('spkPrintCorFields', false)
        ->assertSee('spkPrintMetaTable--cor', false)
        ->assertSee('Catatan', false)
        ->assertSee('min-height: 40px', false)
        ->assertSee('max-height: 52mm', false)
        ->assertSee('Dibuat Oleh', false)
        ->assertSee('Disetujui Oleh', false)
        ->assertSee('Manager Produksi', false)
        ->assertDontSee(now()->format('Y').'/PRD/00000', false)
        ->assertDontSee('Tidak ada batu pada varian ini.', false)
        ->assertDontSee('0 item', false)
        ->assertDontSee('PRIORITAS PRODUKSI', false);
});

function deleteTestingSerahTerimaSpk(): void
{
    SerahTerimaSpk::query()
        ->withTrashed()
        ->where(function ($query): void {
            $query->where('doc_no', 'like', 'WHOJ/PRD/TTS/2098/%')
                ->orWhere('doc_no', 'like', 'WHOJ/PRD/TTS/2099/%')
                ->orWhere('doc_no', 'like', 'WHOJ/PRD/TTS/TEST/%');
        })
        ->forceDelete();
}

beforeEach(fn () => deleteTestingSerahTerimaSpk());
afterEach(fn () => deleteTestingSerahTerimaSpk());

test('spk receipt store generates doc number and saves printed content', function () {
    $customer = 'Tanda Terima '.strtoupper(fake()->unique()->lexify('??????'));
    $first = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-A',
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'estimated_delivery_time' => '2026-10-05 00:00:00',
        'is_deleted' => 0,
    ]);
    $second = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-B',
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'estimated_delivery_time' => '2026-10-10 00:00:00',
        'is_deleted' => 0,
    ]);
    $deleted = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-DELETED',
        'is_deleted' => 1,
    ]);
    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $receivedBy = Employee::factory()->create();

    $response = $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$second->row_id, $first->row_id, $deleted->row_id],
        'tanggal' => '2099-09-30',
        'dari' => 'Head Office',
        'untuk' => 'Workshop',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
        'diterima_oleh' => $receivedBy->nama_lengkap,
    ]);

    $receipt = SerahTerimaSpk::query()->where('doc_no', 'WHOJ/PRD/TTS/2099/0001')->firstOrFail();

    $response->assertCreated()
        ->assertExactJson([
            'id' => $receipt->id,
            'docNo' => 'WHOJ/PRD/TTS/2099/0001',
            'printUrl' => route('spk.print.receipt', $receipt),
        ]);

    expect($receipt->tanggal->toDateString())->toBe('2099-09-30')
        ->and($receipt->dari)->toBe('Head Office')
        ->and($receipt->untuk)->toBe('Workshop')
        ->and($receipt->diserahkan_oleh)->toBe($handedBy->nama_lengkap)
        ->and($receipt->diketahui_oleh)->toBe($acknowledgedBy->nama_lengkap)
        ->and($receipt->diterima_oleh)->toBe($receivedBy->nama_lengkap)
        ->and($receipt->jumlah_spk)->toBe(2)
        ->and($receipt->spk_row_ids)->toBe([(int) $second->row_id, (int) $first->row_id])
        ->and(array_column($receipt->items, 'spkNo'))->toBe(['TEST/SPK/RECEIPT-B', 'TEST/SPK/RECEIPT-A'])
        ->and($receipt->items[1]['targetDate'])->toBe('05-Oct-2026')
        ->and($receipt->created_by)->not->toBeEmpty();

    collect([$first, $second, $deleted, $handedBy, $acknowledgedBy, $receivedBy])->each->delete();
});

test('spk receipt doc number is sequential per year', function () {
    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-SEQ',
        'is_deleted' => 0,
    ]);

    $docNumbers = collect(['2099-01-10', '2099-12-31', '2098-06-01'])
        ->map(fn (string $date): string => $this->postJson(route('spk.print.receipt.store'), [
            'ids' => [$production->row_id],
            'tanggal' => $date,
        ])->assertCreated()->json('docNo'))
        ->all();

    expect($docNumbers)->toBe([
        'WHOJ/PRD/TTS/2099/0001',
        'WHOJ/PRD/TTS/2099/0002',
        'WHOJ/PRD/TTS/2098/0001',
    ]);

    $production->delete();
});

test('spk receipt print page renders stored receipt', function () {
    $receipt = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000001',
        'tanggal' => '2099-09-30',
        'dari' => 'Head Office',
        'untuk' => 'Workshop',
        'diserahkan_oleh' => 'Budi Penyerah',
        'diterima_oleh' => 'Sari Penerima',
        'diketahui_oleh' => 'Joko Mengetahui',
        'jumlah_spk' => 2,
        'spk_row_ids' => [2, 1],
        'items' => [
            ['spkRowId' => 2, 'spkNo' => 'TEST/SPK/PRINT-B', 'type' => 'Stock', 'item' => 'Cincin', 'description' => '', 'customer' => '', 'targetDate' => '10-Oct-2026'],
            ['spkRowId' => 1, 'spkNo' => 'TEST/SPK/PRINT-A', 'type' => 'Pesanan', 'item' => 'Kalung', 'description' => 'Emas 18K', 'customer' => 'Ibu Ani', 'targetDate' => '05-Oct-2026'],
        ],
        'created_by' => 'Admin SPK',
    ]);

    $this->get(route('spk.print.receipt', $receipt))
        ->assertOk()
        ->assertViewIs('spk.receipt')
        ->assertSee('TANDA TERIMA SPK', false)
        ->assertSee('Doc No. : WHOJ-PRD-FRM-002', false)
        ->assertSee('Revision: 00', false)
        ->assertSee('Date: 29/09/2026', false)
        ->assertSeeInOrder(['No. Form', 'WHOJ/PRD/TTS/TEST/000001'], false)
        ->assertSee(': 30-Sep-2099', false)
        ->assertSee(': Head Office', false)
        ->assertSee(': Workshop', false)
        ->assertSee('Jumlah SPK: 2', false)
        ->assertSeeInOrder(['TEST/SPK/PRINT-B', 'TEST/SPK/PRINT-A'], false)
        ->assertSee('Emas 18K', false)
        ->assertSeeInOrder(['>Target Selesai</th>', '>Catatan</th>'], false)
        ->assertDontSee('>Qty</th>', false)
        ->assertSeeInOrder([
            'Diserahkan oleh', 'Budi Penyerah',
            'Diketahui oleh', 'Joko Mengetahui',
            'Diterima oleh', 'Sari Penerima',
        ], false)
        ->assertSee('Dicetak oleh Admin SPK', false);
});

test('spk receipt print page returns not found for unknown receipt', function () {
    $this->get(route('spk.print.receipt', 999999999))->assertNotFound();
});

test('spk receipt store validates form data', function () {
    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-FORM',
        'is_deleted' => 0,
    ]);
    $inactive = Employee::factory()->create(['status' => 'inactive']);

    $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-09-30',
        'diterima_oleh' => $inactive->nama_lengkap,
    ])->assertJsonValidationErrors('diterima_oleh');

    $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '30/09/2099',
    ])->assertJsonValidationErrors('tanggal');

    $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-09-30',
        'dari' => 'Gudang',
        'untuk' => 'Store',
    ])->assertJsonValidationErrors('dari');

    $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-09-30',
        'dari' => 'Store',
        'untuk' => 'Store',
    ])->assertJsonValidationErrors('untuk');

    $this->postJson(route('spk.print.receipt.store'), ['tanggal' => '2099-09-30'])
        ->assertJsonValidationErrors('ids');

    expect(SerahTerimaSpk::query()->where('doc_no', 'like', 'WHOJ/PRD/TTS/2099/%')->exists())->toBeFalse();

    $inactive->delete();
    $production->delete();
});

test('spk receipt history lists stored receipts newest first with search', function () {
    $older = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000011',
        'tanggal' => '2099-09-01',
        'dari' => 'Head Office',
        'untuk' => 'Workshop',
        'diserahkan_oleh' => 'Budi Penyerah',
        'diketahui_oleh' => 'Joko Mengetahui',
        'diterima_oleh' => 'Sari Penerima',
        'jumlah_spk' => 1,
        'spk_row_ids' => [1],
        'items' => [
            ['spkRowId' => 1, 'spkNo' => 'TEST/SPK/HISTORY-A', 'type' => 'Stock', 'item' => 'Cincin', 'description' => '', 'customer' => '', 'targetDate' => '05-Oct-2026'],
        ],
        'created_by' => 'Admin SPK',
    ]);
    $newer = SerahTerimaSpk::factory()->create([
        'doc_no' => 'WHOJ/PRD/TTS/TEST/000012',
        'items' => [
            ['spkRowId' => 2, 'spkNo' => 'TEST/SPK/HISTORY-B', 'type' => 'Stock', 'item' => 'Kalung', 'description' => '', 'customer' => '', 'targetDate' => '06-Oct-2026'],
        ],
    ]);

    $this->getJson(route('spk.print.receipt.index', ['search' => 'WHOJ/PRD/TTS/TEST/00001']))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.docNo', $newer->doc_no)
        ->assertJsonPath('data.1', [
            'id' => $older->id,
            'docNo' => 'WHOJ/PRD/TTS/TEST/000011',
            'tanggal' => '01-Sep-2099',
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
            'jumlahSpk' => 1,
            'spkNos' => ['TEST/SPK/HISTORY-A'],
            'diserahkanOleh' => 'Budi Penyerah',
            'diketahuiOleh' => 'Joko Mengetahui',
            'diterimaOleh' => 'Sari Penerima',
            'createdBy' => 'Admin SPK',
            'createdAt' => $older->created_at->format('d-M-Y H:i'),
            'printUrl' => route('spk.print.receipt', $older),
        ]);

    $this->getJson(route('spk.print.receipt.index', ['search' => 'TEST/SPK/HISTORY-B']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.docNo', $newer->doc_no);
});

test('spk receipt destroy soft deletes receipt and hides it from history and print', function () {
    $receipt = SerahTerimaSpk::factory()->create(['doc_no' => 'WHOJ/PRD/TTS/TEST/000021']);

    $this->deleteJson(route('spk.print.receipt.destroy', $receipt))
        ->assertOk()
        ->assertJsonPath('message', 'Tanda terima WHOJ/PRD/TTS/TEST/000021 berhasil dihapus.');

    $trashed = SerahTerimaSpk::query()->withTrashed()->findOrFail($receipt->id);

    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_by)->not->toBeEmpty();

    $this->getJson(route('spk.print.receipt.index', ['search' => 'WHOJ/PRD/TTS/TEST/000021']))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->get(route('spk.print.receipt', $receipt->id))->assertNotFound();
    $this->deleteJson(route('spk.print.receipt.destroy', $receipt->id))->assertNotFound();
});

test('spk receipt doc number is not reused after soft delete', function () {
    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/RECEIPT-REUSE',
        'is_deleted' => 0,
    ]);
    $store = fn (): string => $this->postJson(route('spk.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-03-01',
    ])->assertCreated()->json('docNo');

    $first = $store();
    SerahTerimaSpk::query()->where('doc_no', $first)->firstOrFail()->delete();

    expect($first)->toBe('WHOJ/PRD/TTS/2099/0001')
        ->and($store())->toBe('WHOJ/PRD/TTS/2099/0002');

    $production->delete();
});
