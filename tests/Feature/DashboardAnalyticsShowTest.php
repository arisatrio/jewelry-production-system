<?php

test('home dashboard includes analytics payload', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('analytics.period.label')
            ->has('analytics.summary.totalSpk')
            ->has('analytics.summary.draftSpk')
            ->has('analytics.summary.confirmedSpk')
            ->has('analytics.summary.inProgressSpk')
            ->has('analytics.summary.doneSpk')
            ->has('analytics.summary.overdueSpk')
            ->has('analytics.summary.goldRequirement')
            ->has('analytics.summary.forecastSpk')
            ->has('analytics.summary.planningDoneSpk')
            ->has('analytics.summary.planningPendingSpk')
            ->has('analytics.summary.todayTargetSpk')
            ->has('analytics.summary.todayCreatedSpk')
            ->has('analytics.summary.todayInProcessSpk')
            ->has('analytics.summary.monthOverdueSpk')
            ->has('analytics.today.targetSpk')
            ->has('analytics.today.createdSpk')
            ->has('analytics.today.inProcessSpk')
            ->has('analytics.today.overdueSpk')
            ->has('analytics.todayLists.todayTarget')
            ->has('analytics.todayLists.todayInProcess')
            ->has('analytics.todayLists.todayCreated')
            ->has('analytics.todayLists.monthOverdue')
            ->has('analytics.planningDaily.days')
            ->has('analytics.statusLists.draft')
            ->has('analytics.statusLists.confirmed')
            ->has('analytics.statusLists.inProgress')
            ->has('analytics.statusLists.overdue')
            ->has('analytics.statusLists.done')
            ->has('analytics.productionTypes')
            ->has('analytics.inProgressByProcess')
            ->has('analytics.itemDistribution')
            ->has('analytics.shrink.byProcess')
            ->has('analytics.control.avgLeadTimeDays')
            ->has('analytics.craftsmen')
            ->has('analytics.gold.issued')
            ->has('analytics.stone.startCrt')
            ->has('analytics.forecast.byItem')
            ->has('analytics.forecast.byType')
            ->has('analytics.forecast.types')
            ->has('analytics.forecast.byItemType')
            ->has('filters.month')
            ->has('navigation.previousMonth')
            ->has('navigation.currentMonth')
            ->where('navigation.nextMonth', null)
            ->where('navigation.isCurrentMonth', true)
        );
});

test('home dashboard can paginate to a previous month', function () {
    $this->get(route('home', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.previousMonth', '2026-02')
            ->where('navigation.nextMonth', '2026-04')
            ->where('navigation.isCurrentMonth', false)
            ->has('analytics.summary.totalSpk')
            ->has('analytics.forecast.spkCount')
        );
});

test('home dashboard rejects future month and clamps to current', function () {
    $future = now()->addMonth()->format('Y-m');

    $this->get(route('home', ['month' => $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('filters.month', now()->format('Y-m'))
            ->where('navigation.isCurrentMonth', true)
            ->where('navigation.nextMonth', null)
        );
});
