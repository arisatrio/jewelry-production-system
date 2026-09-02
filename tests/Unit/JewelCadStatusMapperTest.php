<?php

use App\Models\JewelCadRequest;
use App\Support\JewelCadApprovalService;
use App\Support\JewelCadStatusMapper;
use Tests\TestCase;

uses(TestCase::class);

test('jewelcad status mapper maps draft request to first stage', function () {
    $request = new JewelCadRequest(['status' => 'DRAFT']);
    $request->row_id = 1;

    $mapped = (new JewelCadStatusMapper(app(JewelCadApprovalService::class)))->map($request);

    expect($mapped)->toHaveKeys(['key', 'label', 'stageIndex', 'stages'])
        ->and($mapped['key'])->toBe(JewelCadStatusMapper::KEY_DRAFT)
        ->and($mapped['label'])->toBe('Draft')
        ->and($mapped['stageIndex'])->toBe(0)
        ->and($mapped['stages'])->toHaveCount(4)
        ->and($mapped['stages'][0]['key'])->toBe('draft')
        ->and($mapped['stages'][1]['key'])->toBe('submitted')
        ->and($mapped['stages'][2]['key'])->toBe('manager')
        ->and($mapped['stages'][3]['key'])->toBe('done');
});

test('jewelcad status mapper maps submitted request to pengajuan approval stage', function () {
    $request = new JewelCadRequest([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);
    $request->row_id = 2;

    $mapped = (new JewelCadStatusMapper(app(JewelCadApprovalService::class)))->map($request);

    expect($mapped['key'])->toBe(JewelCadStatusMapper::KEY_SUBMITTED)
        ->and($mapped['label'])->toBe('Pengajuan Approval')
        ->and($mapped['stageIndex'])->toBe(1);
});

test('jewelcad status mapper maps manager approved request to serahkan ke jwcad stage', function () {
    $request = new JewelCadRequest([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);
    $request->row_id = 3;

    $mapped = (new JewelCadStatusMapper(app(JewelCadApprovalService::class)))->map($request);

    expect($mapped['key'])->toBe(JewelCadStatusMapper::KEY_MANAGER)
        ->and($mapped['label'])->toBe('Serahkan ke JWCAD')
        ->and($mapped['stageIndex'])->toBe(2);
});

test('jewelcad status mapper maps completed request to done stage', function () {
    $request = new JewelCadRequest([
        'status' => JewelCadApprovalService::STATUS_DONE,
    ]);
    $request->row_id = 4;

    $mapped = (new JewelCadStatusMapper(app(JewelCadApprovalService::class)))->map($request);

    expect($mapped['key'])->toBe(JewelCadStatusMapper::KEY_DONE)
        ->and($mapped['label'])->toBe('Done')
        ->and($mapped['stageIndex'])->toBe(3);
});
