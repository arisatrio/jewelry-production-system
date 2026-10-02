<?php

use App\Models\Production;
use App\Support\SpkService;

test('spk index excludes reparasi type by default', function () {
    $customer = 'Exclude Reparasi '.strtoupper(fake()->unique()->lexify('??????'));
    $stock = Production::factory()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);
    $repair = Production::factory()->create([
        'spk_type' => 'Reparasi',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $customer]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('indexContext', 'spk')
            ->where('types', SpkService::standardIndexTypes())
            ->where('productions.total', 1)
            ->where('productions.data.0.produksiNo', $stock->spk_no)
        );

    collect([$stock, $repair])->each->delete();
});

test('reparasi index shows only reparasi spk', function () {
    $customer = 'Reparasi Only '.strtoupper(fake()->unique()->lexify('??????'));
    $stock = Production::factory()->create([
        'spk_type' => 'Stock',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);
    $repair = Production::factory()->create([
        'spk_type' => 'Reparasi',
        'customer_name' => $customer,
        'is_deleted' => 0,
    ]);

    $this->get(route('reparasi.index', ['search' => $customer]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reparasi/index')
            ->where('indexContext', 'reparasi')
            ->where('types', ['Reparasi'])
            ->where('filters.type', ['Reparasi'])
            ->where('productions.total', 1)
            ->where('productions.data.0.produksiNo', $repair->spk_no)
            ->where('productions.data.0.tipeProduksi', 'Reparasi')
        );

    collect([$stock, $repair])->each->delete();
});
