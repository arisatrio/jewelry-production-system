<?php

use App\Models\JewelCadRequest;
use App\Support\JewelCadDocNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('jewelcad doc number generator uses year jwc format like spk', function () {
    JewelCadRequest::factory()->create([
        'doc_no' => '2196/JWC/00012',
        'trans_date' => '2196-08-20',
    ]);

    $generator = app(JewelCadDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2196-08-21')))
        ->toBe('2196/JWC/00013');
});

test('jewelcad doc number generator starts at one for new year', function () {
    JewelCadRequest::factory()->create([
        'doc_no' => '2197/JWC/01601',
        'trans_date' => '2197-12-31',
    ]);

    $generator = app(JewelCadDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2198-01-01')))
        ->toBe('2198/JWC/00001');
});

test('jewelcad doc number generator ignores legacy jwc prefix numbers', function () {
    JewelCadRequest::factory()->create([
        'doc_no' => 'JWC0000001',
        'trans_date' => '2199-08-20',
    ]);

    $generator = app(JewelCadDocNumberGenerator::class);

    expect($generator->generate(Carbon::parse('2199-08-21')))
        ->toBe('2199/JWC/00001');
});
