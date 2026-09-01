<?php

use App\Support\SpkOrderPriorityResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array{docNo: string, rowId: int}
 */
function createRequestOrderForPriorityTest(string $typeOrder, ?int $isFullyPaid): array
{
    $docNo = 'DP-TEST-'.Str::upper(Str::random(8));

    $rowId = DB::connection('second')->table('request_order')->insertGetId([
        'company_id' => 1,
        'doc_no' => $docNo,
        'trans_date' => '2026-08-01',
        'type_order' => $typeOrder,
        'online_offline' => 'OFFLINE',
        'is_sales_saved' => 0,
        'is_submitted' => 0,
        'is_deleted' => 0,
        'is_fully_paid' => $isFullyPaid,
        'created_date' => now(),
        'created_by' => 'system',
    ]);

    return [
        'docNo' => $docNo,
        'rowId' => $rowId,
    ];
}

test('order priority resolver returns high for custom pesanan that is fully paid', function () {
    $order = createRequestOrderForPriorityTest('CUSTOM', 1);

    $priority = app(SpkOrderPriorityResolver::class)->resolve('Pesanan', $order['docNo']);

    expect($priority)->toBe([
        'level' => SpkOrderPriorityResolver::LEVEL_HIGH,
        'label' => 'Prioritas Tinggi',
    ]);

    DB::connection('second')->table('request_order')->where('row_id', $order['rowId'])->delete();
});

test('order priority resolver returns medium for custom pesanan that is not fully paid', function () {
    $order = createRequestOrderForPriorityTest('DP PO', 0);

    $priority = app(SpkOrderPriorityResolver::class)->resolve('Pesanan', $order['docNo']);

    expect($priority)->toBe([
        'level' => SpkOrderPriorityResolver::LEVEL_MEDIUM,
        'label' => 'Prioritas Sedang',
    ]);

    DB::connection('second')->table('request_order')->where('row_id', $order['rowId'])->delete();
});

test('order priority resolver returns low for nabung bareng pesanan', function () {
    $order = createRequestOrderForPriorityTest('Nabung Bareng', 1);

    $priority = app(SpkOrderPriorityResolver::class)->resolve('Pesanan', $order['docNo']);

    expect($priority)->toBe([
        'level' => SpkOrderPriorityResolver::LEVEL_LOW,
        'label' => 'Prioritas Rendah',
    ]);

    DB::connection('second')->table('request_order')->where('row_id', $order['rowId'])->delete();
});

test('order priority resolver returns null for non pesanan spk', function () {
    $order = createRequestOrderForPriorityTest('CUSTOM', 1);

    expect(app(SpkOrderPriorityResolver::class)->resolve('Stock', $order['docNo']))->toBeNull();

    DB::connection('second')->table('request_order')->where('row_id', $order['rowId'])->delete();
});

test('order priority resolver returns null when payment status is unknown', function () {
    $order = createRequestOrderForPriorityTest('CUSTOM', null);

    expect(app(SpkOrderPriorityResolver::class)->resolve('Pesanan', $order['docNo']))->toBeNull();

    DB::connection('second')->table('request_order')->where('row_id', $order['rowId'])->delete();
});
