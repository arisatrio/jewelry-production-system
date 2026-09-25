<?php

test('work order kanban dashboard includes analytics payload', function () {
    $this->get(route('analytics.work-order-kanban'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order-kanban')
            ->has('analytics.period.label')
            ->where('analytics.backlogYear', (int) now()->year)
            ->has('analytics.summary.totalSpk')
            ->has('analytics.summary.draftSpk')
            ->has('analytics.summary.confirmedSpk')
            ->has('analytics.summary.inProgressSpk')
            ->has('analytics.summary.doneSpk')
            ->has('analytics.summary.overdueSpk')
            ->has('analytics.statusLists.draft')
            ->has('analytics.statusLists.confirmed')
            ->has('analytics.statusLists.inProgress')
            ->has('analytics.statusLists.overdue')
            ->has('analytics.statusLists.doneRangka')
            ->has('analytics.statusLists.doneBarangJadi')
            ->has('processTabs')
            ->where('processTabs.0.key', 'JewelCAD')
            ->has('filters.month')
            ->has('navigation.previousMonth')
            ->has('navigation.currentMonth')
            ->where('navigation.nextMonth', null)
            ->where('navigation.isCurrentMonth', true)
        );
});

test('work order kanban dashboard can paginate to a previous month', function () {
    $this->get(route('analytics.work-order-kanban', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order-kanban')
            ->where('filters.month', '2026-03')
            ->where('analytics.period.start', '2026-03-01')
            ->where('navigation.previousMonth', '2026-02')
            ->where('navigation.nextMonth', '2026-04')
            ->where('navigation.isCurrentMonth', false)
        );
});

test('work order kanban dashboard rejects future month and clamps to current', function () {
    $future = now()->addMonth()->format('Y-m');

    $this->get(route('analytics.work-order-kanban', ['month' => $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order-kanban')
            ->where('filters.month', now()->format('Y-m'))
            ->where('navigation.isCurrentMonth', true)
            ->where('navigation.nextMonth', null)
        );
});
