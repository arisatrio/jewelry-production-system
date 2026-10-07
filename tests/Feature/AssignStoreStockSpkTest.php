<?php

use App\Models\Production;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeStoreStockAssign(int $status = 200): void
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

test('input spk fills request stock number and posts it to the store', function () {
    fakeStoreStockAssign();

    $production = Production::factory()->create([
        'request_stock_no' => null,
    ]);

    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'rs-0000024',
        'spk_no' => $production->spk_no,
    ])
        ->assertSuccessful()
        ->assertJsonPath('spkNo', $production->spk_no)
        ->assertJsonPath('requestStockNo', 'RS-0000024');

    $production->refresh();

    expect($production->request_stock_no)->toBe('RS-0000024')
        ->and($production->modified_by)->toBe('system');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://store.test/api/public/production/spk/update-spk-no'
        && $request->data() === [
            'doc_no' => 'RS-0000024',
            'spk_no' => $production->spk_no,
            'modified_by' => 'system',
        ]);

    $production->delete();
});

test('input spk rolls back when the store api rejects the spk number', function () {
    fakeStoreStockAssign(500);

    $production = Production::factory()->create([
        'request_stock_no' => null,
    ]);

    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'RS-0000024',
        'spk_no' => $production->spk_no,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'spk_no' => 'Gagal mengirim nomor SPK ke Store. Silakan coba lagi.',
        ]);

    expect($production->refresh()->request_stock_no)->toBeNull();

    $production->delete();
});

test('input spk rejects an unknown spk number', function () {
    Http::preventStrayRequests();

    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'RS-0000024',
        'spk_no' => '2099/PRD/00000',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'spk_no' => 'Nomor SPK tidak ditemukan.',
        ]);

    Http::assertNothingSent();
});

test('input spk rejects an spk already linked to another request', function () {
    Http::preventStrayRequests();

    $production = Production::factory()->create([
        'request_stock_no' => 'RS-OTHER1',
    ]);

    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'RS-0000024',
        'spk_no' => $production->spk_no,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('spk_no');

    expect($production->refresh()->request_stock_no)->toBe('RS-OTHER1');

    Http::assertNothingSent();

    $production->delete();
});

test('input spk rejects a request already linked to another spk', function () {
    Http::preventStrayRequests();

    $existing = Production::factory()->create([
        'request_stock_no' => 'RS-0000024',
    ]);
    $production = Production::factory()->create([
        'request_stock_no' => null,
    ]);

    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'RS-0000024',
        'spk_no' => $production->spk_no,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('doc_no');

    expect($production->refresh()->request_stock_no)->toBeNull();

    Http::assertNothingSent();

    $existing->delete();
    $production->delete();
});

test('input spk rejects an invalid request number', function () {
    $this->postJson(route('spk.store-stock-requests.assign'), [
        'doc_no' => 'bukan-nomor',
        'spk_no' => '2026/PRD/00001',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('doc_no');
});

test('input spk modal only shows spk without request stock number', function () {
    $available = Production::factory()->create([
        'request_stock_no' => null,
    ]);
    $linked = Production::factory()->create([
        'request_stock_no' => 'RS-EXIST1',
    ]);

    $response = $this->getJson(route('spk.select.list'));

    $response->assertSuccessful();

    $spkNumbers = collect($response->json('data'))->pluck('produksiNo')->all();

    expect($spkNumbers)->toContain($available->spk_no)
        ->and($spkNumbers)->not->toContain($linked->spk_no);

    $available->delete();
    $linked->delete();
});
