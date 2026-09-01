<?php

use App\Http\Requests\StoreJewelCadRequestRequest;
use App\Http\Requests\SyncJewelCadSpkRequest;
use App\Models\JewelCadRequestDetail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('estimation brj validation accepts up to three decimal places', function () {
    $rules = (new StoreJewelCadRequestRequest)->rules();

    $validator = Validator::make([
        'details' => [
            ['estimation_brj' => '18.125'],
        ],
    ], [
        'details.*.estimation_brj' => $rules['details.*.estimation_brj'],
    ]);

    expect($validator->fails())->toBeFalse();
});

test('estimation brj validation rejects more than three decimal places', function () {
    $rules = (new StoreJewelCadRequestRequest)->rules();

    $validator = Validator::make([
        'details' => [
            ['estimation_brj' => '18.1234'],
        ],
    ], [
        'details.*.estimation_brj' => $rules['details.*.estimation_brj'],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('details.0.estimation_brj'))->toBeTrue();
});

test('sync jewelcad spk estimation brj allows three decimals', function () {
    $rules = (new SyncJewelCadSpkRequest)->rules();

    $ok = Validator::make(['estimation_brj' => '9.875'], [
        'estimation_brj' => $rules['estimation_brj'],
    ]);
    $bad = Validator::make(['estimation_brj' => '9.8755'], [
        'estimation_brj' => $rules['estimation_brj'],
    ]);

    expect($ok->fails())->toBeFalse()
        ->and($bad->fails())->toBeTrue();
});

test('jewelcad request detail casts estimation brj to three decimals', function () {
    $detail = JewelCadRequestDetail::factory()->create([
        'estimation_brj' => '12.345',
    ]);

    expect((string) $detail->fresh()->estimation_brj)->toBe('12.345');
});
