<?php

use App\Models\Employee;
use App\Models\Position;
use App\Support\SpkCraftsmanPerformanceAnalytics;
use Illuminate\Support\Facades\DB;

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
            ->has('analytics.ranking.0.spkCount')
            ->has('analytics.ranking.0.position')
            ->has('analytics.ranking.0.jobsByProcess')
            ->has('analytics.jobProcesses')
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

test('craftsman performance ranking shows the linked employee position', function () {
    $report = (new SpkCraftsmanPerformanceAnalytics)->summarize();
    $names = array_values(array_filter(array_column($report['ranking'], 'name')));

    expect($names)->not->toBeEmpty();

    $craftsmen = DB::connection('third')
        ->table('mscraftsman')
        ->whereIn('name', $names)
        ->where('is_deleted', 0)
        ->get(['row_id', 'name']);

    expect($craftsmen)->not->toBeEmpty();

    $targetName = (string) $craftsmen->first()->name;
    $targetIds = $craftsmen
        ->where('name', $targetName)
        ->pluck('row_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    $position = Position::factory()->create([
        'name' => 'Tukang Pasang Batu',
    ]);
    $employee = Employee::factory()->create([
        'position_id' => $position->id,
    ]);

    DB::connection('third')->beginTransaction();

    try {
        DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $targetIds)
            ->update(['employee_id' => $employee->id]);

        $linked = (new SpkCraftsmanPerformanceAnalytics)->summarize();
        $linkedRows = array_values(array_filter(
            $linked['ranking'],
            fn (array $row): bool => $row['name'] === $targetName,
        ));

        expect($linkedRows)->not->toBeEmpty()
            ->and(collect($linkedRows)->every(
                fn (array $row): bool => $row['position'] === 'Tukang Pasang Batu',
            ))->toBeTrue();

        DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $targetIds)
            ->update(['employee_id' => null]);

        $unlinked = (new SpkCraftsmanPerformanceAnalytics)->summarize();
        $unlinkedRows = array_values(array_filter(
            $unlinked['ranking'],
            fn (array $row): bool => $row['name'] === $targetName,
        ));

        expect($unlinkedRows)->not->toBeEmpty()
            ->and(collect($unlinkedRows)->every(
                fn (array $row): bool => $row['position'] === '-',
            ))->toBeTrue();
    } finally {
        DB::connection('third')->rollBack();
    }
});
