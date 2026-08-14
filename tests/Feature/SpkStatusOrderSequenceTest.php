<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spk show and print include status order with sku sequence for repeat order', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Ring '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'SEQ-'.Str::upper(Str::random(8)),
        'item_original' => 'SEQ ITEM '.Str::upper(Str::random(4)),
    ]);

    $first = app(SpkService::class)->createStock('system');
    $first->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'status_order' => 'NO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(60000, 69999)),
    ]);

    $second = app(SpkService::class)->createStock('system');
    $second->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'status_order' => 'RO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(60000, 69999)),
    ]);

    $third = app(SpkService::class)->createStock('system');
    $third->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'status_order' => 'RO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(60000, 69999)),
    ]);

    $this->get(route('spk.show', $first))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.tipeProduksi', 'Stock')
            ->where('item.statusOrderLabel', 'New Order')
        );

    $this->get(route('spk.show', $third))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.statusOrderLabel', 'Repeat Order 003')
        );

    $this->get(route('spk.print.show', $third->row_id))
        ->assertOk()
        ->assertViewIs('spk.print')
        ->assertSee('>Tipe Produksi</th>', false)
        ->assertSee('Stock', false)
        ->assertDontSee('Stock (Repeat Order', false)
        ->assertSee('Status Order', false)
        ->assertSee('Repeat Order 003', false);

    $first->delete();
    $second->delete();
    $third->delete();
    $sku->delete();
});

test('spk print preview computes repeat order sequence from sku id', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Bangle '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'PRV-'.Str::upper(Str::random(8)),
        'item_original' => 'PREVIEW ITEM '.Str::upper(Str::random(4)),
    ]);

    $existingOne = app(SpkService::class)->createStock('system');
    $existingOne->update([
        'sku_id' => $sku->id,
        'status_order' => 'NO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(50000, 59999)),
    ]);

    $existingTwo = app(SpkService::class)->createStock('system');
    $existingTwo->update([
        'sku_id' => $sku->id,
        'status_order' => 'RO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(50000, 59999)),
    ]);

    $this->postJson(route('spk.print'), [
        'document' => [
            'info' => [
                'spkType' => 'Stock',
                'statusOrder' => 'Repeat Order',
            ],
            'item' => [
                'typeVariant' => 'X | Y',
                'skuId' => (string) $sku->id,
            ],
            'stones' => [],
            'notes' => '',
        ],
    ])
        ->assertOk()
        ->assertSee('Status Order', false)
        ->assertSee('Repeat Order 003', false)
        ->assertDontSee('Stock (Repeat Order)', false);

    $existingOne->delete();
    $existingTwo->delete();
    $sku->delete();
});

test('repeat order sequence treats gold color prefix as the same sku identity', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Ladies Ring '.Str::upper(Str::random(4)),
            'prefix' => 'LDR',
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $identity = 'LDR-'.Str::upper(Str::random(10));

    $twoToneSku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => '2T-'.$identity,
        'item_original' => 'ATARA FLOW 2T '.Str::upper(Str::random(4)),
    ]);

    $whiteGoldSku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'WG-'.$identity,
        'item_original' => 'ATARA FLOW WG '.Str::upper(Str::random(4)),
    ]);

    $first = app(SpkService::class)->createStock('system');
    $first->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $twoToneSku->id,
        'status_order' => 'NO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(40000, 49999)),
    ]);

    $second = app(SpkService::class)->createStock('system');
    $second->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $whiteGoldSku->id,
        'status_order' => 'RO',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(40000, 49999)),
    ]);

    $this->get(route('spk.show', $second))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.statusOrderLabel', 'Repeat Order 002')
        );

    $this->get(route('spk.print.show', $second->row_id))
        ->assertOk()
        ->assertSee('Repeat Order 002', false);

    $first->delete();
    $second->delete();
    $twoToneSku->delete();
    $whiteGoldSku->delete();
});

test('spk create assigns new and repeat order automatically from sku history', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Auto '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'AUTO-'.Str::upper(Str::random(8)),
        'item_original' => 'AUTO ITEM '.Str::upper(Str::random(4)),
    ]);

    $details = [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Auto status order',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
    ];

    $first = app(SpkService::class)->createWithDetails($details, 'system');
    $second = app(SpkService::class)->createWithDetails($details, 'system');

    expect($first->status_order)->toBe('NO')
        ->and($second->status_order)->toBe('RO');

    $this->get(route('spk.show', $first))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('item.statusOrderLabel', 'New Order')
        );

    $this->get(route('spk.show', $second))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('item.statusOrderLabel', 'Repeat Order 002')
        );

    $first->delete();
    $second->delete();
    $sku->delete();
});
