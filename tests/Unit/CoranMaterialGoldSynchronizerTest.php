<?php

use App\Models\Coran;
use App\Support\CoranMaterialGoldSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('coran material gold synchronizer updates totals from bahan and sisa lines', function () {
    if (
        ! Schema::connection('third')->hasTable('trmaterialgold')
        || ! Schema::connection('third')->hasTable('msmaterialgold')
    ) {
        $this->markTestSkipped('Material gold tables are not available.');
    }

    $materialId = DB::connection('third')
        ->table('msmaterialgold')
        ->when(
            Schema::connection('third')->hasColumn('msmaterialgold', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->orderBy('row_id')
        ->value('row_id');

    if ($materialId === null) {
        $this->markTestSkipped('No material gold master rows available.');
    }

    $coran = Coran::factory()->create([
        'submit_material_rosegold' => '0.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '0.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
    ]);

    app(CoranMaterialGoldSynchronizer::class)->sync($coran, [
        [
            'section' => 'bahan_rosegold',
            'materialgold_id' => (int) $materialId,
            'weight' => '10.50',
            'notes' => 'Catatan bahan rose',
        ],
        [
            'section' => 'bahan_whitegold',
            'materialgold_id' => (int) $materialId,
            'weight' => '2.25',
        ],
        [
            'section' => 'sisa_rosegold',
            'materialgold_id' => (int) $materialId,
            'weight' => '7.10',
            'notes' => 'Sisa untuk scrap',
        ],
    ], 'tester');

    $coran->refresh();

    expect((string) $coran->submit_material_rosegold)->toBe('10.50')
        ->and((string) $coran->submit_material_whitegold)->toBe('2.25')
        ->and((string) $coran->result_material_rosegold)->toBe('7.10');

    $rows = DB::connection('third')
        ->table('trmaterialgold')
        ->where('ref_row_id', $coran->row_id)
        ->whereIn('transtype_id', CoranMaterialGoldSynchronizer::transtypeIds())
        ->when(
            Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted'),
            fn ($query) => $query->where('is_deleted', 0),
        )
        ->get();

    expect($rows)->toHaveCount(3);

    if (Schema::connection('third')->hasColumn('trmaterialgold', 'notes')) {
        $roseBahan = $rows->firstWhere('transtype_id', 1);
        $roseSisa = $rows->firstWhere('transtype_id', 3);

        expect($roseBahan?->notes)->toBe('Catatan bahan rose')
            ->and($roseSisa?->notes)->toBe('Sisa untuk scrap');
    }

    $formLines = app(CoranMaterialGoldSynchronizer::class)->formLinesFor($coran);

    expect($formLines)->toHaveCount(3)
        ->and($formLines[0])->toHaveKeys(['section', 'materialgoldId', 'weight', 'notes']);

    if (Schema::connection('third')->hasColumn('trmaterialgold', 'notes')) {
        expect(collect($formLines)->firstWhere('section', 'bahan_rosegold')['notes'] ?? null)
            ->toBe('Catatan bahan rose');
    }

    DB::connection('third')
        ->table('trmaterialgold')
        ->where('ref_row_id', $coran->row_id)
        ->delete();
    $coran->delete();
});

test('coran material gold synchronizer lists active material options', function () {
    $options = app(CoranMaterialGoldSynchronizer::class)->materialOptions();

    if (
        ! Schema::connection('third')->hasTable('msmaterialgold')
    ) {
        expect($options)->toBe([]);

        return;
    }

    expect($options)->toBeArray();

    if ($options !== []) {
        expect($options[0])->toHaveKeys(['value', 'label', 'stock']);
    }
});

test('coran material gold options include remaining stock from in and out transactions', function () {
    if (
        ! Schema::connection('third')->hasTable('msmaterialgold')
        || ! Schema::connection('third')->hasTable('trmaterialgold')
        || ! Schema::connection('third')->hasTable('mstranstype')
    ) {
        $this->markTestSkipped('Material gold stock tables are not available.');
    }

    $connection = DB::connection('third');
    $materialId = $connection->table('msmaterialgold')->insertGetId([
        'name' => 'Material Stock Test '.uniqid(),
        'is_deleted' => 0,
    ]);

    try {
        $connection->table('trmaterialgold')->insert([
            [
                'transtype_id' => 12,
                'materialgold_id' => $materialId,
                'weight' => '10.25',
                'is_deleted' => 0,
            ],
            [
                'transtype_id' => 13,
                'materialgold_id' => $materialId,
                'weight' => '2.50',
                'is_deleted' => 0,
            ],
        ]);

        $option = collect(app(CoranMaterialGoldSynchronizer::class)->materialOptions())
            ->firstWhere('value', (string) $materialId);

        expect($option)->not->toBeNull()
            ->and($option['stock'])->toBe('7.75');
    } finally {
        $connection->table('trmaterialgold')
            ->where('materialgold_id', $materialId)
            ->delete();
        $connection->table('msmaterialgold')
            ->where('row_id', $materialId)
            ->delete();
    }
});
