<?php

use App\Models\Production;

test('spk show page includes production control report', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('productionControlReport.leadTime')
            ->has('productionControlReport.idleTimes')
            ->has('productionControlReport.yieldPlanning')
        );
});
