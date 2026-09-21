<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Employee;
use App\Models\Production;
use App\Models\Resin;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('coran and resin tables have is_from_new_system column', function () {
    expect(Schema::connection('third')->hasColumn('coran', 'is_from_new_system'))->toBeTrue()
        ->and(Schema::connection('third')->hasColumn('resin', 'is_from_new_system'))->toBeTrue();
});

test('coran and resin factories default is_from_new_system to zero', function () {
    $coran = Coran::factory()->create();
    $resin = Resin::factory()->create();

    expect($coran->is_from_new_system)->toBe(0)
        ->and($resin->is_from_new_system)->toBe(0);

    $coran->delete();
    $resin->delete();
});

test('coran store sets is_from_new_system to one', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORNEW'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '1.00',
                'status' => null,
            ],
        ],
        'materials' => [],
    ]);

    $coran = Coran::query()
        ->notDeleted()
        ->whereHas('details', fn ($query) => $query
            ->notDeleted()
            ->where('spk_id', $production->row_id))
        ->orderByDesc('row_id')
        ->first();

    expect($coran)->not->toBeNull()
        ->and($coran->is_from_new_system)->toBe(1);

    $response->assertRedirect(route('coran.show', $coran));

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('resin store sets is_from_new_system to one', function () {
    $operator = Employee::factory()->productionActive()->create([
        'nama_lengkap' => 'Operator Resin New System',
    ]);
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RESNEW'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('resin.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'operator' => (string) $operator->nama_lengkap,
        'notes' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'berat_resin' => null,
                'status_resin' => null,
                'catatan' => null,
            ],
        ],
    ]);

    $resin = Resin::query()
        ->notDeleted()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('row_id')
        ->first();

    expect($resin)->not->toBeNull()
        ->and($resin->is_from_new_system)->toBe(1);

    $response->assertRedirect(route('resin.show', $resin));

    $resin->details()->delete();
    $resin->delete();
    $production->delete();
    $operator->delete();
});
