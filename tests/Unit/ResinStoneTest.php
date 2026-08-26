<?php

use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinStone;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('resin stone model uses the third connection and resinstone table', function () {
    $model = new ResinStone;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('resinstone')
        ->and($model->getKeyName())->toBe('line_id');
});

test('resin stone belongs to resin', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/RST'.Str::upper(Str::random(4)),
    ]);

    $resin = Resin::factory()->create([
        'spk_id' => $production->row_id,
        'doc_no' => 'RES'.Str::upper(Str::random(7)),
    ]);

    $stone = ResinStone::factory()->create([
        'row_id' => $resin->row_id,
        'pcs' => 3,
        'carat' => 5,
        'size' => '1.50',
    ]);

    expect($stone->resin)->toBeInstanceOf(Resin::class)
        ->and($stone->resin?->row_id)->toBe($resin->row_id);

    $stone->delete();
    $resin->delete();
    $production->delete();
});
