<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\RequestOrderRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validSpkStorePayload(array $overrides = []): array
{
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

    return [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Create SPK test',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'gold_content' => 'Polish',
        'status_order' => 'NO',
        'notes' => 'Catatan create',
        ...$overrides,
    ];
}

test('spk create prefills dates and sku from store stock request', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $skuCode = 'RG-STOCK-'.strtoupper(fake()->unique()->bothify('??##??'));
    $sku = SkuMaster::factory()->create([
        'sku_code' => $skuCode,
        'category_prefix_id' => $category->id,
        'item_original' => 'EAR ELECTA OVAL',
    ]);

    $this->get(route('spk.create', [
        'order_date' => '2026-09-23',
        'estimated_delivery_time' => '2026-10-23',
        'sku' => strtolower($skuCode),
        'gold_weight' => '4.64',
        'request_stock_no' => 'rs-0000033',
        'store_notes' => 'Ukuran 16, finish glossy',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.isNew', true)
            ->where('production.requestStockNo', 'RS-0000033')
            ->where('production.notes', 'Catatan dari Toko: Ukuran 16, finish glossy')
            ->where('production.goldWeight', '4.64')
            ->where('production.orderDate', '2026-09-23')
            ->where('production.estimatedDeliveryTime', '2026-10-23')
            ->where('production.categoryPrefixId', (string) $category->id)
            ->where('production.itemTypeId', (string) $category->id)
            ->where('production.skuId', (string) $sku->id)
        );
});

test('spk create keeps a blank sku when the stock request sku is unknown', function () {
    $this->travelTo('2026-10-05');

    $this->get(route('spk.create', [
        'order_date' => 'not-a-date',
        'estimated_delivery_time' => '2026-13-40',
        'sku' => 'MISSING-SKU-CODE',
        'request_stock_no' => 'bukan-nomor',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('production.requestStockNo', null)
            ->where('production.orderDate', '2026-10-05')
            ->where('production.estimatedDeliveryTime', '')
            ->where('production.categoryPrefixId', '')
            ->where('production.skuId', '')
            ->where('stones', [])
        );
});

test('spk create page shows form without generating number', function () {
    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.isNew', true)
            ->where('production.id', null)
            ->where('production.spkNo', null)
            ->where('production.spkType', 'Stock')
            ->where('production.priority', 'NO')
            ->where('production.qty', '1')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-001')
            ->has('options.spkTypes')
            ->has('options.categories')
            ->has('options.skus')
            ->has('approvalFooter', 3)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', 'system')
            ->where('approvalFooter.0.date', fn ($date) => is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $date) === 1)
            ->where('approvalFooter.1.title', 'Disetujui Oleh')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->where('approvalFooter.2.title', 'Manager Produksi')
            ->where('approvalFooter.2.name', '-')
            ->where('approvalFooter.2.date', '-')
            ->has('approval')
            ->where('options.spkTypes.0', 'Pesanan')
        );
});

test('spk create guide page renders web form instructions', function () {
    $this->get(route('spk.create.guide'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/create-guide')
            ->where('formDocumentNo', 'WHOJ-PRD-FRM-001')
        );
});

test('spk create form options include sku master product items', function () {
    $sku = SkuMaster::factory()->create([
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'SPK SKU '.Str::upper(Str::random(6)),
        'design_image' => 'https://example.com/sku.png',
    ]);

    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->has('options.skus')
            ->where('options.skus', function ($skus) use ($sku) {
                $match = collect($skus)->firstWhere('value', (string) $sku->id);

                if ($match === null) {
                    return false;
                }

                return $match['skuCode'] === $sku->sku_code
                    && $match['itemOriginal'] === $sku->item_original
                    && $match['label'] === $sku->displayName()
                    && $match['imageUrl'] === 'https://example.com/sku.png';
            })
        );

    $sku->delete();
});

test('spk stock can be created with form details and generated number', function () {
    $payload = validSpkStorePayload([
        'spk_type' => 'Stock',
        'priority' => 'YES',
        'description' => 'Stock create full',
        'qty' => 2,
    ]);

    $response = $this->post(route('spk.store'), $payload);

    $production = Production::query()
        ->notDeleted()
        ->where('spk_type', 'Stock')
        ->where('description', 'Stock create full')
        ->where('created_by', 'system')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->spk_no)->toMatch('/^\d{4}\/PRD\/\d{5}$/')
        ->and($production->status)->toBe('')
        ->and($production->priority)->toBe('YES')
        ->and($production->work_estimated)->toBe(5)
        ->and($production->estimated_delivery_time?->toDateString())->toBe('2026-08-10')
        ->and($production->qty)->toBe(2)
        ->and($production->sku_id)->toBe((int) $payload['sku_id'])
        ->and($production->category_prefix_id)->toBe((int) $payload['category_prefix_id'])
        ->and($production->supplier_id)->toBe(1)
        ->and($production->created_by)->toBe('system');

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
});

