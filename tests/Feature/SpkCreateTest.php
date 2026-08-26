<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\RequestOrderRepository;
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
            'diameter_length_ringsize',
            'gold_weight',
            'gold_color',
        ]);
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
