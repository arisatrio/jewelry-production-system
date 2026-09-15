<?php

use App\Models\SpkProcessSlaTarget;
use App\Support\SpkProcessMapper;
use App\Support\SpkProcessSlaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::forget(SpkProcessSlaResolver::CACHE_KEY);
});

test('resolver returns config defaults when database is empty', function () {
    $resolver = app(SpkProcessSlaResolver::class);
    $targets = $resolver->targets();
    $defaults = config('spk_processes.sla_defaults');

    expect($targets)->toHaveKey('JewelCAD')
        ->and($targets['JewelCAD'])->toBe((int) $defaults['JewelCAD'])
        ->and($targets['Finishing'])->toBe((int) $defaults['Finishing'])
        ->and($resolver->totalWorkingDays())->toBe(array_sum(array_map('intval', $defaults)));
});

test('resolver prefers database values over config defaults', function () {
    SpkProcessSlaTarget::query()->create([
        'process_key' => 'JewelCAD',
        'working_days' => 9,
        'updated_by' => 'tester',
    ]);

    Cache::forget(SpkProcessSlaResolver::CACHE_KEY);

    $resolver = app(SpkProcessSlaResolver::class);

    expect($resolver->targetFor('JewelCAD'))->toBe(9)
        ->and($resolver->targetFor('Resin'))->toBe((int) config('spk_processes.sla_defaults.Resin'));
});

test('resolver sync upserts all process keys and clears cache', function () {
    $resolver = app(SpkProcessSlaResolver::class);
    $keys = array_column(app(SpkProcessMapper::class)->tabs(), 'key');
    $payload = [];

    foreach ($keys as $index => $key) {
        $payload[$key] = $index + 1;
    }

    $resolver->sync($payload, 'system');

    expect(SpkProcessSlaTarget::query()->count())->toBe(count($keys))
        ->and($resolver->targets())->toBe($payload)
        ->and($resolver->forSettings()[0])->toMatchArray([
            'processKey' => $keys[0],
            'workingDays' => 1,
            'source' => 'database',
            'updatedBy' => 'system',
        ]);
});

test('settings rows include every configured production process', function () {
    $rows = app(SpkProcessSlaResolver::class)->forSettings();
    $labels = array_column(app(SpkProcessMapper::class)->tabs(), 'label');

    expect($rows)->toHaveCount(count($labels))
        ->and(array_column($rows, 'label'))->toBe($labels);
});
