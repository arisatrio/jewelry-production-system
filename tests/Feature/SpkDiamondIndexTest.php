<?php

use App\Models\MsStone;
use App\Models\Production;
use App\Models\SpkDiamond;
use App\Models\User;
use App\Support\SpkStoneAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('spk diamond index includes spk order type fields on documents', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    DB::connection('third')->beginTransaction();

    $production = Production::factory()->create([
        'is_deleted' => 0,
        'spk_type' => 'Stock',
    ]);

    $document = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/SPKTYPE/TEST',
        'tanggal' => '2026-11-05',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 0,
        'items' => [],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spk-diamond.index', ['search' => $document->doc_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.spkType', 'Stock')
            ->has('documents.data.0.orderTypeLabel'));

    DB::connection('third')->rollBack();
    $user->delete();
});

test('spk diamond index resolves micro stone name from msstone master', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    if (! Schema::connection('third')->hasTable('msstone')) {
        $this->markTestSkipped('Table msstone is not available.');
    }

    DB::connection('third')->beginTransaction();

    $microStone = MsStone::factory()->create([
        'name' => 'Mikro Nama Batu Index Test',
    ]);

    $production = Production::factory()->create([
        'is_deleted' => 0,
    ]);

    $document = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/MICRO/NAME/TEST',
        'tanggal' => '2026-11-05',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 1,
        'items' => [[
            'allocationRowId' => 999001,
            'source' => SpkStoneAllocationService::SOURCE_MICRO,
            'label' => (string) $microStone->row_id,
            'code' => (string) $microStone->row_id,
            'msStoneId' => (int) $microStone->row_id,
            'shape' => 'Round (R)',
            'reservedPcs' => '3',
            'reservedCrt' => '0.155',
        ]],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spk-diamond.index', ['search' => $document->doc_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.allocatedStones.0.label', 'Mikro Nama Batu Index Test'));

    DB::connection('third')->rollBack();
    $user->delete();
});

test('authenticated user can view spk diamond index', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spk-diamond.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk-diamond/index')
            ->has('documents')
            ->has('filters')
            ->has('receiptEmployeeOptions')
            ->where('tableAvailable', true));
});

test('spk diamond index accepts sort query parameters', function () {
    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spk-diamond.index', [
            'sort' => 'doc_no',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk-diamond/index')
            ->where('filters.sort', 'doc_no')
            ->where('filters.direction', 'asc')
            ->has('filterOptions.sort', 8));

    $this->actingAs($user)
        ->get(route('spk-diamond.index', [
            'sort' => 'invalid',
            'direction' => 'sideways',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'tanggal')
            ->where('filters.direction', 'desc'));
});
