<?php

use App\Support\GoldMaterialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    foreach (['msperiod', 'msmaterialgold', 'mstranstype', 'trmaterialgold'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    DB::connection('third')->beginTransaction();
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

test('ledger paginates every transaction type and calculates signed totals', function () {
    $connection = DB::connection('third');
    $period = app(GoldMaterialLedger::class)->activePeriod();
    $materialName = 'Gold Ledger Test '.Str::uuid();
    $materialId = (int) $connection->table('msmaterialgold')->insertGetId([
        'name' => $materialName,
        'is_deleted' => 0,
    ]);

    foreach ([[1, '10.00'], [12, '3.00'], [13, '2.00']] as [$typeId, $weight]) {
        $connection->table('trmaterialgold')->insert([
            'period_id' => $period['id'],
            'transtype_id' => $typeId,
            'ref_row_id' => 0,
            'materialgold_id' => $materialId,
            'spk_id' => 0,
            'weight' => $weight,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'tester',
        ]);
    }

    $ledger = app(GoldMaterialLedger::class);
    $transactions = $ledger->paginate(
        $period['id'],
        $materialName,
        null,
        null,
        10,
    );

    expect($transactions->total())->toBe(3)
        ->and($transactions->items()[0]['category'])->toBe('Pemakaian')
        ->and($transactions->items()[0]['signedWeight'])->toBe('-2.00')
        ->and($transactions->items()[1]['category'])->toBe('Penambahan')
        ->and($transactions->items()[1]['signedWeight'])->toBe('3.00')
        ->and($transactions->items()[2]['category'])->toBe('Pemakaian')
        ->and($transactions->items()[2]['signedWeight'])->toBe('-10.00')
        ->and($ledger->total($period['id'], $materialName, null, null))->toBe('-9.00');
});

test('ledger stores append-only additions and deductions in the active period', function (
    string $kind,
    int $expectedType,
) {
    $connection = DB::connection('third');
    $ledger = app(GoldMaterialLedger::class);
    $period = $ledger->activePeriod();
    $materialId = (int) $connection->table('msmaterialgold')->insertGetId([
        'name' => 'Gold Ledger Store Test '.Str::uuid(),
        'is_deleted' => 0,
    ]);

    $ledger->store(
        $period['id'],
        $kind,
        $materialId,
        '12.34',
        'Adjustment test',
        'tester',
    );

    $transaction = $connection->table('trmaterialgold')
        ->where('materialgold_id', $materialId)
        ->first();

    expect((int) $transaction->transtype_id)->toBe($expectedType)
        ->and((int) $transaction->period_id)->toBe($period['id'])
        ->and((int) $transaction->ref_row_id)->toBe(0)
        ->and((int) $transaction->spk_id)->toBe(0)
        ->and((string) $transaction->weight)->toBe('12.34');
})->with([
    'addition' => ['addition', GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID],
    'deduction that may make stock negative' => [
        'deduction',
        GoldMaterialLedger::DEDUCTION_TRANSACTION_TYPE_ID,
    ],
]);
