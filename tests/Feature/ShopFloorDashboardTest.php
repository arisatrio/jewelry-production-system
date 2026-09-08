<?php

test('shop floor dashboard includes analytics payload', function () {
    $this->get(route('analytics.shop-floor'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/shop-floor')
            ->has('analytics.period.label')
            ->where('analytics.backlogYear', (int) now()->year)
            ->has('analytics.summary.wipSpk')
            ->has('analytics.summary.overdueWipSpk')
            ->has('analytics.summary.completedThisMonth')
            ->has('analytics.summary.bottleneckProcess')
            ->has('analytics.summary.bottleneckCount')
            ->has('analytics.summary.avgAgeDays')
            ->has('analytics.summary.agedOver7Days')
            ->has('analytics.summary.activeProcesses')
            ->has('analytics.wipByProcess')
            ->has('analytics.agingBuckets')
            ->has('analytics.agingByProcess')
            ->has('analytics.craftsmen')
            ->has('filters.month')
            ->has('navigation.previousMonth')
            ->has('navigation.currentMonth')
            ->where('navigation.nextMonth', null)
            ->where('navigation.isCurrentMonth', true)
        );
});

test('shop floor dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.shop-floor', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/shop-floor')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.previousMonth', '2026-02')
            ->where('navigation.nextMonth', '2026-04')
            ->where('navigation.isCurrentMonth', false)
            ->has('analytics.summary.wipSpk')
            ->has('analytics.wipByProcess')
        );
});

test('shop floor dashboard rejects future month and clamps to current', function () {
    $future = now()->addMonth()->format('Y-m');

    $this->get(route('analytics.shop-floor', ['month' => $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/shop-floor')
            ->where('filters.month', now()->format('Y-m'))
            ->where('navigation.isCurrentMonth', true)
            ->where('navigation.nextMonth', null)
        );
});