function fakeStoreSpkNumberUpdate(int $status = 200): void
{
    config([
        'services.store_api.base_url' => 'https://store.test/api/public',
        'services.store_api.key' => 'test-store-key',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'store.test/api/public/production/spk/update-spk-no' => Http::response(['message' => 'ok'], $status),
    ]);
}

test('spk create from request stock keeps the production type as stock', function () {
    fakeStoreSpkNumberUpdate();

    $payload = validSpkStorePayload([
        'spk_type' => 'Pesanan',
        'request_order_no' => 'DP-NOT-USED',
        'request_stock_no' => 'RS-0000077',
        'description' => 'Locked stock type',
    ]);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Locked stock type')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->spk_type)->toBe('Stock')
        ->and($production->request_stock_no)->toBe('RS-0000077')
        ->and($production->request_order_no)->toBeNull();

    $production->delete();
});

test('spk create stores request stock number beside the order number', function () {
    fakeStoreSpkNumberUpdate();

    $payload = validSpkStorePayload([
        'description' => 'Stock with request stock no',
        'request_stock_no' => 'rs-0000044',
    ]);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Stock with request stock no')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->request_stock_no)->toBe('RS-0000044');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://store.test/api/public/production/spk/update-spk-no'
        && $request->hasHeader('X-API-KEY', 'test-store-key')
        && $request->data() === [
            'doc_no' => 'RS-0000044',
            'spk_no' => $production->spk_no,
            'modified_by' => 'system',
        ]);

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('production.requestStockNo', 'RS-0000044')
        );

    $production->delete();
});

test('spk create from request stock rolls back when the store api rejects the spk number', function () {
    fakeStoreSpkNumberUpdate(500);

    $payload = validSpkStorePayload([
        'description' => 'Store sync failed',
        'request_stock_no' => 'RS-0000099',
    ]);

    $this->from(route('spk.create'))
        ->post(route('spk.store'), $payload)
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors([
            'request_stock_no' => 'Gagal mengirim nomor SPK ke Store. Silakan coba lagi.',
        ]);

    expect(Production::query()->where('description', 'Store sync failed')->exists())->toBeFalse();
});

test('spk create without a request stock number does not call the store api', function () {
    Http::preventStrayRequests();

    $payload = validSpkStorePayload([
        'description' => 'Stock without store sync',
    ]);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    Http::assertNothingSent();

    Production::query()
        ->where('description', 'Stock without store sync')
        ->orderByDesc('row_id')
        ->first()
        ?->delete();
});

test('spk create copies sku master image filename to file name', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'image_filename' => '1782887215_testskuimage.jpg',
    ]);

    $payload = validSpkStorePayload([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'description' => 'Copy SKU image on create',
    ]);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Copy SKU image on create')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->file_name)->toBe($sku->image_filename);

    $production->delete();
    $sku->delete();
});

test('spk pesanan can be created from request order', function () {
    $order = app(RequestOrderRepository::class)->search('', 1)->first();

    expect($order)->not->toBeNull();

    $payload = validSpkStorePayload([
        'spk_type' => 'Pesanan',
        'request_order_no' => $order['docNo'],
        'description' => 'Pesanan create full',
    ]);

    $response = $this->post(route('spk.store'), $payload);

    $production = Production::query()
        ->notDeleted()
        ->where('spk_type', 'Pesanan')
        ->where('request_order_no', $order['docNo'])
        ->where('created_by', 'system')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->spk_no)->toMatch('/^\d{4}\/PRD\/\d{5}$/')
        ->and($production->request_order_no)->toBe($order['docNo'])
        ->and($production->customer_name)->not->toBeNull()
        ->and($production->description)->toBe('Pesanan create full');

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
});

