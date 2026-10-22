<?php

use App\Models\Production;
use App\Models\SpkChain;

test('spk show page includes chains list props', function () {
    $chain = SpkChain::query()->notDeleted()->orderByDesc('line_id')->first();

    if ($chain === null) {
        $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

        expect($production)->not->toBeNull();

        $chain = SpkChain::factory()->create([
            'row_id' => $production->row_id,
            'category' => 'Bracelet Chain',
            'item' => 'Cable Chain',
            'color' => 'Yellow Gold',
            'size' => '18',
        ]);
    }

    $production = Production::query()->notDeleted()->find($chain->row_id);

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('chains')
            ->has('chains.0.id')
            ->has('chains.0.category')
            ->has('chains.0.item')
            ->has('chains.0.color')
            ->has('chains.0.size')
        );

    if ($chain->wasRecentlyCreated) {
        $chain->delete();
    }
});

test('spk show page returns empty chains when production has none', function () {
    $production = Production::query()
        ->notDeleted()
        ->whereDoesntHave('chains', fn ($query) => $query->notDeleted())
        ->first();

    if (! $production) {
        $this->markTestSkipped('No production without chains found.');
    }

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('chains', [])
        );
});
