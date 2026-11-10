<?php

use App\Support\SpkCraftsmanPerformanceAnalytics;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('craftsman performance analytics returns ranking payload', function () {
    $report = (new SpkCraftsmanPerformanceAnalytics(Carbon::parse('2026-03-01')))->summarize();

    expect($report)->toHaveKeys(['period', 'summary', 'ranking', 'jobProcesses', 'byProcess'])
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
        ->and($report['summary']['activeCraftsmen'])->toBe(
            count(array_filter(
                $report['ranking'],
                static fn (array $row): bool => $row['jobCount'] > 0,
            )),
        )
        ->and($report['ranking'])->toBeArray()
        ->and($report['byProcess'])->toBeArray();

    if ($report['ranking'] !== []) {
        expect($report['ranking'][0])->toHaveKeys([
            'craftsmanId',
            'name',
            'position',
            'labelTukang',
            'jobCount',
            'jobsByProcess',
            'spkCount',
            'totalShrink',
            'avgShrink',
            'avgShrinkPercent',
        ])
            ->and($report['ranking'][0]['position'])->toBeString()
            ->and($report['ranking'][0]['position'])->not->toBe('')
            ->and($report['ranking'][0]['jobsByProcess'])->toBeArray()
            ->and($report['ranking'][0]['spkCount'])->toBeInt()
            ->and($report['ranking'][0]['spkCount'])->toBeLessThanOrEqual($report['ranking'][0]['jobCount']);

        if ($report['byProcess'] !== []) {
            expect($report['byProcess'][0])->toHaveKeys([
                'process',
                'jobCount',
                'reparasiJobCount',
                'nonReparasiJobCount',
                'craftsmanCount',
                'totalShrink',
            ])
                ->and($report['byProcess'][0]['reparasiJobCount'] + $report['byProcess'][0]['nonReparasiJobCount'])
                ->toBe($report['byProcess'][0]['jobCount']);
        }

        foreach ($report['jobProcesses'] as $process) {
            expect($process)->toHaveKeys(['key', 'label']);
        }

        $processKeys = array_column($report['jobProcesses'], 'key');

        expect(array_keys($report['ranking'][0]['jobsByProcess']))->toEqual($processKeys)
            ->and(array_sum($report['ranking'][0]['jobsByProcess']))->toBe($report['ranking'][0]['jobCount']);
    }
});
