<?php

use App\Support\RequestOrderRepository;
use App\Support\SpkApprovalService;
use App\Support\SpkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('request order repository resolves trans date by doc no', function () {
    $docNo = 'DP-TEST-'.Str::upper(Str::random(6));
    $orderId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-07-01',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $transDate = app(RequestOrderRepository::class)->transDateByDocNo($docNo);

    expect($transDate)->toStartWith('2026-07-01');

    DB::connection('second')->table('request_order')->where('row_id', $orderId)->delete();
});

test('manager approved at is empty before manager approval', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(83000, 83999)),
    ]);

    expect($service->managerApprovedAt($production))->toBe('-');

    $production->delete();
});

test('manager approved at uses manager approve log date', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(84000, 84999)),
    ]);

    $service->managerApprove($production, 'manager-tester', 'OK');

    expect($service->managerApprovedAt($production->refresh()))->toBe(now()->format('d-M-Y'))
        ->and($service->managerApprovedAt($production, 'd/m/Y'))->toBe(now()->format('d/m/Y'));

    $production->delete();
});
