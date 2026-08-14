<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spk show maps dimensi to dimensi field when diameter is empty', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Ukuran Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Ukuran Product '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'diameter_length_ringsize' => ' / Panjang 150 / ',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(60000, 69999)),
    ]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.diameter', '-')
            ->where('item.dimensi', 'Panjang 150')
            ->where('item.ringSize', '-')
        );

    $this->get(route('spk.print.show', $production->row_id))
        ->assertOk()
        ->assertSee('Panjang 150', false);

    $production->delete();
    $sku->delete();
});

test('spk show splits combined label stuck in diameter field', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Stuck Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Stuck Product '.Str::upper(Str::random(4)),
    ]);

    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'item_name' => $category->category,
        'diameter_length_ringsize' => ' / Panjang 150 / ',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(70000, 79999)),
    ]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('item.diameter', '-')
            ->where('item.dimensi', 'Panjang 150')
            ->where('item.ringSize', '-')
        );

    $production->delete();
    $sku->delete();
});

test('spk store keeps dimensi in middle slot of combined ukuran', function () {
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'Slot Type '.Str::upper(Str::random(4)),
            'prefix' => strtoupper(Str::random(3)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'TST-'.Str::upper(Str::random(8)),
        'item_original' => 'Slot Product '.Str::upper(Str::random(6)),
    ]);
    $description = 'Slot Ukuran '.Str::upper(Str::random(6));

    $this->post(route('spk.store'), [
        'spk_type' => 'Stock',
        'order_date' => '2026-08-03',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => $description,
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter' => '',
        'dimensi' => 'Panjang 150',
        'ring_size' => '',
        'diameter_length_ringsize' => ' / Panjang 150 / ',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
    ])->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', $description)
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->category_prefix_id)->toBe($category->id)
        ->and($production->diameter_length_ringsize)->toBe(' / Panjang 150 / ');

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('item.diameter', '-')
            ->where('item.dimensi', 'Panjang 150')
            ->where('item.ringSize', '-')
        );

    $production?->delete();
    $sku->delete();
});
