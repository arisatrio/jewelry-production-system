<?php

use App\Models\FinishingHandmade;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('backfill migration fills null shrink_tolerance for complete finishing docs', function () {
    $withMaterials = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN9999801',
        'start_weight' => '1.08',
        'finish_weight' => '0.91',
        'submit_materialgold' => '0.07',
        'result_materialgold' => '0.16',
        'shrink' => '0.08',
        'shrink_tolerance' => null,
        'is_deleted' => 0,
    ]);

    $largeBatch = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN9999802',
        'start_weight' => '16.58',
        'finish_weight' => '14.09',
        'submit_materialgold' => '2.59',
        'result_materialgold' => '4.23',
        'shrink' => '0.85',
        'shrink_tolerance' => null,
        'is_deleted' => 0,
    ]);

    $alreadyFilled = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN9999803',
        'start_weight' => '3.00',
        'finish_weight' => '2.00',
        'submit_materialgold' => '0.00',
        'result_materialgold' => '0.00',
        'shrink' => '1.00',
        'shrink_tolerance' => '33.33',
        'is_deleted' => 0,
    ]);

    DB::table('migrations')
        ->where('migration', '2026_09_25_093809_backfill_finishinghandmade_null_shrink_tolerance')
        ->delete();

    $exitCode = Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_09_25_093809_backfill_finishinghandmade_null_shrink_tolerance.php',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $withMaterials->refresh();
    $largeBatch->refresh();
    $alreadyFilled->refresh();

    expect((string) $withMaterials->shrink_tolerance)->toBe('6.96')
        ->and((string) $largeBatch->shrink_tolerance)->toBe('4.43')
        ->and((string) $alreadyFilled->shrink_tolerance)->toBe('33.33');

    $withMaterials->delete();
    $largeBatch->delete();
    $alreadyFilled->delete();
});
