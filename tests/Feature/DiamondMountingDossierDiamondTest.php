<?php

use App\Models\DiamondMounting;
use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['trdiamond', 'msshape', 'diamondmounting'] as $table) {
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

/**
 * @param  array<string, mixed>  $overrides
 */
function createMountingDossierDiamondForTest(array $overrides = []): int
{
    return (int) DB::connection('third')
        ->table('trdiamond')
        ->insertGetId([
            'doc_no' => 'T'.Str::upper(Str::random(12)),
            'diamond_type' => 'Dossier',
            'entry_date' => '2026-09-09',
            'out_date' => null,
            'supplier' => 'RJ',
            'crt' => '0.500',
            'shape_id' => 1,
            'color' => 'D',
            'certificate' => null,
            'rapp' => null,
            'disc' => null,
            'hpp' => null,
            'diamondmounting_id' => 0,
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

function createMountingProductionForTest(): Production
{
    return Production::factory()->create([
        'spk_no' => '2026/PRD/DMDSR'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Rangka',
        'is_inprocess' => 1,
    ]);
}

/**
 * @param  list<int>  $diamondIds
 * @return array<string, mixed>
 */
function mountingPayloadForTest(Production $production, array $diamondIds): array
{
    return [
        'spk_id' => $production->row_id,
        'setting_stones' => [],
        'return_stones' => [],
        'diamonds' => array_map(fn (int $id): array => ['diamond_id' => $id], $diamondIds),
        'mounted_stones' => [],
    ];
}

function dossierDiamondRow(int $diamondId): object
{
    return DB::connection('third')->table('trdiamond')->where('row_id', $diamondId)->first();
}

test('pasang batu store links selected dossier diamonds without creating new rows', function () {
    $production = createMountingProductionForTest();
    $diamondId = createMountingDossierDiamondForTest();
    $diamondCountBefore = DB::connection('third')->table('trdiamond')->count();

    $this->post(route('pasang-batu.store'), mountingPayloadForTest($production, [$diamondId]))
        ->assertSessionHasNoErrors();

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->firstOrFail();
    $diamond = dossierDiamondRow($diamondId);

    expect(DB::connection('third')->table('trdiamond')->count())->toBe($diamondCountBefore)
        ->and((int) $diamond->diamondmounting_id)->toBe((int) $document->row_id)
        ->and((int) $diamond->is_used)->toBe(1)
        ->and($diamond->out_date)->toBe(now()->toDateString())
        ->and((int) $diamond->is_deleted)->toBe(0);
});

test('pasang batu update releases removed dossier diamonds and links new ones', function () {
    $production = createMountingProductionForTest();
    $firstDiamondId = createMountingDossierDiamondForTest();
    $secondDiamondId = createMountingDossierDiamondForTest();

    $this->post(route('pasang-batu.store'), mountingPayloadForTest($production, [$firstDiamondId]))
        ->assertSessionHasNoErrors();

    $document = DiamondMounting::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->firstOrFail();

    $this->get(route('pasang-batu.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pasang-batu/edit')
            ->where('form.diamonds', [['diamondId' => $firstDiamondId]])
            ->where('diamondOptions', fn ($options) => collect($options)
                ->pluck('value')
                ->intersect([(string) $firstDiamondId, (string) $secondDiamondId])
                ->count() === 2)
        );

    $this->put(route('pasang-batu.update', $document), mountingPayloadForTest($production, [$secondDiamondId]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pasang-batu.show', $document));

    $firstDiamond = dossierDiamondRow($firstDiamondId);
    $secondDiamond = dossierDiamondRow($secondDiamondId);

    expect((int) $firstDiamond->diamondmounting_id)->toBe(0)
        ->and((int) $firstDiamond->is_used)->toBe(0)
        ->and($firstDiamond->out_date)->toBeNull()
        ->and((int) $firstDiamond->is_deleted)->toBe(0)
        ->and((int) $secondDiamond->diamondmounting_id)->toBe((int) $document->row_id)
        ->and((int) $secondDiamond->is_used)->toBe(1);
});

test('pasang batu rejects dossier diamonds already used by another document', function () {
    $production = createMountingProductionForTest();
    $usedDiamondId = createMountingDossierDiamondForTest([
        'is_used' => 1,
        'diamondmounting_id' => 999999,
        'out_date' => '2026-09-22',
    ]);

    $this->post(route('pasang-batu.store'), mountingPayloadForTest($production, [$usedDiamondId]))
        ->assertSessionHasErrors('diamonds.0.diamond_id');

    expect(DiamondMounting::query()->where('spk_id', $production->row_id)->exists())->toBeFalse()
        ->and((int) dossierDiamondRow($usedDiamondId)->diamondmounting_id)->toBe(999999);
});

test('pasang batu create page only offers available dossier diamonds', function () {
    $availableDiamondId = createMountingDossierDiamondForTest(['certificate' => 'GIA-TEST']);
    $usedDiamondId = createMountingDossierDiamondForTest(['is_used' => 1, 'diamondmounting_id' => 999999]);
    $deletedDiamondId = createMountingDossierDiamondForTest(['is_deleted' => 1]);

    $response = $this->get(route('pasang-batu.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('pasang-batu/create')->has('diamondOptions'));

    $options = collect($response->inertiaProps()['diamondOptions'])->keyBy('value');
    $availableOption = $options->get((string) $availableDiamondId);

    expect($availableOption)->not->toBeNull()
        ->and($availableOption['crt'])->toBe('0.500')
        ->and($availableOption['certificate'])->toBe('GIA-TEST')
        ->and($availableOption['label'])->toContain('0.500 ct')
        ->and($options->has((string) $usedDiamondId))->toBeFalse()
        ->and($options->has((string) $deletedDiamondId))->toBeFalse();
});
