<?php

use App\Models\MsChain;
use App\Support\SpkService;
use Illuminate\Support\Facades\Schema;

test('spk form includes mschain options for chain item select', function () {
    if (! Schema::connection('third')->hasTable('mschain')) {
        $this->markTestSkipped('Table mschain is not available.');
    }

    $chain = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_PENDANT,
        'item' => 'Chain Classic',
        'item_code' => 'TEST-CDOV',
        'length' => '40.00',
        'item_name' => 'Chain Classic - TEST-CDOV - 40 cm',
        'description' => 'Test chain option',
    ]);

    $production = app(SpkService::class)->createStock('system');

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('options.chainOptions', function ($options) use ($chain) {
                $match = collect($options)->firstWhere('value', (string) $chain->row_id);

                return is_array($match)
                    && $match['label'] === 'Chain Classic - TEST-CDOV - 40 cm'
                    && $match['category'] === MsChain::CATEGORY_PENDANT
                    && $match['item'] === 'Chain Classic'
                    && $match['size'] === '40';
            })
        );

    $chain->delete();
    $production->delete();
});

test('spk create form includes chain options', function () {
    if (! Schema::connection('third')->hasTable('mschain')) {
        $this->markTestSkipped('Table mschain is not available.');
    }

    $this->get(route('spk.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->has('options.chainOptions')
        );
});
