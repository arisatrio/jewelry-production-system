<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaFakturBarangJadi;
use App\Models\User;
use App\Support\FakturBarangJadiWorkflowStatus;
use App\Support\InventoryPluSkuLookup;
use App\Support\InventorySpkSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_faktur_barang_jadi')) {
        $this->markTestSkipped('Table serah_terima_faktur_barang_jadi is not available.');
    }
});

/**
 * @return array<string, mixed>
 */
function fakturShowCompletedProductionAttributes(string $suffix): array
{
    $attributes = [
        'spk_type' => 'Exchange',
        'status' => 'SPK010',
        'last_process' => 'Poles Barang Jadi',
        'is_inprocess' => 1,
        'location' => 'Workshop',
        'plu' => 'PLU-'.$suffix,
        'is_deleted' => 0,
    ];

    if (Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $attributes['foto_brj'] = 'test/foto-brj-'.$suffix.'.jpg';
    }

    return $attributes;
}

function seedCompletedProductionForFakturShow(Production $production): void
{
    DB::connection('third')->table('polishfinishedgood')->insertGetId([
        'doc_no' => 'TEST-FBJ-SHOW-'.$production->row_id,
        'process_name' => 'Poles Barang Jadi',
        'spk_id' => $production->row_id,
        'status' => 'RPFDONE',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
    ], 'row_id');
}

test('faktur barang jadi show page lists receipt items', function () {
    $production = Production::factory()->create(fakturShowCompletedProductionAttributes('A'));
    seedCompletedProductionForFakturShow($production);

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $user = User::factory()->create();

    $store = $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$production->row_id],
        'tanggal' => '2099-11-20',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->findOrFail((int) $store->json('id'));

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.show', $receipt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faktur-barang-jadi/show')
            ->where('receipt.id', $receipt->id)
            ->where('receipt.canEditSpk', true)
            ->has('receipt.items', 1)
            ->where('receipt.items.0.spkRowId', (int) $production->row_id)
            ->has('printGroups', 1)
            ->where('printGroups.0.spkRowId', (int) $production->row_id)
        );
});

test('faktur barang jadi created receipt can attach and detach spk', function () {
    $first = Production::factory()->create(fakturShowCompletedProductionAttributes('B1'));
    $second = Production::factory()->create(fakturShowCompletedProductionAttributes('B2'));
    seedCompletedProductionForFakturShow($first);
    seedCompletedProductionForFakturShow($second);

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $user = User::factory()->create();

    $store = $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$first->row_id, $second->row_id],
        'tanggal' => '2099-11-21',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->findOrFail((int) $store->json('id'));

    $third = Production::factory()->create(fakturShowCompletedProductionAttributes('B3'));
    seedCompletedProductionForFakturShow($third);

    $inventorySync = app(InventorySpkSync::class);
    $inventoryRowId = null;

    if (
        $inventorySync->inventoryTableAvailable()
        && $inventorySync->inventoryHasColumn('spk')
    ) {
        $lookup = app(InventoryPluSkuLookup::class);
        $pluColumn = $lookup->pluColumnName();

        if ($pluColumn !== null) {
            $insert = [
                $pluColumn => $third->plu,
                'spk' => $third->spk_no,
            ];

            if ($lookup->hasColumn('is_deleted')) {
                $insert['is_deleted'] = 0;
            }

            if ($lookup->hasColumn('company_id')) {
                $insert['company_id'] = 1;
            }

            foreach (['calc_price' => 0, 'production_cost' => 0, 'markup' => 0] as $column => $value) {
                if ($lookup->hasColumn($column)) {
                    $insert[$column] = $value;
                }
            }

            $inventoryRowId = DB::connection('second')->table('inventory')->insertGetId($insert);
        }
    }

    $this->actingAs($user)
        ->post(route('faktur-barang-jadi.spk.attach', $receipt), [
            'ids' => [$third->row_id],
        ])
        ->assertRedirect(route('faktur-barang-jadi.show', $receipt));

    $receipt->refresh();

    expect($receipt->jumlah_spk)->toBe(3)
        ->and($receipt->spk_row_ids)->toContain((int) $third->row_id);

    $third->refresh();

    expect($third->location)->toBe('Store');

    $this->actingAs($user)
        ->delete(route('faktur-barang-jadi.spk.detach', [
            'serahTerimaFakturBarangJadi' => $receipt,
            'spk_row_id' => $third->row_id,
        ]))
        ->assertRedirect(route('faktur-barang-jadi.show', $receipt));

    $receipt->refresh();

    expect($receipt->jumlah_spk)->toBe(2)
        ->and($receipt->spk_row_ids)->not->toContain((int) $third->row_id);

    $third->refresh();

    expect($third->location)->toBe('Workshop');

    if (Schema::connection('third')->hasColumn('spk', 'plu')) {
        expect($third->plu)->toBeNull();
    }

    if (Schema::connection('third')->hasColumn('spk', 'send_to_merch_date')) {
        expect($third->send_to_merch_date)->toBeNull();
    }

    if ($inventoryRowId !== null) {
        expect(
            DB::connection('second')->table('inventory')->where('row_id', $inventoryRowId)->value('spk')
        )->toBeNull();
    }

    if ($inventoryRowId !== null) {
        DB::connection('second')->table('inventory')->where('row_id', $inventoryRowId)->delete();
    }
});

test('faktur barang jadi received receipt can still attach spk', function () {
    $first = Production::factory()->create(fakturShowCompletedProductionAttributes('C1'));
    $second = Production::factory()->create(fakturShowCompletedProductionAttributes('C2'));
    seedCompletedProductionForFakturShow($first);
    seedCompletedProductionForFakturShow($second);

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $receivedBy = Employee::factory()->create();
    $user = User::factory()->create();

    $store = $this->actingAs($user)->postJson(route('faktur-barang-jadi.print.receipt.store'), [
        'ids' => [$first->row_id, $second->row_id],
        'tanggal' => '2099-11-22',
        'dari' => 'Workshop',
        'untuk' => 'Store',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
    ])->assertCreated();

    $receipt = SerahTerimaFakturBarangJadi::query()->findOrFail((int) $store->json('id'));

    if (! FakturBarangJadiWorkflowStatus::columnAvailable()) {
        $this->markTestSkipped('Workflow status column is not available.');
    }

    $this->actingAs($user)
        ->postJson(route('faktur-barang-jadi.print.receipt.send', $receipt))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('faktur-barang-jadi.print.receipt.receive', $receipt), [
            'diterima_oleh' => $receivedBy->nama_lengkap,
        ])
        ->assertOk();

    $receipt->refresh();

    expect(FakturBarangJadiWorkflowStatus::normalize($receipt->workflow_status))
        ->toBe(FakturBarangJadiWorkflowStatus::RECEIVED);

    $third = Production::factory()->create(fakturShowCompletedProductionAttributes('C3'));
    seedCompletedProductionForFakturShow($third);

    $this->actingAs($user)
        ->get(route('faktur-barang-jadi.show', $receipt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('receipt.canEditSpk', true));

    $this->actingAs($user)
        ->post(route('faktur-barang-jadi.spk.attach', $receipt), [
            'ids' => [$third->row_id],
        ])
        ->assertRedirect(route('faktur-barang-jadi.show', $receipt));

    $receipt->refresh();

    expect($receipt->jumlah_spk)->toBe(3)
        ->and($receipt->spk_row_ids)->toContain((int) $third->row_id);
});
