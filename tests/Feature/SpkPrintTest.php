<?php

use App\Models\MsShape;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Support\SpkService;

test('spk print preview page renders document header', function () {
    $this->get(route('spk.print'))
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee('Wanda House of Jewels', false)
        ->assertSee('FORM SPK', false)
        ->assertSee('Doc No. : WHOJ-PRD-FRM-001', false)
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
        ->assertSee('6.900', false)
        ->assertSee('Panjang (mm)', false)
        ->assertDontSee('10.000', false)
        ->assertDontSee('150.000', false)
        ->assertSee('0.050', false)
        ->assertSee('2.250', false)
        ->assertDontSee('2.350', false)
        ->assertSee('>Posisi</th>', false)
        ->assertSee('Center', false)
        ->assertSee('Round', false)
        ->assertDontSee('Round - R', false)
        ->assertSee('Catatan', false)
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

test('spk print preview shows priority banner when priority is yes', function () {
    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Stock',
                'priority' => 'YES',
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
        ->assertSee('PRIORITAS PRODUKSI', false)
        ->assertSee('spkPrintPage--priority', false)
        ->assertSee('spkPrintPriorityPageBorder', false)
        ->assertSee('Stock', false)
        ->assertDontSee('Stock (New Order)', false)
        ->assertDontSee('>Prioritas</th>', false)
        ->assertDontSee('>Status Order</th>', false);
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
                && ($stones[0]['totalCarat'] ?? null) === '2.250';
        })
        ->assertSee('1 item', false)
        ->assertSee('2.35', false)
        ->assertSee('2.250', false)
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
