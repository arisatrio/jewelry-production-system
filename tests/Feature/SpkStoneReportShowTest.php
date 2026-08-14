<?php

use App\Models\Production;

test('spk show page includes stone report for tab laporan batu', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('stoneReport.rows')
            ->has('stoneReport.totalStartCrt')
            ->has('stoneReport.totalEndCrt')
            ->has('stoneReport.totalDifference')
            ->has('stoneReport.totalLabel')
        );
});
