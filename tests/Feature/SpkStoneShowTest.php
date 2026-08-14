<?php

use App\Models\Production;
use App\Models\SpkStone;

test('spk show page includes stones list props', function () {
    $stone = SpkStone::query()->notDeleted()->with('shape')->first();

    expect($stone)->not->toBeNull();

    $production = Production::query()->notDeleted()->find($stone->row_id);

    expect($production)->not->toBeNull();

    $expectedShapeName = filled($stone->shape?->name)
        ? (string) $stone->shape->name
        : (filled($stone->shape?->code) ? (string) $stone->shape->code : '-');

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('stones')
            ->has('stones.0.id')
            ->has('stones.0.shape')
            ->has('stones.0.shapeName')
            ->where('stones.0.shapeName', $expectedShapeName)
            ->has('stones.0.pcs')
            ->has('stones.0.carat')
            ->has('stones.0.caratPerPcs')
            ->has('stones.0.totalCarat')
            ->has('stones.0.size')
        );

    if (filled($stone->shape?->name) && filled($stone->shape?->code)) {
        expect($expectedShapeName)->not->toContain(' - '.$stone->shape->code);
    }
});

test('spk show page returns empty stones when production has none', function () {
    $production = Production::query()
        ->notDeleted()
        ->whereDoesntHave('stones', fn ($query) => $query->notDeleted())
        ->first();

    if (! $production) {
        $this->markTestSkipped('No production without stones found.');
    }

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('stones', [])
        );
});
