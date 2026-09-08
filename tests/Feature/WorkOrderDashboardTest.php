<?php

test('work order dashboard includes analytics payload', function () {
    $this->get(route('analytics.work-order'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order')
            ->has('analytics.period.label')
            ->where('analytics.backlogYear', (int) now()->year)
            ->has('analytics.summary.totalSpk')
            ->has('analytics.summary.draftSpk')
            ->has('analytics.summary.confirmedSpk')
            ->has('analytics.summary.inProgressSpk')
            ->has('analytics.summary.doneSpk')
            ->has('analytics.summary.overdueSpk')
            ->has('analytics.summary.forecastSpk')
            ->has('analytics.summary.planningDoneSpk')
            ->has('analytics.summary.planningPendingSpk')
            ->has('analytics.summary.todayTargetSpk')
            ->has('analytics.today.targetSpk')
            ->has('analytics.todayLists.todayTarget')
            ->has('analytics.statusLists.draft')
            ->has('analytics.productionTypes')
            ->has('analytics.inProgressByProcess')
            ->has('analytics.forecast.byItemType')
            ->has('filters.month')
            ->has('navigation.previousMonth')
            ->has('navigation.currentMonth')
            ->where('navigation.nextMonth', null)
            ->where('navigation.isCurrentMonth', true)
        );
});

test('work order dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.work-order', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.previousMonth', '2026-02')
            ->where('navigation.nextMonth', '2026-04')
            ->where('navigation.isCurrentMonth', false)
        );
});

test('work order dashboard rejects future month and clamps to current', function () {
    $future = now()->addMonth()->format('Y-m');

    $this->get(route('analytics.work-order', ['month' => $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order')
            ->where('filters.month', now()->format('Y-m'))
            ->where('navigation.isCurrentMonth', true)
            ->where('navigation.nextMonth', null)
        );
});
