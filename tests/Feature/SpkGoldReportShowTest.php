<?php

use App\Models\Production;

test('spk show page includes gold report for tab laporan emas', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('goldReport.issued')
            ->has('goldReport.returned')
            ->has('goldReport.used')
            ->has('goldReport.difference')
            ->has('goldReport.materials')
            ->has('goldReport.totalLabel')
        );
});
