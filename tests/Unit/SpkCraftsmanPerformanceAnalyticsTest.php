<?php

use App\Support\SpkCraftsmanPerformanceAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('craftsman performance analytics returns ranking payload', function () {
    $report = (new SpkCraftsmanPerformanceAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys(['period', 'summary', 'ranking', 'byProcess'])
        ->and($report['period']['start'])->toBe('2026-03-01')
        ->and($report['summary'])->toHaveKeys([
            'activeCraftsmen',
            'totalJobs',
            'totalShrink',
            'avgJobsPerCraftsman',
            'topCraftsman',
            'topCraftsmanJobs',
            'heaviestShrinkCraftsman',
            'heaviestShrink',
        ])
        ->and($report['summary']['activeCraftsmen'])->toBe(count($report['ranking']))
        ->and($report['ranking'])->toBeArray()
        ->and($report['byProcess'])->toBeArray();
});
