<?php

use App\Models\Production;
use App\Support\SpkApprovalService;
use App\Support\SpkStatusMapper;
use Tests\TestCase;

uses(TestCase::class);

test('spk status mapper classifies draft when status empty', function () {
    $production = new Production([
        'status' => '',
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    $mapped = (new SpkStatusMapper)->map($production, false);

    expect($mapped['key'])->toBe(SpkStatusMapper::KEY_DRAFT)
        ->and($mapped['label'])->toBe('Draft')
        ->and($mapped['stageIndex'])->toBe(0)
        ->and($mapped['isOverdue'])->toBeFalse()
        ->and($mapped['stages'])->toHaveCount(4)
        ->and($mapped['stages'][1]['key'])->toBe('confirmed')
        ->and($mapped['stages'][2]['key'])->toBe('inProgress')
        ->and($mapped['stages'][3]['key'])->toBe('done');
});

test('spk status mapper classifies draft for pending manager SPK010', function () {
    $production = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    expect((new SpkStatusMapper)->resolveKey($production, false))->toBe(SpkStatusMapper::KEY_DRAFT)
        ->and((new SpkStatusMapper)->isPendingManager($production, false))->toBeTrue()
        ->and((new SpkStatusMapper)->labelFor(SpkStatusMapper::KEY_DRAFT))->toBe('Draft');
});

test('spk status mapper classifies confirmed for SPKDONE without process', function () {
    $production = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    expect((new SpkStatusMapper)->resolveKey($production, false))->toBe(SpkStatusMapper::KEY_CONFIRMED)
        ->and((new SpkStatusMapper)->isConfirmed($production, false))->toBeTrue()
        ->and((new SpkStatusMapper)->isManagerApproved($production, false))->toBeTrue();
});

test('spk status mapper does not treat repeat order as confirmed without SPKDONE', function () {
    $production = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'status_order' => 'RO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    $mapped = (new SpkStatusMapper)->map($production, false);

    expect($mapped['key'])->toBe(SpkStatusMapper::KEY_DRAFT)
        ->and($mapped['label'])->toBe('Draft')
        ->and($mapped['stageIndex'])->toBe(0)
        ->and((new SpkStatusMapper)->isManagerApproved($production, false))->toBeFalse()
        ->and((new SpkStatusMapper)->isConfirmed($production, false))->toBeFalse();
});

test('spk status mapper classifies in progress when process started', function () {
    $byLastProcess = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'status_order' => 'NO',
        'last_process' => 'Coran',
        'is_inprocess' => 0,
    ]);
    $byLastProcess->row_id = 1;

    $byFlag = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 1,
    ]);
    $byFlag->row_id = 2;

    $done = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'RO',
        'last_process' => 'Poles Chrome',
        'is_inprocess' => 1,
    ]);
    $done->row_id = 3;

    $mapper = new SpkStatusMapper;

    expect($mapper->resolveKey($byLastProcess, false))->toBe(SpkStatusMapper::KEY_IN_PROGRESS)
        ->and($mapper->resolveKey($byFlag, false))->toBe(SpkStatusMapper::KEY_IN_PROGRESS)
        ->and($mapper->resolveKey($done, false))->toBe(SpkStatusMapper::KEY_IN_PROGRESS)
        ->and($mapper->resolveKey($done, true))->toBe(SpkStatusMapper::KEY_DONE)
        ->and($mapper->stageIndexFor(SpkStatusMapper::KEY_IN_PROGRESS))->toBe(2);
});

test('spk status mapper marks overdue only for approved or in progress past delivery', function () {
    $pending = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'estimated_delivery_time' => now()->subDay()->startOfDay(),
    ]);
    $pending->row_id = 1;

    $confirmed = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'RO',
        'last_process' => null,
        'is_inprocess' => 0,
        'estimated_delivery_time' => now()->subDays(3)->startOfDay(),
    ]);
    $confirmed->row_id = 2;

    $inProgress = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'last_process' => 'Finishing',
        'is_inprocess' => 1,
        'estimated_delivery_time' => now()->subDay()->startOfDay(),
    ]);
    $inProgress->row_id = 3;

    $mapper = new SpkStatusMapper;

    expect($mapper->isOverdue($pending, false))->toBeFalse()
        ->and($mapper->isOverdue($confirmed, false))->toBeTrue()
        ->and($mapper->isOverdue($inProgress, false))->toBeTrue()
        ->and($mapper->isOverdue($confirmed, true))->toBeFalse();
});

test('spk status mapper maps a real production record from third database', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $mapped = (new SpkStatusMapper)->map($production);

    expect($mapped)->toHaveKeys(['key', 'label', 'stageIndex', 'isOverdue', 'stages'])
        ->and($mapped['key'])->toBeIn([
            SpkStatusMapper::KEY_DRAFT,
            SpkStatusMapper::KEY_CONFIRMED,
            SpkStatusMapper::KEY_IN_PROGRESS,
            SpkStatusMapper::KEY_DONE,
        ])
        ->and($mapped['stageIndex'])->toBe((new SpkStatusMapper)->stageIndexFor($mapped['key']))
        ->and($mapped['label'])->toBe((new SpkStatusMapper)->labelFor($mapped['key']));
});
