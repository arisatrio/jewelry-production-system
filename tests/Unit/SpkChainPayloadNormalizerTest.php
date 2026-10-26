<?php

use App\Models\MsChain;
use App\Support\SpkChainPayloadNormalizer;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('spk chain payload normalizer resolves item name from mschain', function () {
    if (! Schema::connection('third')->hasTable('mschain')) {
        $this->markTestSkipped('Table mschain is not available.');
    }

    $master = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_PENDANT,
        'item' => 'Chain Classic',
        'item_code' => 'CDOV999',
        'length' => '40.00',
        'item_name' => 'Chain Classic - CDOV999 - 40 cm',
    ]);

    $normalized = app(SpkChainPayloadNormalizer::class)->normalize([
        'mschain_id' => $master->row_id,
        'category' => 'Legacy',
        'item' => 'Wrong label',
        'color' => 'White Gold',
        'size' => '99',
    ]);

    expect($normalized['mschain_id'])->toBe($master->row_id)
        ->and($normalized['category'])->toBe(MsChain::CATEGORY_PENDANT)
        ->and($normalized['item'])->toBe('Chain Classic - CDOV999 - 40 cm')
        ->and($normalized['size'])->toBe('40')
        ->and($normalized['color'])->toBe('White Gold');

    $master->delete();
});
