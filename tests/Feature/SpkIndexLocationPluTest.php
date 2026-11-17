<?php

use App\Models\Production;
use Illuminate\Support\Facades\Schema;

test('spk index exposes location and plu for list rows', function () {
    if (! Schema::connection('third')->hasColumn('spk', 'location')) {
        test()->markTestSkipped('Column spk.location is not available.');
    }

    $plu = (string) fake()->unique()->numerify('######');
    $production = Production::factory()->create([
        'location' => Production::DEFAULT_LOCATION,
        'plu' => $plu,
        'is_deleted' => 0,
    ]);

    $this->get(route('spk.index', ['search' => $production->spk_no]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/index')
            ->where('productions.data.0.location', Production::DEFAULT_LOCATION)
            ->where('productions.data.0.plu', $plu)
        );

    $production->delete();
});
