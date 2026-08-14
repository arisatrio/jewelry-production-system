<?php

use App\Models\Production;

test('spk show page includes craftsman report for tab laporan', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('craftsmanReport')
            ->has('shrinkReport.rows')
            ->has('shrinkReport.totalShrink')
        );
});
