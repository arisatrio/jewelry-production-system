<?php

use App\Models\Production;
use App\Models\SpkStone;
use App\Support\SpkIndexMaterialStatus;
use App\Support\SpkStoneAllocationService;
use App\Support\SpkStoneFulfillment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('spkstone')) {
        $this->markTestSkipped('Table spkstone is not available.');
    }
});

test('spk index material status summarizes ordering stone lines', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $production = Production::query()->notDeleted()->find($stone->row_id);

    expect($production)->not->toBeNull();

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERING]);

    $summary = app(SpkIndexMaterialStatus::class)->summariesForRowIds([(int) $production->row_id]);

    $stoneIndexStatus = $summary[(int) $production->row_id]['stoneIndexStatus'];

    expect($stoneIndexStatus)->toBeArray()
        ->and($stoneIndexStatus['allocation']['status'])->toBe(SpkStoneFulfillment::ORDERING)
        ->and($stoneIndexStatus['allocation']['label'])->toBe(
            SpkStoneFulfillment::label(SpkStoneFulfillment::ORDERING),
        );
});

test('spk index material status summarizes ordered stone lines', function () {
    $stone = SpkStone::query()->notDeleted()->first();

    expect($stone)->not->toBeNull();

    $production = Production::query()->notDeleted()->find($stone->row_id);

    expect($production)->not->toBeNull();

    $stone->update(['fulfillment_status' => SpkStoneFulfillment::ORDERED]);

    $summary = app(SpkIndexMaterialStatus::class)->summariesForRowIds([(int) $production->row_id]);

    $stoneIndexStatus = $summary[(int) $production->row_id]['stoneIndexStatus'];

    expect($stoneIndexStatus)->toBeArray()
        ->and($stoneIndexStatus['allocation']['status'])->toBe(SpkStoneFulfillment::ORDERED)
        ->and($stoneIndexStatus['allocation']['label'])->toBe(
            SpkStoneFulfillment::label(SpkStoneFulfillment::ORDERED),
        );
});

test('spk index material status formats spk diamond allocation label with document number', function () {
    $reflection = new ReflectionClass(SpkIndexMaterialStatus::class);
    $method = $reflection->getMethod('spkDiamondAllocationLabel');
    $method->setAccessible(true);

    expect($method->invoke(app(SpkIndexMaterialStatus::class), 'SD-2026-001'))
        ->toBe('SPK Pasang Batu · SD-2026-001');
});

test('spk index material status formats stock and flow labels', function () {
    expect(SpkIndexMaterialStatus::formatStoneIndexStatus(
        SpkIndexMaterialStatus::STOCK_AVAILABLE,
        SpkIndexMaterialStatus::FLOW_WAITING_ALLOCATION,
    ))->toBe('Stok Ada | Menunggu Alokasi');

    expect(SpkIndexMaterialStatus::formatStoneIndexStatus(
        SpkIndexMaterialStatus::STOCK_UNAVAILABLE,
        SpkIndexMaterialStatus::FLOW_NOT_ORDERED,
    ))->toBe('Stok Tidak ada | Belum diorder');
});

test('spk index material status partial stone label format', function () {
    expect(SpkIndexMaterialStatus::stonePartialAllocatedLabel(1, 4))
        ->toBe('1 dari 4 batu sudah dialokasikan');
});

test('spk index material status summarizes partial stone allocation', function () {
    if (! app(SpkStoneAllocationService::class)->tableAvailable()) {
        $this->markTestSkipped('Table spkstone_allocation is not available.');
    }

    $stones = SpkStone::query()
        ->notDeleted()
        ->orderBy('row_id')
        ->orderBy('line_id')
        ->get(['line_id', 'row_id', 'pcs', 'fulfillment_status']);

    $targetRowId = null;
    $targetStones = null;

    foreach ($stones->groupBy(static fn (SpkStone $stone): int => (int) $stone->row_id) as $rowId => $lines) {
        if ((int) $rowId <= 0 || $lines->count() < 2) {
            continue;
        }

        $lineIds = $lines->pluck('line_id')->map(static fn (mixed $id): int => (int) $id)->all();
        $allocatedByLine = DB::connection('third')
            ->table(SpkStoneAllocationService::TABLE)
            ->whereIn('line_id', $lineIds)
            ->where('is_deleted', 0)
            ->where('status', SpkStoneAllocationService::STATUS_RESERVED)
            ->pluck('line_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($allocatedByLine !== []) {
            continue;
        }

        $targetRowId = (int) $rowId;
        $targetStones = $lines->values();

        break;
    }

    if ($targetRowId === null || $targetStones === null) {
        $this->markTestSkipped('No SPK with multiple unallocated stone lines in database.');
    }

    foreach ($targetStones as $stone) {
        $stone->update(['fulfillment_status' => SpkStoneFulfillment::NOT_ALLOCATED]);
    }

    $firstStone = $targetStones->first();

    expect($firstStone)->not->toBeNull();

    $firstLineId = (int) $firstStone->line_id;
    $requiredPcs = max(1, (int) ($firstStone->pcs ?? 1));

    DB::connection('third')->table(SpkStoneAllocationService::TABLE)->insert([
        'line_id' => $firstLineId,
        'source' => SpkStoneAllocationService::SOURCE_MICRO,
        'msstone_id' => 1,
        'reserved_pcs' => $requiredPcs,
        'status' => SpkStoneAllocationService::STATUS_RESERVED,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'partial-index-test',
    ]);

    try {
        $summary = app(SpkIndexMaterialStatus::class)->summariesForRowIds([$targetRowId]);
        $totalLines = $targetStones->count();
        $stoneIndexStatus = $summary[$targetRowId]['stoneIndexStatus'];

        expect($stoneIndexStatus['allocation']['status'])->toBe('partial')
            ->and($stoneIndexStatus['allocation']['label'])->toBe(
                SpkIndexMaterialStatus::stonePartialAllocatedLabel(1, $totalLines),
            );
    } finally {
        DB::connection('third')
            ->table(SpkStoneAllocationService::TABLE)
            ->where('line_id', $firstLineId)
            ->where('created_by', 'partial-index-test')
            ->delete();
    }
});

test('spk index material status uses dash values when spk has no stone or component lines', function () {
    $production = Production::query()
        ->notDeleted()
        ->whereDoesntHave('stones', fn ($query) => $query->notDeleted())
        ->whereDoesntHave('components', fn ($query) => $query->notDeleted())
        ->first();

    if ($production === null) {
        $this->markTestSkipped('No SPK without stone and component lines in database.');
    }

    $summary = app(SpkIndexMaterialStatus::class)->summariesForRowIds([(int) $production->row_id]);

    expect($summary[(int) $production->row_id])
        ->toMatchArray([
            'stoneIndexStatus' => null,
            'componentStatus' => null,
        ]);
});
