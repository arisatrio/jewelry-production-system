<?php

use App\Support\RequestOrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('request order repository formats pesanan label with payment status', function () {
    $repository = app(RequestOrderRepository::class);

    expect($repository->pesananHeading('Shasya'))
        ->toBe('Pesanan (Sales: Shasya)')
        ->and($repository->pesananHeading('  '))
        ->toBe('Pesanan')
        ->and($repository->pesananHeading(null))
        ->toBe('Pesanan')
        ->and($repository->pesananDisplayLabel('DP-0009303', 'Vera', 1))
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
    $rows = app(RequestOrderRepository::class)->rowsByDocNos([$docNo, 'MISSING-DOC']);

    expect($label)->toBe("{$docNo} (Vera) (Lunas)")
        ->and($rows)->toHaveKey($docNo)
        ->and($rows)->not->toHaveKey('MISSING-DOC')
        ->and((string) $rows[$docNo]->doc_no)->toBe($docNo);

    DB::connection('second')->table('request_order')->where('row_id', $rowId)->delete();
});

test('request order repository resolves sales name from sales id', function () {
    $salesName = 'Sales '.Str::upper(Str::random(6));
    $docNo = 'DP-TEST-'.Str::upper(Str::random(8));
    $salesId = null;
    $rowId = null;

    try {
        $salesId = DB::connection('second')->table('sysuser')->insertGetId([
            'company_id' => 1,
            'name' => $salesName,
            'is_login' => 0,
            'is_deleted' => 0,
        ]);
        $rowId = DB::connection('second')->table('request_order')->insertGetId([
            'company_id' => 1,
            'doc_no' => $docNo,
            'sales_id' => $salesId,
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

        $repository = app(RequestOrderRepository::class);
        $order = $repository->findByDocNo($docNo);
        $match = $repository->search($docNo)->firstWhere('docNo', $docNo);

        expect($order)->not->toBeNull()
            ->and($order['sales'])->toBe($salesName)
            ->and($match)->not->toBeNull()
            ->and($match['sales'])->toBe($salesName);
    } finally {
        if ($rowId !== null) {
            DB::connection('second')->table('request_order')->where('row_id', $rowId)->delete();
        }

        if ($salesId !== null) {
            DB::connection('second')->table('sysuser')->where('row_id', $salesId)->delete();
        }
    }
});
