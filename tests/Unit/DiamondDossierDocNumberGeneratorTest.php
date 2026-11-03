<?php

use App\Support\DiamondDossierDocNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! Schema::connection('third')->hasTable('trdiamond')) {
        $this->markTestSkipped('Table trdiamond is not available.');
    }

    DB::connection('third')->beginTransaction();
});

afterEach(function () {
    $connection = DB::connection('third');

    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
});

test('dossier doc number generator increments from latest d prefix', function () {
    $seedDocNo = 'D9999900';

    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => $seedDocNo,
        'diamond_type' => 'Dossier',
        'entry_date' => '2026-10-29',
        'supplier' => 'RJ',
        'crt' => '0.500',
        'shape_id' => 1,
        'color' => 'D',
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
    ]);

    $next = app(DiamondDossierDocNumberGenerator::class)->generate();

    expect($next)->toBe('D9999901');
});

test('dossier doc number generator ignores non legacy doc prefixes', function () {
    DB::connection('third')->table('trdiamond')->insert([
        'doc_no' => 'DOS0000099',
        'diamond_type' => 'Dossier',
        'entry_date' => '2026-10-29',
        'supplier' => 'RJ',
        'crt' => '0.500',
        'shape_id' => 1,
        'color' => 'D',
        'diamondmounting_id' => 0,
        'is_used' => 0,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'tester',
        'modified_date' => now(),
        'modified_by' => 'tester',
    ]);

    $next = app(DiamondDossierDocNumberGenerator::class)->generate();

    expect($next)->toMatch('/^D\d{7}$/');
});
