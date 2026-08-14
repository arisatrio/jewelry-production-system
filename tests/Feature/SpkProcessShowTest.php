<?php

use App\Models\Production;

test('spk show page includes mapped production processes', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('processes')
            ->has('processes.0.key')
            ->has('processes.0.label')
            ->has('processes.0.tables')
            ->has('processes.0.placement')
            ->has('processes.0.recordCount')
            ->has('processes.0.sources')
            ->where('processes.0.key', 'JewelCAD')
            ->where('processes.0.tables', ['requestjwcaddetails'])
            ->where('processes.0.placement', 'proses-produksi')
            ->has('defaultProcessSelection.mainSection')
            ->has('defaultProcessSelection.processKey')
        );
});
