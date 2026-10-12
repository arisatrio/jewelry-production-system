<?php

use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validRequestStockDrkPayload(array $overrides = []): array
{
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST DRK '.Str::upper(Str::random(4)),
            'prefix' => 'D'.Str::upper(Str::random(2)),
            'usage_count' => 0,
            'is_active' => 1,
        ]);

    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
        'sku_code' => 'DRK-'.Str::upper(Str::random(8)),
    ]);

    return [
        'spk_type' => 'Stock',
        'order_date' => '2026-10-12',
        'work_estimated' => 5,
        'priority' => 'NO',
        'description' => 'Stock permintaan direksi',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 1,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 1.5,
        'gold_color' => 'Yellow Gold',
        'status_order' => 'NO',
        ...$overrides,
    ];
}

test('spk table has request_stock_drk_no after request_stock_no', function () {
    $columns = Schema::connection('third')->getColumns('spk');
    $names = array_column($columns, 'name');
    $requestStockIndex = array_search('request_stock_no', $names, true);
    $requestStockDrkIndex = array_search('request_stock_drk_no', $names, true);

    expect($requestStockIndex)->not->toBeFalse()
        ->and($requestStockDrkIndex)->toBe($requestStockIndex + 1);
});

test('spk service generates sequential request stock direksi numbers', function () {
    $service = app(SpkService::class);

    $latest = Production::query()
        ->whereRaw('request_stock_drk_no REGEXP ?', ['^RS-DRK-[0-9]{4}$'])
        ->orderByDesc('request_stock_drk_no')
        ->value('request_stock_drk_no');

    $expectedNext = 1;

    if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
        $expectedNext = (int) $matches[1] + 1;
    }

    $number = DB::connection('third')->transaction(
        fn (): string => $service->generateRequestStockDrkNo(),
    );

    expect($number)->toBe(sprintf('RS-DRK-%04d', $expectedNext));
});

test('stock permintaan direksi stores generated request_stock_drk_no', function () {
    $payload = validRequestStockDrkPayload([
        'description' => 'Stock permintaan direksi '.Str::random(6),
    ]);

    $response = $this->post(route('spk.store'), $payload);

    $production = Production::query()
        ->notDeleted()
        ->where('description', $payload['description'])
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull();
    $response->assertRedirect(route('spk.show', $production->spk_no));

    expect($production->request_stock_no)->toBeNull()
        ->and($production->request_stock_drk_no)->toMatch('/^RS-DRK-\d{4}$/');

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.requestStockDrkNo', $production->request_stock_drk_no)
        );

    $this->get(route('spk.show', $production->spk_no))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('production.requestStockDrkNo', $production->request_stock_drk_no)
        );

    $production->delete();
});

test('stock from store request does not generate request_stock_drk_no', function () {
    config([
        'services.store_api.base_url' => 'https://store.test/api/public',
        'services.store_api.key' => 'test-store-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'store.test/api/public/production/spk/update-spk-no' => Http::response(['message' => 'ok']),
    ]);

    $payload = validRequestStockDrkPayload([
        'description' => 'Stock permintaan toko '.Str::random(6),
        'request_stock_no' => 'rs-0000088',
    ]);

    $this->post(route('spk.store'), $payload)->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('description', $payload['description'])
        ->orderByDesc('row_id')
        ->first();

    expect($production)->not->toBeNull()
        ->and($production->request_stock_no)->toBe('RS-0000088')
        ->and($production->request_stock_drk_no)->toBeNull();

    $production->delete();
});
