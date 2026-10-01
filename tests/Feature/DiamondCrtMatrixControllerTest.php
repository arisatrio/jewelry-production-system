<?php

use App\Models\DiamondCrtMatrix;
use App\Models\MsShape;
use Database\Seeders\DiamondCrtMatrixSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['msshape', 'diamond_crt_matrix'] as $table) {
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

function crtMatrixShapeId(string $code): int
{
    return (int) MsShape::query()->notDeleted()->where('code', $code)->value('row_id');
}

test('matrix crt dossier edit page lists shapes and stored ranges', function () {
    $this->seed(DiamondCrtMatrixSeeder::class);

    $this->get(route('master-data.diamond-crt-matrix.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/diamond-crt-matrix/edit')
            ->has('shapes')
            ->has('rows', DiamondCrtMatrix::query()->count())
            ->where('rows.0.shapeId', crtMatrixShapeId('R'))
            ->where('rows.0.crtMin', '0.180')
            ->where('rows.0.crtMax', '0.180')
            ->has('lastUpdate')
        );
});

test('matrix crt dossier can be replaced', function () {
    $this->seed(DiamondCrtMatrixSeeder::class);

    $roundShapeId = crtMatrixShapeId('R');
    $ovalShapeId = crtMatrixShapeId('OV');

    $this->put(route('master-data.diamond-crt-matrix.update'), [
        'rows' => [
            ['shape_id' => $ovalShapeId, 'crt_min' => '0.5', 'crt_max' => '0.99'],
            ['shape_id' => $roundShapeId, 'crt_min' => '0.2', 'crt_max' => '0.299'],
            ['shape_id' => $roundShapeId, 'crt_min' => '0.3', 'crt_max' => '0.399'],
        ],
    ])
        ->assertRedirect(route('master-data.diamond-crt-matrix.edit'));

    $ranges = DiamondCrtMatrix::query()
        ->orderBy('sort_order')
        ->get()
        ->map(fn (DiamondCrtMatrix $range): array => [
            $range->shape_id,
            $range->crt_min,
            $range->crt_max,
            $range->updated_by,
        ])
        ->all();

    expect($ranges)->toBe([
        [$ovalShapeId, '0.500', '0.990', 'system'],
        [$roundShapeId, '0.200', '0.299', 'system'],
        [$roundShapeId, '0.300', '0.399', 'system'],
    ]);
});

test('matrix crt dossier rejects crt max below crt min', function () {
    $this->from(route('master-data.diamond-crt-matrix.edit'))
        ->put(route('master-data.diamond-crt-matrix.update'), [
            'rows' => [
                ['shape_id' => crtMatrixShapeId('R'), 'crt_min' => '0.5', 'crt_max' => '0.4'],
            ],
        ])
        ->assertRedirect(route('master-data.diamond-crt-matrix.edit'))
        ->assertSessionHasErrors('rows.0.crt_max');
});

test('matrix crt dossier rejects overlapping ranges within a shape', function () {
    $roundShapeId = crtMatrixShapeId('R');

    $this->from(route('master-data.diamond-crt-matrix.edit'))
        ->put(route('master-data.diamond-crt-matrix.update'), [
            'rows' => [
                ['shape_id' => $roundShapeId, 'crt_min' => '0.2', 'crt_max' => '0.3'],
                ['shape_id' => $roundShapeId, 'crt_min' => '0.3', 'crt_max' => '0.4'],
                ['shape_id' => crtMatrixShapeId('OV'), 'crt_min' => '0.2', 'crt_max' => '0.3'],
            ],
        ])
        ->assertRedirect(route('master-data.diamond-crt-matrix.edit'))
        ->assertSessionHasErrors('rows.1.crt_min')
        ->assertSessionDoesntHaveErrors('rows.2.crt_min');
});

test('matrix crt dossier rejects unknown shapes and empty payload', function () {
    $this->from(route('master-data.diamond-crt-matrix.edit'))
        ->put(route('master-data.diamond-crt-matrix.update'), [
            'rows' => [
                ['shape_id' => 999999, 'crt_min' => '0.2', 'crt_max' => '0.3'],
            ],
        ])
        ->assertSessionHasErrors('rows.0.shape_id');

    $this->from(route('master-data.diamond-crt-matrix.edit'))
        ->put(route('master-data.diamond-crt-matrix.update'), ['rows' => []])
        ->assertSessionHasErrors('rows');
});
