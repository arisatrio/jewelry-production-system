<?php

use App\Models\Production;
use App\Models\SpkStone;
use App\Models\User;
use App\Support\DiamondDossierInventory;
use App\Support\SpkService;
use App\Support\SpkStoneAllocationService;
use App\Support\SpkStoneFulfillment;
use App\Support\SpkStoneStockChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('spkstone')) {
        $this->markTestSkipped('Table spkstone is not available.');
    }

    if (! Schema::connection('third')->hasTable('spkstone_allocation')) {
        $this->markTestSkipped('Table spkstone_allocation is not available.');
    }

    DB::connection('third')->beginTransaction();
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

/**
 * @return array{id: int, code: string|null}
 */
function matchingDossierCandidate(SpkStone $stone): array
{
    $match = app(SpkStoneStockChecker::class)->matchingStones($stone);

    $row = collect($match['dossier']['rows'] ?? $match['rows'] ?? [])
        ->first(fn (array $candidate): bool => ($candidate['matchesSpkRequirement'] ?? false) === true);

    if (! is_array($row)) {
        test()->markTestSkipped('No matching dossier stock for SPK stone line.');
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'code' => isset($row['code']) ? (string) $row['code'] : null,
    ];
}

test('spk stone dossier allocation reserves diamond for spk line', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape_id)->not->toBeNull();

    $candidate = matchingDossierCandidate($stone);

    expect($candidate['id'])->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(1)
        ->and(app(SpkStoneAllocationService::class)->diamondIsReserved($candidate['id']))
        ->toBeTrue();

    $allocatedItems = app(SpkStoneAllocationService::class)->allocationsForStone($stone);

    expect($allocatedItems)->toHaveCount(1)
        ->and($allocatedItems[0]['source'])->toBe(SpkStoneAllocationService::SOURCE_DOSSIER)
        ->and($allocatedItems[0])->toHaveKeys(['shape', 'displayId', 'diamondType']);

    if ($candidate['code'] !== null && $candidate['code'] !== '') {
        expect($allocatedItems[0]['displayId'])->toBe($candidate['code']);
    }

    if ($candidate['code'] !== null && $candidate['code'] !== '') {
        $this->get(route('inventory.diamond-dossiers.index', [
            'search' => $candidate['code'],
            'status' => DiamondDossierInventory::STATUS_RESERVED,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('diamonds.data.0.status', DiamondDossierInventory::STATUS_RESERVED)
            );
    }
});

test('spk stone dossier allocation accepts diamond listed outside spk crt spec', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape_id)->not->toBeNull();

    $match = app(SpkStoneStockChecker::class)->matchingStones($stone);
    $row = collect($match['dossier']['rows'] ?? $match['rows'] ?? [])
        ->first(fn (array $candidate): bool => ($candidate['matchesSpkRequirement'] ?? false) !== true);

    if (! is_array($row)) {
        test()->markTestSkipped('No non-matching dossier row in stock list for this SPK stone line.');
    }

    $diamondId = (int) ($row['id'] ?? 0);

    expect($diamondId)->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $diamondId,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(1)
        ->and(app(SpkStoneAllocationService::class)->diamondIsReserved($diamondId))
        ->toBeTrue();
});

test('spk stone single allocation revoke removes one reserved item', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 2)->first();

    if ($stone === null) {
        test()->markTestSkipped('No SPK stone line with pcs >= 2.');
    }

    expect($stone->shape_id)->not->toBeNull();

    $match = app(SpkStoneStockChecker::class)->matchingStones($stone);
    $rows = collect($match['dossier']['rows'] ?? $match['rows'] ?? [])
        ->filter(fn (array $candidate): bool => ($candidate['matchesSpkRequirement'] ?? false) === true)
        ->values();

    if ($rows->count() < 2) {
        test()->markTestSkipped('Need at least two matching dossier rows.');
    }

    $firstId = (int) ($rows[0]['id'] ?? 0);
    $secondId = (int) ($rows[1]['id'] ?? 0);

    expect($firstId)->toBeGreaterThan(0)
        ->and($secondId)->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $firstId,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $secondId,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(2);

    $allocationRowId = (int) DB::connection('third')
        ->table('spkstone_allocation')
        ->where('line_id', $stone->line_id)
        ->where('diamond_id', $firstId)
        ->where('is_deleted', 0)
        ->value('row_id');

    expect($allocationRowId)->toBeGreaterThan(0);

    $this->post(route('spk.stones.allocations.items.revoke', [
        'spkStone' => $stone,
        'allocationRowId' => $allocationRowId,
    ]))->assertRedirect();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(1)
        ->and(app(SpkStoneAllocationService::class)->diamondIsReserved($firstId))
        ->toBeFalse()
        ->and(app(SpkStoneAllocationService::class)->diamondIsReserved($secondId))
        ->toBeTrue();
});

test('spk stone allocation revoke releases reserved dossier diamond', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', 1)->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape_id)->not->toBeNull();

    $candidate = matchingDossierCandidate($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->post(route('spk.stones.allocations.revoke', $stone))
        ->assertRedirect();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(0)
        ->and($stone->fulfillment_status)->toBe(SpkStoneFulfillment::NOT_ALLOCATED)
        ->and(app(SpkStoneAllocationService::class)->diamondIsReserved($candidate['id']))
        ->toBeFalse();
});

