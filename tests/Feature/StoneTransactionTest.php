<?php

use App\Support\StoneLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['msperiod', 'msstone', 'mstranstype', 'trstone'] as $table) {
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

function createStoneForTransactionTest(): int
{
    return (int) DB::connection('third')
        ->table('msstone')
        ->insertGetId([
            'name' => 'Stone Transaction Test '.Str::uuid(),
            'is_deleted' => 0,
        ]);
}

function activeStonePeriodIdForTransactionTest(): int
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
function createStoneTransactionForTest(int $stoneId, array $overrides = []): int
{
    return (int) DB::connection('third')
        ->table('trstone')
        ->insertGetId([
            'period_id' => activeStonePeriodIdForTransactionTest(),
            'transtype_id' => StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
            'ref_row_id' => 0,
            'stone_id' => $stoneId,
            'spk_id' => 0,
            'pcs' => 10,
            'crt' => '0.1000',
            'txt' => null,
            'notes' => null,
            'is_used' => 0,
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

test('stone transaction index lists transaction types and calculates signed total', function () {
    $stoneId = createStoneForTransactionTest();
    createStoneTransactionForTest($stoneId, [
        'transtype_id' => 7,
        'pcs' => 10,
    ]);
    createStoneTransactionForTest($stoneId, [
        'transtype_id' => StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
        'pcs' => 3,
    ]);

    $stoneName = DB::connection('third')
        ->table('msstone')
        ->where('row_id', $stoneId)
        ->value('name');

    $this->get(route('inventory.stone-transactions.index', [
        'search' => $stoneName,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/transaksi-batu/index')
            ->has('transactions.data', 2)
            ->where('total', '-7')
            ->has('stoneOptions')
            ->where(
                'activePeriod.id',
                activeStonePeriodIdForTransactionTest(),
            )
        );
});

test('stone transaction index filters by created date', function () {
    $stoneId = createStoneForTransactionTest();
    createStoneTransactionForTest($stoneId, [
        'created_date' => '2026-08-31 23:59:59',
    ]);
    createStoneTransactionForTest($stoneId, [
        'created_date' => '2026-09-09 10:00:00',
    ]);

    $stoneName = DB::connection('third')
        ->table('msstone')
        ->where('row_id', $stoneId)
        ->value('name');

    $this->get(route('inventory.stone-transactions.index', [
        'search' => $stoneName,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('total', '10')
        );
});

test('stone addition and deduction append the correct legacy transaction types', function (
    string $kind,
    int $transactionTypeId,
) {
    $stoneId = createStoneForTransactionTest();

    $this->post(route('inventory.stone-transactions.store'), [
        'transaction_kind' => $kind,
        'stone_id' => $stoneId,
        'pcs' => '12',
        'crt' => '0.0500',
        'notes' => 'Adjustment test',
    ])->assertRedirect();

    $transaction = DB::connection('third')
        ->table('trstone')
        ->where('stone_id', $stoneId)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and((int) $transaction->transtype_id)->toBe($transactionTypeId)
        ->and((int) $transaction->period_id)->toBe(activeStonePeriodIdForTransactionTest())
        ->and((int) $transaction->ref_row_id)->toBe(0)
        ->and((int) $transaction->spk_id)->toBe(0)
        ->and((int) $transaction->pcs)->toBe(12)
        ->and((float) $transaction->crt)->toBe(0.05)
        ->and($transaction->notes)->toBe('Adjustment test');
})->with([
    'addition' => [
        'addition',
        StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
    ],
    'deduction that may make stock negative' => [
        'deduction',
        StoneLedger::DEDUCTION_TRANSACTION_TYPE_ID,
    ],
]);

test('stone transaction store validates stone pcs and note length', function () {
    $this->post(route('inventory.stone-transactions.store'), [
        'transaction_kind' => 'addition',
        'stone_id' => 999999999,
        'pcs' => '0',
        'notes' => str_repeat('a', 101),
    ])
        ->assertSessionHasErrors([
            'stone_id',
            'pcs',
            'notes',
        ]);
});
