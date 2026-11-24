<?php

use App\Models\Production;
use App\Support\SpkProcessWeightSyncGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('coran last weight sync is blocked when finishing document exists', function () {
    if (! Schema::connection('third')->hasTable('finishinghandmade')) {
        $this->markTestSkipped('Table finishinghandmade is not available.');
    }

    $production = Production::factory()->create();

    DB::connection('third')->table('finishinghandmade')->insert([
        'spk_id' => $production->row_id,
        'finish_weight' => '5.81',
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'system',
        'modified_date' => now(),
        'modified_by' => 'system',
    ]);

    try {
        expect(app(SpkProcessWeightSyncGuard::class)->shouldSyncLastWeight(
            (int) $production->row_id,
            'coran',
        ))->toBeFalse()
            ->and(app(SpkProcessWeightSyncGuard::class)->shouldSyncLastWeight(
                (int) $production->row_id,
                'finishinghandmade',
            ))->toBeTrue();
    } finally {
        DB::connection('third')->table('finishinghandmade')
            ->where('spk_id', $production->row_id)
            ->delete();
        $production->delete();
    }
});
