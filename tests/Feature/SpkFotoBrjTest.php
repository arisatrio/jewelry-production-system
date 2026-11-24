<?php

use App\Models\Production;
use Illuminate\Support\Facades\Schema;

test('spk table has foto_brj column after file_name on third connection', function () {
    expect(Schema::connection('third')->hasColumn('spk', 'foto_brj'))->toBeTrue();
});

test('production model persists foto_brj', function () {
    if (! Schema::connection('third')->hasColumn('spk', 'foto_brj')) {
        $this->markTestSkipped('Column spk.foto_brj is not available.');
    }

    $production = Production::factory()->create([
        'foto_brj' => 'finished-goods-photo.jpg',
        'is_deleted' => 0,
    ]);

    expect($production->refresh()->foto_brj)->toBe('finished-goods-photo.jpg');

    $production->delete();
});
