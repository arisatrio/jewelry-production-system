<?php

use App\Models\FinishingHandmade;
use App\Support\FinishingApprovalService;
use Tests\TestCase;

uses(TestCase::class);

test('finishing approval writes legacy-compatible fhm status codes', function () {
    expect(FinishingApprovalService::STATUS_SUBMITTED)->toBe(FinishingHandmade::STATUS_OPEN)
        ->and(FinishingApprovalService::STATUS_MANAGER)->toBe(FinishingHandmade::STATUS_TO_PPIC)
        ->and(FinishingApprovalService::STATUS_DONE)->toBe(FinishingHandmade::STATUS_DONE);
});

test('finishing approval still recognizes temporary fin status codes', function (string $status, string $expectedKey) {
    $service = app(FinishingApprovalService::class);
    $document = new FinishingHandmade(['status' => $status]);

    expect($service->resolveKey($document))->toBe($expectedKey);
})->with([
    'fin submitted' => [FinishingApprovalService::LEGACY_NEW_STATUS_SUBMITTED, FinishingApprovalService::KEY_SUBMITTED],
    'fin manager' => [FinishingApprovalService::LEGACY_NEW_STATUS_MANAGER, FinishingApprovalService::KEY_MANAGER],
    'fin done' => [FinishingApprovalService::LEGACY_NEW_STATUS_DONE, FinishingApprovalService::KEY_DONE],
    'fhm submitted' => [FinishingApprovalService::STATUS_SUBMITTED, FinishingApprovalService::KEY_SUBMITTED],
    'fhm manager' => [FinishingApprovalService::STATUS_MANAGER, FinishingApprovalService::KEY_MANAGER],
    'fhm done' => [FinishingApprovalService::STATUS_DONE, FinishingApprovalService::KEY_DONE],
]);
