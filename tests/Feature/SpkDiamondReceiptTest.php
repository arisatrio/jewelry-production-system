<?php

use App\Models\Employee;
use App\Models\Production;
use App\Models\SerahTerimaSpkDiamond;
use App\Models\SpkDiamond;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function deleteTestingSerahTerimaSpkDiamond(): void
{
    if (! Schema::connection('third')->hasTable('serah_terima_spk_diamond')) {
        return;
    }

    SerahTerimaSpkDiamond::query()
        ->withTrashed()
        ->where(function ($query): void {
            $query->where('doc_no', 'like', 'WHOJ/PRD/TTB/2098/%')
                ->orWhere('doc_no', 'like', 'WHOJ/PRD/TTB/2099/%')
                ->orWhere('doc_no', 'like', 'WHOJ/PRD/TTB/TEST/%');
        })
        ->forceDelete();
}

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('serah_terima_spk_diamond')) {
        $this->markTestSkipped('Table serah_terima_spk_diamond is not available.');
    }

    if (! Schema::connection('third')->hasTable('spk_diamond')) {
        $this->markTestSkipped('Table spk_diamond is not available.');
    }

    deleteTestingSerahTerimaSpkDiamond();
});

afterEach(fn () => deleteTestingSerahTerimaSpkDiamond());

test('spk diamond receipt store generates doc number and saves printed content', function () {
    $production = Production::factory()->create([
        'spk_no' => 'TEST/SPK/DIAMOND-A',
        'item_name' => 'Item Receipt Test',
        'is_deleted' => 0,
    ]);

    $first = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/RECEIPT/A/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-01',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 2,
        'items' => [],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $secondProduction = Production::factory()->create([
        'spk_no' => 'TEST/SPK/DIAMOND-B',
        'item_name' => 'Item Receipt Test B',
        'is_deleted' => 0,
    ]);

    $second = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/RECEIPT/B/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-02',
        'spk_row_id' => $secondProduction->row_id,
        'spk_no' => $secondProduction->spk_no,
        'jumlah_baris' => 1,
        'items' => [],
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $deleted = SpkDiamond::query()->create([
        'doc_no' => 'MKDM/RECEIPT/DEL/'.Str::upper(Str::random(4)),
        'tanggal' => '2026-11-03',
        'spk_row_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'jumlah_baris' => 1,
        'items' => [],
        'is_deleted' => 1,
        'created_date' => now(),
        'created_by' => 'test',
    ]);

    $handedBy = Employee::factory()->create();
    $acknowledgedBy = Employee::factory()->create();
    $receivedBy = Employee::factory()->create();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('spk-diamond.print.receipt.store'), [
        'ids' => [$second->row_id, $first->row_id, $deleted->row_id],
        'tanggal' => '2099-09-30',
        'dari' => 'Head Office',
        'untuk' => 'Workshop',
        'diserahkan_oleh' => $handedBy->nama_lengkap,
        'diketahui_oleh' => $acknowledgedBy->nama_lengkap,
        'diterima_oleh' => $receivedBy->nama_lengkap,
    ]);

    $receipt = SerahTerimaSpkDiamond::query()->where('doc_no', 'WHOJ/PRD/TTB/2099/0001')->firstOrFail();

    $response->assertCreated()
        ->assertExactJson([
            'id' => $receipt->id,
            'docNo' => 'WHOJ/PRD/TTB/2099/0001',
            'printUrl' => route('spk-diamond.print.receipt', $receipt),
        ]);

    expect($receipt->jumlah_dokumen)->toBe(2)
        ->and($receipt->spk_diamond_row_ids)->toBe([(int) $second->row_id, (int) $first->row_id])
        ->and(array_column($receipt->items, 'docNo'))->toBe([$second->doc_no, $first->doc_no])
        ->and($receipt->items[1]['spkNo'])->toBe('TEST/SPK/DIAMOND-A')
        ->and($receipt->items[1]['lineCount'])->toBe(2)
        ->and($receipt->created_by)->toBe($user->name);

    $this->actingAs($user)
        ->get(route('spk-diamond.print.receipt', $receipt))
        ->assertOk()
        ->assertSee($first->doc_no, false)
        ->assertSee('Tanda Terima SPK Pasang Batu', false);

    collect([$first, $second, $deleted, $production, $secondProduction, $handedBy, $acknowledgedBy, $receivedBy, $user])->each->delete();
});

test('spk diamond index includes receipt employee options', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    $this->actingAs($user)
        ->get(route('spk-diamond.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('receiptEmployeeOptions')
            ->where('receiptEmployeeOptions', fn ($options) => collect($options)->contains($employee->nama_lengkap)));

    $employee->delete();
    $user->delete();
});
