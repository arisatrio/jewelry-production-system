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

    $mapped = (new SpkStatusMapper)->map($production);

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

    expect((new SpkStatusMapper)->resolveKey($production))->toBe(SpkStatusMapper::KEY_DRAFT)
        ->and((new SpkStatusMapper)->isPendingManager($production))->toBeTrue()
        ->and((new SpkStatusMapper)->labelFor(SpkStatusMapper::KEY_DRAFT))->toBe('Draft');
});

test('spk status mapper keeps draft for RO/PO until manager approves', function () {
    $production = new Production([
        'status' => '',
        'status_order' => 'RO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    expect((new SpkStatusMapper)->resolveKey($production))->toBe(SpkStatusMapper::KEY_DRAFT);
});

test('spk status mapper classifies confirmed after manager approves SPKDONE', function () {
    $production = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'RO',
        'last_process' => null,
        'is_inprocess' => 0,
    ]);
    $production->row_id = 1;

    $mapped = (new SpkStatusMapper)->map($production);

    expect($mapped['key'])->toBe(SpkStatusMapper::KEY_CONFIRMED)
        ->and($mapped['label'])->toBe('Confirmed')
        ->and($mapped['stageIndex'])->toBe(1)
        ->and((new SpkStatusMapper)->isManagerApproved($production))->toBeTrue()
        ->and((new SpkStatusMapper)->isConfirmed($production))->toBeTrue();
});

test('spk status mapper classifies in progress after approve when process started', function () {
    $byLastProcess = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'NO',
        'last_process' => 'Coran',
        'is_inprocess' => 0,
    ]);
    $byLastProcess->row_id = 1;

    $byFlag = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'status_order' => 'NO',
        'last_process' => null,
        'is_inprocess' => 1,
    ]);
    $byFlag->row_id = 2;

    $beforeApprove = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'last_process' => 'Coran',
        'is_inprocess' => 1,
    ]);
    $beforeApprove->row_id = 3;

    $mapper = new SpkStatusMapper;

    expect($mapper->resolveKey($byLastProcess))->toBe(SpkStatusMapper::KEY_IN_PROGRESS)
        ->and($mapper->resolveKey($byFlag))->toBe(SpkStatusMapper::KEY_IN_PROGRESS)
        ->and($mapper->resolveKey($beforeApprove))->toBe(SpkStatusMapper::KEY_DRAFT)
        ->and($mapper->stageIndexFor(SpkStatusMapper::KEY_IN_PROGRESS))->toBe(2);
});

test('spk status mapper marks overdue when estimated delivery is past and not SPKDONE', function () {
    $production = new Production([
        'status' => SpkApprovalService::STATUS_PENDING,
        'estimated_delivery_time' => now()->subDay()->startOfDay(),
    ]);
    $production->row_id = 1;

    $done = new Production([
        'status' => SpkApprovalService::STATUS_DONE,
        'estimated_delivery_time' => now()->subDays(3)->startOfDay(),
    ]);
    $done->row_id = 2;

    $mapper = new SpkStatusMapper;

    expect($mapper->isOverdue($production))->toBeTrue()
        ->and($mapper->map($production)['isOverdue'])->toBeTrue()
        ->and($mapper->isOverdue($done))->toBeFalse();
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
