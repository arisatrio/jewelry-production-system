<?php

test('yearly kpi dashboard includes analytics payload', function () {
    $this->get(route('analytics.yearly-kpi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/yearly-kpi')
            ->has('analytics.period.label')
            ->has('analytics.period.year')
            ->has('analytics.summary.createdSpk')
            ->has('analytics.summary.createdQty')
            ->has('analytics.summary.completedSpk')
            ->has('analytics.summary.completedQty')
            ->has('analytics.summary.doneRangkaSpk')
            ->has('analytics.summary.doneBarangJadiSpk')
            ->has('analytics.summary.pluIssuedSpk')
            ->has('analytics.summary.pluDatedBy')
            ->has('analytics.summary.inProgressSpk')
            ->has('analytics.summary.waitingApprovalSpk')
            ->has('analytics.summary.approvedNotProcessedSpk')
            ->has('analytics.summary.pendingProcessSpk')
            ->has('analytics.breakdown.createdByProductionType')
            ->has('analytics.breakdown.pending')
            ->has('analytics.breakdown.inProgressByProcess')
            ->has('analytics.breakdown.completedPlu')
            ->has('filters.year')
            ->where('navigation.isCurrentYear', true)
        );
});

test('yearly kpi dashboard can navigate to a previous year', function () {
    $this->get(route('analytics.yearly-kpi', ['year' => '2025']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/yearly-kpi')
            ->where('filters.year', '2025')
            ->where('analytics.period.start', '2025-01-01')
            ->where('navigation.nextYear', '2026')
        );
});

test('yearly kpi dashboard rejects future years', function () {
    $future = (int) now()->format('Y') + 2;

    $this->get(route('analytics.yearly-kpi', ['year' => (string) $future]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.year', now()->format('Y'))
            ->where('navigation.isCurrentYear', true)
        );
});