test('spk exchange can be created from approved reference', function () {
    $reference = Production::query()
        ->notDeleted()
        ->where('status', 'SPKDONE')
        ->whereNotNull('spk_no')
        ->orderByDesc('row_id')
        ->first();

    expect($reference)->not->toBeNull();

    $payload = validSpkStorePayload([
        'spk_type' => 'Exchange',
        'ref_spk_id' => $reference->row_id,
        'description' => 'Exchange create full',
        'priority' => 'YES',
        'qty' => 3,
        'gold_color' => 'White Gold',
    ]);

    $response = $this->post(route('spk.store'), $payload);

    $production = Production::query()
        ->notDeleted()
        ->where('spk_type', 'Exchange')
        ->where('ref_spk_id', $reference->row_id)
        ->where('created_by', 'system')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->spk_no)->toMatch('/^\d{4}\/PRD\/\d{5}$/')
        ->and($production->description)->toBe('Exchange create full')
        ->and($production->qty)->toBe(3)
        ->and($production->gold_color)->toBe('White Gold')
        ->and($production->priority)->toBe('YES');

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
});

test('spk create validates required type and form fields', function () {
    $this->from(route('spk.create'))
        ->post(route('spk.store'), [])
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors([
            'spk_type',
            'order_date',
            'estimated_delivery_time',
            'priority',
            'description',
            'category_prefix_id',
            'sku_id',
            'qty',
            'satuan',
            'gold_weight',
            'gold_color',
        ]);
});

test('spk create allows an empty diameter length ring size', function () {
    $payload = validSpkStorePayload([
        'description' => 'Stock without ukuran',
    ]);
    unset($payload['diameter_length_ringsize']);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Stock without ukuran')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->diameter_length_ringsize)->toBeNull();

    $production->delete();
});

test('spk create stores selected sku_id as product item reference', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'CRT-'.Str::upper(Str::random(8)),
        'item_original' => 'Create SKU Product',
    ]);

    $response = $this->post(route('spk.store'), validSpkStorePayload([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'description' => 'Create with sku',
    ]));

    $production = Production::query()
        ->notDeleted()
        ->where('description', 'Create with sku')
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id);

    $response->assertRedirect(route('spk.show', $production->spk_no));

    $production->delete();
    $sku->delete();
});

test('spk pesanan requires request order number', function () {
    $this->from(route('spk.create'))
        ->post(route('spk.store'), validSpkStorePayload([
            'spk_type' => 'Pesanan',
        ]))
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('request_order_no');
});

test('spk exchange requires reference spk', function () {
    $this->from(route('spk.create'))
        ->post(route('spk.store'), validSpkStorePayload([
            'spk_type' => 'Exchange',
        ]))
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('ref_spk_id');
});

test('spk exchange rejects non approved reference', function () {
    $reference = Production::factory()->create([
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(90000, 99999)),
        'status' => 'SPK010',
        'spk_type' => 'Stock',
    ]);

    $this->from(route('spk.create'))
        ->post(route('spk.store'), validSpkStorePayload([
            'spk_type' => 'Refund',
            'ref_spk_id' => $reference->row_id,
        ]))
        ->assertRedirect(route('spk.create'))
        ->assertSessionHasErrors('ref_spk_id');

    $reference->delete();
});

test('request order selector endpoint returns data', function () {
    $this->getJson(route('spk.select.request-orders'))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['rowId', 'docNo', 'customer', 'item', 'refSku'],
            ],
        ]);
});

test('reference spk selector endpoint returns approved only', function () {
    $this->getJson(route('spk.select.reference-spks'))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => [
                    'rowId',
                    'spkNo',
                    'customer',
                    'item',
                    'lastWeight',
                    'frameNo',
                    'requestOrderNo',
                ],
            ],
        ]);
});
