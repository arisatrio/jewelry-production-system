<?php

use App\Models\SpkProcessSlaTarget;
use App\Support\SpkProcessMapper;
use App\Support\SpkProcessSlaResolver;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget(SpkProcessSlaResolver::CACHE_KEY);
    SpkProcessSlaTarget::query()->delete();
});

afterEach(function (): void {
    SpkProcessSlaTarget::query()->delete();
    Cache::forget(SpkProcessSlaResolver::CACHE_KEY);
});

test('spk process sla edit page is accessible', function () {
    $this->get(route('master-data.spk-process-sla.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/spk-process-sla/edit')
            ->has('targets')
            ->has('totalWorkingDays')
            ->where('targets.0.processKey', 'JewelCAD')
        );
});

test('spk process sla targets can be updated', function () {
    $keys = array_column(app(SpkProcessMapper::class)->tabs(), 'key');
    $targets = [];

    foreach ($keys as $index => $key) {
        $targets[] = [
            'process_key' => $key,
            'working_days' => $index + 3,
        ];
    }

    $this->put(route('master-data.spk-process-sla.update'), [
        'targets' => $targets,
    ])
        ->assertRedirect(route('master-data.spk-process-sla.edit'));

    expect(SpkProcessSlaTarget::query()->count())->toBe(count($keys))
        ->and(SpkProcessSlaTarget::query()->where('process_key', 'JewelCAD')->value('working_days'))->toBe(3)
        ->and(app(SpkProcessSlaResolver::class)->targetFor('JewelCAD'))->toBe(3);
});

test('spk process sla update validates working days range', function () {
    $keys = array_column(app(SpkProcessMapper::class)->tabs(), 'key');
    $targets = [];

    foreach ($keys as $key) {
        $targets[] = [
            'process_key' => $key,
            'working_days' => 1,
        ];
    }

    $targets[0]['working_days'] = 999;

    $this->from(route('master-data.spk-process-sla.edit'))
        ->put(route('master-data.spk-process-sla.update'), [
            'targets' => $targets,
        ])
        ->assertRedirect(route('master-data.spk-process-sla.edit'))
        ->assertSessionHasErrors('targets.0.working_days');
});

test('spk process sla update requires every process', function () {
    $this->from(route('master-data.spk-process-sla.edit'))
        ->put(route('master-data.spk-process-sla.update'), [
            'targets' => [
                [
                    'process_key' => 'JewelCAD',
                    'working_days' => 2,
                ],
            ],
        ])
        ->assertRedirect(route('master-data.spk-process-sla.edit'))
        ->assertSessionHasErrors('targets');
});
