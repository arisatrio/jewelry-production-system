<?php

use App\Models\Resin;
use App\Support\ResinDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('resin doc number generator uses year rsn format like spk and jewelcad', function () {
    Resin::factory()->create([
        'doc_no' => '2196/RSN/00012',
        'trans_date' => '2196-08-20',
    ]);

    $generator = app(ResinDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2196-08-21')))
        ->toBe('2196/RSN/00013');
});

test('resin doc number generator starts at one for new year', function () {
    Resin::factory()->create([
        'doc_no' => '2197/RSN/01601',
        'trans_date' => '2197-12-31',
    ]);

    $generator = app(ResinDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2198-01-01')))
        ->toBe('2198/RSN/00001');
});

test('resin doc number generator ignores legacy res prefix numbers', function () {
    Resin::factory()->create([
        'doc_no' => 'RES0000001',
        'trans_date' => '2199-08-20',
    ]);

    $generator = app(ResinDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2199-08-21')))
        ->toBe('2199/RSN/00001');
});
