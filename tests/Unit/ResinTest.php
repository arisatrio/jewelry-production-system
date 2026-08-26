<?php

use App\Models\Production;
use App\Models\Resin;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('resin model uses the third connection and resin table', function () {
    $model = new Resin;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('resin')
        ->and($model->getKeyName())->toBe('row_id');
});

test('resin factory creates a record on the third connection', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RES'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);

    expect($resin->exists)->toBeTrue()
        ->and($resin->getConnectionName())->toBe('third')
        ->and($resin->production?->row_id)->toBe($production->row_id);

    $resin->delete();
    $production->delete();
});
