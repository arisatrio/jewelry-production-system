<?php

test('craftsman performance dashboard includes analytics payload', function () {
    $this->get(route('analytics.craftsman-performance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/craftsman-performance')
            ->has('analytics.period.label')
            ->has('analytics.summary.activeCraftsmen')
            ->has('analytics.summary.totalJobs')
            ->has('analytics.summary.totalShrink')
            ->has('analytics.summary.avgJobsPerCraftsman')
            ->has('analytics.summary.topCraftsman')
            ->has('analytics.summary.topCraftsmanJobs')
            ->has('analytics.summary.heaviestShrinkCraftsman')
            ->has('analytics.summary.heaviestShrink')
            ->has('analytics.ranking')
            ->has('analytics.byProcess')
            ->has('filters.month')
            ->where('navigation.isCurrentMonth', true)
        );
});

test('craftsman performance dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.craftsman-performance', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/craftsman-performance')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.nextMonth', '2026-04')
        );
});
