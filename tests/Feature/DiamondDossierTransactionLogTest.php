<?php

use App\Models\Production;
use App\Support\DiamondDossierTransactionLog;
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
function createDossierDiamondForTransactionLogTest(array $overrides = []): int
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

test('diamond dossier transaction log lists entry and out events', function () {
    $certificate = 'LOG-'.Str::upper(Str::random(8));

    createDossierDiamondForTransactionLogTest([
        'certificate' => $certificate,
        'entry_date' => '2026-09-01',
    ]);
    createDossierDiamondForTransactionLogTest([
        'certificate' => $certificate,
        'entry_date' => '2026-09-05',
        'out_date' => '2026-09-20',
        'is_used' => 1,
        'modified_by' => 'out-user',
    ]);

    $this->get(route('inventory.diamond-dossier-transactions.index', [
        'search' => $certificate,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/batu-dossier/transactions/index')
            ->has('transactions.data.0.actorAt')
            ->has('transactions.data.0.spkNo')
            ->where('transactions.total', 3)
            ->where('transactions.data', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => ($row['eventType'] ?? null) === DiamondDossierTransactionLog::EVENT_ENTRY
                    && ($row['eventLabel'] ?? null) === 'Penambahan'
            ) && collect($rows)->contains(
                fn (array $row): bool => ($row['eventType'] ?? null) === DiamondDossierTransactionLog::EVENT_OUT
                    && ($row['eventLabel'] ?? null) === 'Pemakaian'
                    && ($row['actor'] ?? null) === 'out-user'
            ))
        );
});

test('diamond dossier transaction log filters by event type', function () {
    $certificate = 'LOG-'.Str::upper(Str::random(8));

    createDossierDiamondForTransactionLogTest([
        'certificate' => $certificate,
        'out_date' => '2026-09-22',
        'is_used' => 1,
    ]);

    $this->get(route('inventory.diamond-dossier-transactions.index', [
        'search' => $certificate,
        'event_type' => DiamondDossierTransactionLog::EVENT_OUT,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transactions.total', 1)
            ->where('transactions.data.0.eventType', DiamondDossierTransactionLog::EVENT_OUT)
            ->where('filters.event_type', DiamondDossierTransactionLog::EVENT_OUT)
        );
});

test('diamond dossier transaction log rejects unknown event type', function () {
    $this->get(route('inventory.diamond-dossier-transactions.index', [
        'event_type' => 'invalid',
    ]))
        ->assertSessionHasErrors('event_type');
});

test('diamond dossier transaction log includes spk from pasang batu document', function () {
    if (! Schema::connection('third')->hasTable('spk')) {
        $this->markTestSkipped('Table spk is not available.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/LOG'.Str::upper(Str::random(4)),
    ]);

    $mountingId = (int) DB::connection('third')->table('diamondmounting')->insertGetId([
        'doc_no' => 'DMT-LOG-'.Str::upper(Str::random(6)),
        'spk_id' => $production->row_id,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
    ]);

    $certificate = 'LOG-SPK-'.Str::upper(Str::random(6));

    createDossierDiamondForTransactionLogTest([
        'certificate' => $certificate,
        'out_date' => '2026-09-22',
        'is_used' => 1,
        'diamondmounting_id' => $mountingId,
    ]);

    $this->get(route('inventory.diamond-dossier-transactions.index', [
        'search' => $certificate,
        'event_type' => DiamondDossierTransactionLog::EVENT_OUT,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.spkNo', $production->spk_no)
            ->where('transactions.data.0.mountingDocumentId', $mountingId)
        );
});
