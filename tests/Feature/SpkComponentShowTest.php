<?php

use App\Models\Production;
use App\Models\SpkComponent;

test('spk show page includes components list props', function () {
    $component = SpkComponent::query()->notDeleted()->orderByDesc('line_id')->first();

    if ($component === null) {
        $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

        expect($production)->not->toBeNull();

        $component = SpkComponent::factory()->create([
            'row_id' => $production->row_id,
            'category' => 'Komponen',
            'item' => 'Clasp',
            'color' => 'Yellow Gold',
            'size' => '2',
        ]);
    }

    $production = Production::query()->notDeleted()->find($component->row_id);

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('components')
            ->has('components.0.id')
            ->has('components.0.category')
            ->has('components.0.item')
            ->has('components.0.color')
            ->has('components.0.size')
        );

    if ($component->wasRecentlyCreated) {
        $component->delete();
    }
});

test('spk show page returns empty components when production has none', function () {
    $production = Production::query()
        ->notDeleted()
        ->whereDoesntHave('components', fn ($query) => $query->notDeleted())
        ->first();

    if (! $production) {
        $this->markTestSkipped('No production without components found.');
    }

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('components', [])
        );
});
