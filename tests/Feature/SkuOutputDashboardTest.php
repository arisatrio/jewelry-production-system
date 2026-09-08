<?php

test('sku output dashboard includes analytics payload', function () {
    $this->get(route('analytics.sku-output'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/sku-output')
            ->has('analytics.period.label')
            ->has('analytics.summary.totalSpk')
            ->has('analytics.summary.totalQty')
            ->has('analytics.summary.doneSpk')
            ->has('analytics.summary.doneQty')
            ->has('analytics.summary.uniqueSku')
            ->has('analytics.summary.completionPercent')
            ->has('analytics.bySku')
            ->has('analytics.byItem')
            ->has('filters.month')
            ->where('navigation.isCurrentMonth', true)
        );
});

test('sku output dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.sku-output', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/sku-output')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.nextMonth', '2026-04')
        );
});
