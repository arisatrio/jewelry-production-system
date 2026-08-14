<?php

use App\Models\Production;

test('spk show page includes shrink report for laporan', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('shrinkReport.rows')
            ->has('shrinkReport.planningWeight')
            ->has('shrinkReport.startWeight')
            ->has('shrinkReport.endWeight')
            ->has('shrinkReport.goldIssued')
            ->has('shrinkReport.goldReturned')
            ->has('shrinkReport.goldUsed')
            ->has('shrinkReport.goldMaterials')
            ->has('shrinkReport.totalShrink')
            ->has('shrinkReport.totalShrinkPercent')
            ->has('shrinkReport.totalLost')
            ->has('shrinkReport.totalLostPercent')
            ->has('shrinkReport.totalLabel')
            ->has('shrinkReport.rows.0.shrinkPercent')
            ->has('shrinkReport.rows.0.tolerance')
            ->has('shrinkReport.rows.0.toleranceStatus')
        );
});
