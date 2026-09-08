<?php

test('material yield dashboard includes analytics payload', function () {
    $this->get(route('analytics.material-yield'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/material-yield')
            ->has('analytics.period.label')
            ->has('analytics.summary.totalSpk')
            ->has('analytics.summary.totalShrink')
            ->has('analytics.summary.shrinkOkCount')
            ->has('analytics.summary.shrinkNokCount')
            ->has('analytics.summary.goldRequirement')
            ->has('analytics.summary.goldIssued')
            ->has('analytics.summary.goldReturned')
            ->has('analytics.summary.goldUsed')
            ->has('analytics.summary.goldVariance')
            ->has('analytics.summary.stoneStartCrt')
            ->has('analytics.summary.stoneEndCrt')
            ->has('analytics.summary.stoneDifference')
            ->has('analytics.summary.stoneLossPercent')
            ->has('analytics.summary.avgYieldPercent')
            ->has('analytics.summary.avgGoldYieldPercent')
            ->has('analytics.shrink.byProcess')
            ->has('analytics.gold.issued')
            ->has('analytics.stone.startCrt')
            ->has('analytics.control.avgYieldPercent')
            ->has('analytics.craftsmen')
            ->has('filters.month')
            ->has('navigation.previousMonth')
            ->has('navigation.currentMonth')
            ->where('navigation.nextMonth', null)
            ->where('navigation.isCurrentMonth', true)
        );
});

test('material yield dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.material-yield', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/material-yield')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.previousMonth', '2026-02')
            ->where('navigation.nextMonth', '2026-04')
            ->where('navigation.isCurrentMonth', false)
            ->has('analytics.summary.totalShrink')
            ->has('analytics.shrink.byProcess')
        );
});

test('material yield dashboard rejects future month and clamps to current', function () {
    $future = now()->addMonth()->format('Y-m');

    $this->get(route('analytics.material-yield', ['month' => $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/material-yield')
            ->where('filters.month', now()->format('Y-m'))
            ->where('navigation.isCurrentMonth', true)
            ->where('navigation.nextMonth', null)
        );
});
