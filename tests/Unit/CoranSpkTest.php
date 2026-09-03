<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('coran spk model uses the third connection and coranspk table', function () {
    $model = new CoranSpk;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('coranspk')
        ->and($model->getKeyName())->toBe('line_id');
});

test('coran spk factory creates a record on the third connection', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CSP'.Str::upper(Str::random(4)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);

    $detail = CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
        'weight' => '2.500',
    ]);

    expect($detail->exists)->toBeTrue()
        ->and($detail->getConnectionName())->toBe('third')
        ->and($detail->coran?->row_id)->toBe($coran->row_id)
        ->and($detail->production?->row_id)->toBe($production->row_id);

    $detail->delete();
    $coran->delete();
    $production->delete();
});
