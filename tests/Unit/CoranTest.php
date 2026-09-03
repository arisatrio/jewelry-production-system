<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('coran model uses the third connection and coran table', function () {
    $model = new Coran;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('coran')
        ->and($model->getKeyName())->toBe('row_id');
});

test('coran factory creates a record on the third connection', function () {
    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);

    expect($coran->exists)->toBeTrue()
        ->and($coran->getConnectionName())->toBe('third')
        ->and($coran->statusLabel())->toBe('Open');

    $coran->delete();
});

test('coran done status returns done label', function () {
    $coran = Coran::factory()->done()->make();

    expect($coran->isDone())->toBeTrue()
        ->and($coran->statusLabel())->toBe('Done');
});

test('coran has many coran spk details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/COR'.Str::upper(Str::random(4)),
    ]);

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
    ]);
    $detail = CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $production->row_id,
    ]);

    expect($coran->details)->toHaveCount(1)
        ->and($coran->details->first()?->line_id)->toBe($detail->line_id);

    $detail->delete();
    $coran->delete();
    $production->delete();
});
