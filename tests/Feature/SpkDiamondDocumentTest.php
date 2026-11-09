<?php

use App\Models\Production;
use App\Models\SpkDiamond;
use App\Models\SpkStone;
use App\Models\User;
use App\Support\SpkStoneStockChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spk_diamond', 'spkstone_allocation', 'spkstone'] as $table) {
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
 * @return array{id: int}
 */
function matchingDossierCandidateForSpkDiamond(SpkStone $stone): array
{
    $match = app(SpkStoneStockChecker::class)->matchingStones($stone);

    $row = collect($match['dossier']['rows'] ?? $match['rows'] ?? [])
        ->first(fn (array $candidate): bool => ($candidate['matchesSpkRequirement'] ?? false) === true);

    if (! is_array($row)) {
        test()->markTestSkipped('No matching dossier stock for SPK stone line.');
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
    ];
}

test('spk diamond store persists document from fully allocated stones', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull();

    $candidate = matchingDossierCandidateForSpkDiamond($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('row_id', $stone->row_id)
        ->firstOrFail();

    $docNo = 'MKDM/TEST/'.Str::upper(Str::random(6));

    $response = $this->postJson(route('spk.spk-diamond.store'), [
        'spk_row_id' => $production->row_id,
        'doc_no' => $docNo,
        'tanggal' => '2026-11-05',
    ]);

    $response->assertCreated()
        ->assertJsonPath('docNo', $docNo);

    $document = SpkDiamond::query()->findOrFail($response->json('id'));

    expect($document->doc_no)->toBe($docNo)
        ->and($document->spk_row_id)->toBe((int) $production->row_id)
        ->and($document->jumlah_baris)->toBeGreaterThan(0)
        ->and($document->items)->toBeArray()
        ->and($document->items[0]['allocationRowId'] ?? null)->toBeInt();

    if (Schema::connection('third')->hasColumn('spk', 'spk_diamond_doc_no')) {
        expect(DB::connection('third')->table('spk')->where('row_id', $production->row_id)->value('spk_diamond_doc_no'))
            ->toBe($docNo);
    }

    if (Schema::connection('third')->hasColumn('spk_diamond', 'status')) {
        expect($document->status)->toBe('draft');
    }
});

test('spk diamond show renders document detail', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull();

    $candidate = matchingDossierCandidateForSpkDiamond($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('row_id', $stone->row_id)
        ->firstOrFail();

    $docNo = 'MKDM/SHOW/'.Str::upper(Str::random(6));

    $response = $this->postJson(route('spk.spk-diamond.store'), [
        'spk_row_id' => $production->row_id,
        'doc_no' => $docNo,
        'tanggal' => '2026-11-05',
    ]);

    $response->assertCreated();

    $documentId = (int) $response->json('id');

    $this->get(route('spk-diamond.show', $documentId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk-diamond/show')
            ->has('document')
            ->where('document.docNo', $docNo)
            ->where('document.spkRowId', (int) $production->row_id)
            ->where('document.status', 'draft')
            ->where('document.statusLabel', 'Draft')
            ->has('document.items', 1));
});

test('spk diamond store rejects duplicate document for same production', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull();

    $candidate = matchingDossierCandidateForSpkDiamond($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('row_id', $stone->row_id)
        ->firstOrFail();

    $payload = [
        'spk_row_id' => $production->row_id,
        'doc_no' => 'MKDM/DUP/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-05',
    ];

    $this->postJson(route('spk.spk-diamond.store'), $payload)->assertCreated();

    $this->postJson(route('spk.spk-diamond.store'), [
        ...$payload,
        'doc_no' => 'MKDM/DUP2/'.Str::upper(Str::random(4)),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['spk_row_id']);
});

test('spk diamond store rejects duplicate doc number', function () {
    $stone = SpkStone::query()->notDeleted()->where('pcs', '>=', 1)->first();

    expect($stone)->not->toBeNull();

    $candidate = matchingDossierCandidateForSpkDiamond($stone);

    $this->actingAs(User::factory()->create());

    $this->post(route('spk.stones.allocations.dossier', $stone), [
        'diamond_id' => $candidate['id'],
    ])->assertRedirect();

    $production = Production::query()
        ->notDeleted()
        ->where('row_id', $stone->row_id)
        ->firstOrFail();

    $docNo = 'MKDM/UNIQ/'.Str::upper(Str::random(5));

    $this->postJson(route('spk.spk-diamond.store'), [
        'spk_row_id' => $production->row_id,
        'doc_no' => $docNo,
        'tanggal' => '2026-11-05',
    ])->assertCreated();

    $otherProduction = Production::factory()->create(['is_deleted' => 0]);
    $otherStone = SpkStone::factory()->create([
        'row_id' => $otherProduction->row_id,
        'pcs' => 1,
        'shape_id' => $stone->shape_id,
    ]);

    $otherCandidate = matchingDossierCandidateForSpkDiamond($otherStone);

    $this->post(route('spk.stones.allocations.dossier', $otherStone), [
        'diamond_id' => $otherCandidate['id'],
    ])->assertRedirect();

    $this->postJson(route('spk.spk-diamond.store'), [
        'spk_row_id' => $otherProduction->row_id,
        'doc_no' => $docNo,
        'tanggal' => '2026-11-05',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['doc_no']);
});
