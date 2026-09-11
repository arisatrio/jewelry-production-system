<?php

use App\Support\GoldMaterialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

function createGoldMaterialForTransactionTest(): int
{
    return (int) DB::connection('third')
        ->table('msmaterialgold')
        ->insertGetId([
            'name' => 'Gold Transaction Test '.Str::uuid(),
            'is_deleted' => 0,
        ]);
}

function activeGoldPeriodIdForTransactionTest(): int
{
    return (int) DB::connection('third')
        ->table('msperiod')
        ->where('is_active', 'YES')
        ->where('is_deleted', 0)
        ->value('row_id');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createGoldTransactionForTest(int $materialId, array $overrides = []): int
{
    return (int) DB::connection('third')
        ->table('trmaterialgold')
        ->insertGetId([
            'period_id' => activeGoldPeriodIdForTransactionTest(),
            'transtype_id' => GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID,
            'ref_row_id' => 0,
            'materialgold_id' => $materialId,
            'spk_id' => 0,
            'weight' => '10.00',
            'txt' => null,
            'notes' => null,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
            'modified_date' => '2026-09-09 10:00:00',
            'modified_by' => 'tester',
            'deleted_date' => null,
            'deleted_by' => null,
            ...$overrides,
        ]);
}

test('transaction index lists all transaction types and calculates signed total', function () {
    $materialId = createGoldMaterialForTransactionTest();
    createGoldTransactionForTest($materialId, [
        'transtype_id' => 1,
        'weight' => '10.00',
    ]);
    createGoldTransactionForTest($materialId, [
        'transtype_id' => GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID,
        'weight' => '3.00',
    ]);

    $materialName = DB::connection('third')
        ->table('msmaterialgold')
        ->where('row_id', $materialId)
        ->value('name');

    $this->get(route('inventory.gold-material-transactions.index', [
        'search' => $materialName,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-bahan-emas/index')
            ->has('transactions.data', 2)
            ->where('total', '-7.00')
            ->has('materialOptions')
            ->where(
                'activePeriod.id',
                activeGoldPeriodIdForTransactionTest(),
            )
        );
});

test('transaction index filters by created date', function () {
    $materialId = createGoldMaterialForTransactionTest();
    createGoldTransactionForTest($materialId, [
        'created_date' => '2026-08-31 23:59:59',
    ]);
    createGoldTransactionForTest($materialId, [
        'created_date' => '2026-09-09 10:00:00',
    ]);

    $materialName = DB::connection('third')
        ->table('msmaterialgold')
        ->where('row_id', $materialId)
        ->value('name');

    $this->get(route('inventory.gold-material-transactions.index', [
        'search' => $materialName,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('total', '10.00')
        );
});

test('addition and deduction append the correct legacy transaction types', function (
    string $kind,
    int $transactionTypeId,
) {
    $materialId = createGoldMaterialForTransactionTest();

    $this->post(route('inventory.gold-material-transactions.store'), [
        'transaction_kind' => $kind,
        'materialgold_id' => $materialId,
        'weight' => '12.34',
        'notes' => 'Adjustment test',
    ])->assertRedirect();

    $transaction = DB::connection('third')
        ->table('trmaterialgold')
        ->where('materialgold_id', $materialId)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and((int) $transaction->transtype_id)->toBe($transactionTypeId)
        ->and((int) $transaction->period_id)->toBe(activeGoldPeriodIdForTransactionTest())
        ->and((int) $transaction->ref_row_id)->toBe(0)
        ->and((int) $transaction->spk_id)->toBe(0)
        ->and((string) $transaction->weight)->toBe('12.34')
        ->and($transaction->notes)->toBe('Adjustment test');
})->with([
    'addition' => [
        'addition',
        GoldMaterialLedger::ADDITION_TRANSACTION_TYPE_ID,
    ],
    'deduction that may make stock negative' => [
        'deduction',
        GoldMaterialLedger::DEDUCTION_TRANSACTION_TYPE_ID,
    ],
]);

test('transaction store validates material weight precision and note length', function () {
    $this->post(route('inventory.gold-material-transactions.store'), [
        'transaction_kind' => 'addition',
        'materialgold_id' => 999999999,
        'weight' => '1.234',
        'notes' => str_repeat('a', 101),
    ])
        ->assertSessionHasErrors([
            'materialgold_id',
            'weight',
            'notes',
        ]);
});
