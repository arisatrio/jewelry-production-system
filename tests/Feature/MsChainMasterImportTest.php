<?php

use App\Models\MsChain;
use App\Support\MsChainMasterImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('mschain')) {
        $this->markTestSkipped('Table mschain is not available.');
    }

    MsChain::query()->delete();
});

test('ms chain master importer loads bundled csv rows', function () {
    $result = app(MsChainMasterImporter::class)->importDefault('tester');

    expect($result['created'])->toBe(66)
        ->and($result['updated'])->toBe(0)
        ->and(MsChain::query()->notDeleted()->count())->toBe(66);

    $sample = MsChain::query()
        ->notDeleted()
        ->where('item_code', 'CDOV030')
        ->where('length', '40.00')
        ->first();

    expect($sample)->not->toBeNull()
        ->and($sample->chain_category)->toBe(MsChain::CATEGORY_PENDANT)
        ->and($sample->item)->toBe('Chain Classic')
        ->and($sample->item_name)->toBe('Chain Classic - CDOV030 - 40 cm');
});

test('ms chain master import is idempotent', function () {
    $importer = app(MsChainMasterImporter::class);

    $first = $importer->importDefault('tester');
    $second = $importer->importDefault('tester');

    expect($first['created'])->toBe(66)
        ->and($second['created'])->toBe(0)
        ->and($second['skipped'])->toBe(66)
        ->and(MsChain::query()->notDeleted()->count())->toBe(66);
});

test('chain import artisan command loads master csv', function () {
    Artisan::call('chain:import', [
        '--actor' => 'artisan-test',
        '--only' => 'master',
    ]);

    expect(Artisan::output())->toContain('Master import complete')
        ->and(MsChain::query()->notDeleted()->count())->toBe(66);
});
