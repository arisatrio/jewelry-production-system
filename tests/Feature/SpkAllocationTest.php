<?php

use App\Models\Production;
use App\Models\SpkAllocation;
use App\Models\SpkAllocationStone;
use App\Support\SpkApprovalService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('alokasi batu index is accessible', function () {
    $this->get(route('alokasi-batu.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('alokasi-batu/index'));
});

test('alokasi batu index only lists manager approved spks', function () {
    $prefix = '2026/PRD/ALIDX'.Str::upper(Str::random(3));

    $approved = Production::factory()->managerApproved()->create([
        'spk_no' => $prefix.'-OK',
    ]);

    $pending = Production::factory()->create([
        'spk_no' => $prefix.'-NO',
        'status' => SpkApprovalService::STATUS_PENDING,
        'is_inprocess' => 0,
        'last_process' => null,
    ]);

    $this->get(route('alokasi-batu.index', ['search' => $prefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('productions.data', 1)
            ->where('productions.data.0.produksiNo', $approved->spk_no));

    $approved->delete();
    $pending->delete();
});

test('alokasi batu can be saved for production', function () {
    if (! Schema::connection('third')->hasTable('spk_allocation')) {
        $this->markTestSkipped('spk_allocation tables are not migrated.');
    }

    $production = Production::factory()->managerApproved()->create([
        'spk_no' => '2026/PRD/AL'.Str::upper(Str::random(4)),
    ]);

    $response = $this->put(route('alokasi-batu.update', $production), [
        'setting_stones' => [],
        'diamonds' => [],
    ]);

    $response->assertRedirect(route('alokasi-batu.show', $production));

    $allocation = SpkAllocation::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->first();

    expect($allocation)->not->toBeNull();

    $allocation?->delete();
    $production->delete();
});

test('spk diamond allocation endpoint returns empty when none', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/AL'.Str::upper(Str::random(4)),
    ]);

    $this->getJson(route('spk-diamond.select.allocation', ['spk_id' => $production->row_id]))
        ->assertOk()
        ->assertJsonPath('data.setting_stones', [])
        ->assertJsonPath('data.diamonds', []);

    $production->delete();
});

test('spk diamond allocation endpoint returns saved lines', function () {
    if (! Schema::connection('third')->hasTable('spk_allocation_stone')) {
        $this->markTestSkipped('spk_allocation tables are not migrated.');
    }

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/AL'.Str::upper(Str::random(4)),
    ]);

    $allocation = SpkAllocation::query()->create([
        'spk_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    SpkAllocationStone::query()->create([
        'spk_allocation_id' => $allocation->id,
        'stone_id' => 1,
        'pcs' => '5',
        'crt' => null,
        'notes' => null,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $this->getJson(route('spk-diamond.select.allocation', ['spk_id' => $production->row_id]))
        ->assertOk()
        ->assertJsonPath('data.setting_stones.0.stone_id', 1)
        ->assertJsonPath('data.setting_stones.0.pcs', '5');

    SpkAllocationStone::query()->where('spk_allocation_id', $allocation->id)->delete();
    $allocation->delete();
    $production->delete();
});
