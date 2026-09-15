<?php

use App\Support\SpkProcessSlaResolver;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('h1 threshold is one working day before process sla deadline', function () {
    $resolver = new SpkProcessSlaResolver;
    // Senin + 2 HK → deadline Rabu; H-1 = Selasa
    $start = Carbon::parse('2026-09-14'); // Senin

    expect($resolver->h1ThresholdDate($start, 2)->toDateString())->toBe('2026-09-15')
        ->and($resolver->h1ThresholdDate($start, 1)->toDateString())->toBe('2026-09-14');
});

test('is at or past h1 includes threshold day and overdue days', function () {
    $resolver = new SpkProcessSlaResolver;
    $start = Carbon::parse('2026-09-14'); // Senin, SLA 2 HK

    expect($resolver->isAtOrPastH1($start, 2, Carbon::parse('2026-09-14')))->toBeFalse()
        ->and($resolver->isAtOrPastH1($start, 2, Carbon::parse('2026-09-15')))->toBeTrue()
        ->and($resolver->isAtOrPastH1($start, 2, Carbon::parse('2026-09-16')))->toBeTrue()
        ->and($resolver->isAtOrPastH1($start, 0, Carbon::parse('2026-09-20')))->toBeFalse();
});

test('h1 threshold skips weekends when counting working days', function () {
    $resolver = new SpkProcessSlaResolver;
    // Jumat + 2 HK → H-1 = Senin berikutnya
    $start = Carbon::parse('2026-09-11'); // Jumat

    expect($resolver->h1ThresholdDate($start, 2)->toDateString())->toBe('2026-09-14');
});

test('is past sla only after the process sla deadline day', function () {
    $resolver = new SpkProcessSlaResolver;
    $start = Carbon::parse('2026-09-14'); // Senin, SLA 2 HK → deadline Rabu 16

    expect($resolver->slaDeadlineDate($start, 2)->toDateString())->toBe('2026-09-16')
        ->and($resolver->isPastSla($start, 2, Carbon::parse('2026-09-16')))->toBeFalse()
        ->and($resolver->isPastSla($start, 2, Carbon::parse('2026-09-17')))->toBeTrue()
        ->and($resolver->isPastSla($start, 0, Carbon::parse('2026-09-20')))->toBeFalse();
});

test('sla deadline remaining calendar days can be positive or negative', function () {
    $resolver = new SpkProcessSlaResolver;
    $start = Carbon::parse('2026-09-14');
    $deadline = $resolver->slaDeadlineDate($start, 2)->startOfDay();

    expect((int) Carbon::parse('2026-09-15')->diffInDays($deadline, false))->toBe(1)
        ->and((int) Carbon::parse('2026-09-16')->diffInDays($deadline, false))->toBe(0)
        ->and((int) Carbon::parse('2026-09-17')->diffInDays($deadline, false))->toBe(-1);
});
