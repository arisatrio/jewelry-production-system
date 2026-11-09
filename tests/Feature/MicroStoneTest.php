<?php

use App\Support\StoneLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['msperiod', 'msstone', 'msshape', 'mstranstype', 'trstone'] as $table) {
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

function createMicroStoneForTest(string $name): int
{
    return (int) DB::connection('third')
        ->table('msstone')
        ->insertGetId([
            'name' => $name,
            'parcel' => 'B99',
            'stone_size' => '1.25',
            'shape_id' => 1,
            'is_deleted' => 0,
        ]);
}

function createMicroStoneTransactionForTest(int $stoneId, int $transactionTypeId, int $pcs, string $crt): void
{
    DB::connection('third')
        ->table('trstone')
        ->insert([
            'period_id' => (int) DB::connection('third')
                ->table('msperiod')
                ->where('is_active', 'YES')
                ->where('is_deleted', 0)
                ->value('row_id'),
            'transtype_id' => $transactionTypeId,
            'ref_row_id' => 0,
            'stone_id' => $stoneId,
            'spk_id' => 0,
            'pcs' => $pcs,
            'crt' => $crt,
            'is_used' => 0,
            'is_deleted' => 0,
            'created_date' => '2026-09-09 10:00:00',
            'created_by' => 'tester',
        ]);
}

test('micro stone index summarizes stock per stone from trstone', function () {
    $prefix = 'Micro Stone Test '.Str::uuid();
    $stoneId = createMicroStoneForTest($prefix.' A');
    createMicroStoneTransactionForTest($stoneId, StoneLedger::ADDITION_TRANSACTION_TYPE_ID, 20, '0.2000');
    createMicroStoneTransactionForTest($stoneId, 7, 5, '0.0500');
    createMicroStoneTransactionForTest($stoneId, 9, 3, '0.0300');

    $this->get(route('inventory.micro-stones.index', ['search' => $prefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-mikro/index')
            ->where('filters.per_page', 50)
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->has('filterOptions.sort')
            ->has('stones.data', 1)
            ->where('stones.data.0.name', $prefix.' A')
            ->where('stones.data.0.shape', 'Round')
            ->where('stones.data.0.pcsIn', '20')
            ->where('stones.data.0.pcsOut', '5')
            ->where('stones.data.0.balancePcs', '15')
            ->where('stones.data.0.balanceCrt', '0.150')
            ->where('totals.pcs', '15')
            ->where('totals.crt', '0.1500')
            ->has('statusSummaries.balance')
            ->where('statusSummaries.balance.pcs', '15')
            ->has('activePeriod.id')
        );
});

test('micro stone index filters by stock status', function (string $stockStatus, string $expectedSuffix) {
    $prefix = 'Micro Stone Test '.Str::uuid();
    $availableStoneId = createMicroStoneForTest($prefix.' Available');
    createMicroStoneForTest($prefix.' Empty');
    createMicroStoneTransactionForTest($availableStoneId, StoneLedger::ADDITION_TRANSACTION_TYPE_ID, 10, '0.1000');

    $this->get(route('inventory.micro-stones.index', [
        'search' => $prefix,
        'stock_status' => $stockStatus,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stones.data', 1)
            ->where('stones.data.0.name', $prefix.' '.$expectedSuffix)
            ->where('filters.stock_status', $stockStatus)
        );
})->with([
    'available' => [StoneLedger::STOCK_STATUS_AVAILABLE, 'Available'],
    'empty' => [StoneLedger::STOCK_STATUS_EMPTY, 'Empty'],
]);

test('micro stone index sorts by balance pcs descending', function () {
    $prefix = 'Micro Stone Sort '.Str::uuid();
    $lowStoneId = createMicroStoneForTest($prefix.' Low');
    $highStoneId = createMicroStoneForTest($prefix.' High');
    createMicroStoneTransactionForTest(
        $lowStoneId,
        StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
        5,
        '0.0500',
    );
    createMicroStoneTransactionForTest(
        $highStoneId,
        StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
        20,
        '0.2000',
    );

    $this->get(route('inventory.micro-stones.index', [
        'search' => $prefix,
        'sort' => 'balance_pcs',
        'direction' => 'desc',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'balance_pcs')
            ->where('filters.direction', 'desc')
            ->where('stones.data.0.name', $prefix.' High')
            ->where('stones.data.1.name', $prefix.' Low')
        );
});

test('micro stone index exposes add stock stone for restock deep link', function () {
    $prefix = 'Micro Stone Restock '.Str::uuid();
    $stoneId = createMicroStoneForTest($prefix);

    $this->get(route('inventory.micro-stones.index', [
        'add_stock' => '1',
        'stone_id' => $stoneId,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('addStockStone.id', $stoneId)
            ->where('addStockStone.name', $prefix)
        );
});

test('micro stone index filters list by stone_id deep link', function () {
    $prefix = 'Micro Stone Focus '.Str::uuid();
    $targetStoneId = createMicroStoneForTest($prefix.' Target');
    createMicroStoneForTest($prefix.' Other');
    createMicroStoneTransactionForTest(
        $targetStoneId,
        StoneLedger::ADDITION_TRANSACTION_TYPE_ID,
        12,
        '0.1200',
    );

    $this->get(route('inventory.micro-stones.index', [
        'stone_id' => $targetStoneId,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stones.data', 1)
            ->where('stones.data.0.id', $targetStoneId)
            ->where('stones.data.0.name', $prefix.' Target')
            ->where('addStockStone', null)
        );
});