test('replacing spk stone list revokes allocations on removed lines', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', 1)->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape_id)->not->toBeNull();

    $production = Production::query()
        ->notDeleted()
        ->where('row_id', $stone->row_id)
        ->first();

    expect($production)->not->toBeNull();

    $candidate = matchingDossierCandidate($stone);
    $lineId = (int) $stone->line_id;

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SpkStoneAllocationService::class)->diamondIsReserved($candidate['id']))
        ->toBeTrue();

    app(SpkService::class)->updateGoldAndStones($production, [
        'gold_weight' => $production->gold_weight ?? 1,
        'gold_color' => $production->gold_color ?? 'Yellow Gold',
        'stones' => [],
    ], 'tester');

    expect(app(SpkStoneAllocationService::class)->diamondIsReserved($candidate['id']))
        ->toBeFalse();

    expect(
        DB::connection('third')
            ->table('spkstone_allocation')
            ->where('line_id', $lineId)
            ->where('is_deleted', 0)
            ->count(),
    )->toBe(0);
});

test('diamond dossier show revoke allocation releases reserved stone', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', 1)->first();

    expect($stone)->not->toBeNull()
        ->and($stone->shape_id)->not->toBeNull();

    $candidate = matchingDossierCandidate($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->get(route('inventory.diamond-dossiers.show', $candidate['id']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canRevokeAllocation', true)
            ->where('diamond.status', DiamondDossierInventory::STATUS_RESERVED),
        );

    $this->from(route('inventory.diamond-dossiers.show', $candidate['id']))
        ->post(route('inventory.diamond-dossiers.revoke-allocation', $candidate['id']))
        ->assertRedirect(route('inventory.diamond-dossiers.show', $candidate['id']));

    expect(app(SpkStoneAllocationService::class)->diamondIsReserved($candidate['id']))
        ->toBeFalse();

    $this->get(route('inventory.diamond-dossiers.show', $candidate['id']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canRevokeAllocation', false)
            ->where('diamond.status', DiamondDossierInventory::STATUS_AVAILABLE),
        );
});

/**
 * @return array{id: int}
 */
function matchingMicroCandidate(SpkStone $stone): array
{
    $match = app(SpkStoneStockChecker::class)->matchingStones($stone);

    $row = collect($match['micro']['rows'] ?? [])
        ->first(fn (array $candidate): bool => ($candidate['matchesSpkRequirement'] ?? false) === true);

    if (! is_array($row)) {
        test()->markTestSkipped('No matching micro stock for SPK stone line.');
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
    ];
}

test('spk stone micro allocation merges pcs on same line and stone master', function () {
    $stone = SpkStone::query()
        ->notDeleted()
        ->where('pcs', '>=', 3)
        ->whereNotNull('shape_id')
        ->first();

    if ($stone === null) {
        test()->markTestSkipped('No SPK stone line with pcs >= 3.');
    }

    $micro = matchingMicroCandidate($stone);

    expect($micro['id'])->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.micro', $stone), [
        'msstone_id' => $micro['id'],
        'reserved_pcs' => 1,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->post(route('spk.stones.allocations.micro', $stone), [
        'msstone_id' => $micro['id'],
        'reserved_pcs' => 2,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()))
        ->toBe(3);

    $microRows = DB::connection('third')
        ->table('spkstone_allocation')
        ->where('line_id', $stone->line_id)
        ->where('source', SpkStoneAllocationService::SOURCE_MICRO)
        ->where('msstone_id', $micro['id'])
        ->where('is_deleted', 0)
        ->get();

    expect($microRows)->toHaveCount(1)
        ->and((float) $microRows[0]->reserved_pcs)->toBe(3.0);

    $items = app(SpkStoneAllocationService::class)->allocationsForStone($stone);

    expect($items)->toHaveCount(1)
        ->and($items[0]['source'])->toBe(SpkStoneAllocationService::SOURCE_MICRO)
        ->and((float) $items[0]['reservedPcs'])->toBe(3.0);
});

test('spk stone line can combine dossier and micro allocations', function () {
    $stone = SpkStone::query()
        ->notDeleted()
        ->where('pcs', '>=', 2)
        ->whereNotNull('shape_id')
        ->first();

    expect($stone)->not->toBeNull();

    $dossier = matchingDossierCandidate($stone);
    $micro = matchingMicroCandidate($stone);

    expect($dossier['id'])->toBeGreaterThan(0)
        ->and($micro['id'])->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $dossier['id'],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $remaining = max(0, (int) ($stone->pcs ?? 0) - app(SpkStoneAllocationService::class)->allocatedPcs($stone->refresh()));

    expect($remaining)->toBeGreaterThan(0);

    $this->post(route('spk.stones.allocations.micro', $stone), [
        'msstone_id' => $micro['id'],
        'reserved_pcs' => min(1, $remaining),
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $items = app(SpkStoneAllocationService::class)->allocationsForStone($stone->refresh());
    $sources = collect($items)->pluck('source')->unique()->values()->all();

    expect($sources)->toContain(SpkStoneAllocationService::SOURCE_DOSSIER)
        ->and($sources)->toContain(SpkStoneAllocationService::SOURCE_MICRO);

    $microItem = collect($items)->firstWhere('source', SpkStoneAllocationService::SOURCE_MICRO);

    expect($microItem)->not->toBeNull()
        ->and(filled($microItem['label'] ?? null))->toBeTrue();
});
