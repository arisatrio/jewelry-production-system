<?php

use App\Support\RequestOrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('request order repository formats pesanan label with payment status', function () {
    $repository = app(RequestOrderRepository::class);

    expect($repository->pesananDisplayLabel('DP-0009303', 'Vera', 1))
        ->toBe('DP-0009303 (Vera) (Lunas)')
        ->and($repository->pesananDisplayLabel('DP-0009423', 'Annisa', 0))
        ->toBe('DP-0009423 (Annisa) (Belum Lunas)')
        ->and($repository->pesananDisplayLabel('RP-0003554', 'Customer', null))
        ->toBe('RP-0003554 (Customer)');
});

test('request order repository resolves display label by doc no', function () {
    $docNo = 'DP-TEST-'.Str::upper(Str::random(8));
    $rowId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => 'CUSTOM',
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => 1,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    $label = app(RequestOrderRepository::class)->displayLabelByDocNo($docNo, 'Vera');

    expect($label)->toBe("{$docNo} (Vera) (Lunas)");

    DB::connection('second')->table('request_order')->where('row_id', $rowId)->delete();
});
