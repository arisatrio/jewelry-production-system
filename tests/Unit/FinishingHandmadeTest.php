<?php

use App\Models\FinishingHandmade;
use App\Support\FinishingApprovalService;
use Tests\TestCase;

uses(TestCase::class);

test('finishing handmade model uses third connection and finishinghandmade table', function () {
    $model = new FinishingHandmade;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('finishinghandmade')
        ->and($model->getKeyName())->toBe('row_id');
});

test('finishing handmade status labels follow coran-like approval flow', function (string $status, string $label, bool $done) {
    $document = new FinishingHandmade(['status' => $status]);

    expect($document->statusLabel())->toBe($label)
        ->and($document->isDone())->toBe($done);
})->with([
    'open null' => ['', 'Open', false],
    'submitted new' => [FinishingApprovalService::STATUS_SUBMITTED, 'Pengajuan', false],
    'manager new' => [FinishingApprovalService::STATUS_MANAGER, 'Serahkan ke PPIC', false],
    'done new' => [FinishingApprovalService::STATUS_DONE, 'Completed', true],
    'legacy loket' => [FinishingHandmade::STATUS_OPEN, 'Pengajuan', false],
    'legacy pengrajin' => [FinishingHandmade::STATUS_TO_CRAFTSMAN, 'Pengajuan', false],
    'legacy ppic' => [FinishingHandmade::STATUS_TO_PPIC, 'Serahkan ke PPIC', false],
    'legacy done' => [FinishingHandmade::STATUS_DONE, 'Completed', true],
    'reparasi open' => [FinishingHandmade::STATUS_REPARATION_OPEN, 'Pengajuan', false],
    'reparasi done' => [FinishingHandmade::STATUS_REPARATION_DONE, 'Completed', true],
]);

test('finishing approval service maps stages like coran', function () {
    $service = app(FinishingApprovalService::class);
    $document = new FinishingHandmade(['status' => FinishingApprovalService::STATUS_SUBMITTED]);
    $workflow = $service->map($document);

    expect($workflow['key'])->toBe(FinishingApprovalService::KEY_SUBMITTED)
        ->and($workflow['stageIndex'])->toBe(1)
        ->and($workflow['stages'])->toHaveCount(4)
        ->and($workflow['stages'][0]['label'])->toBe('Open')
        ->and($workflow['stages'][1]['label'])->toBe('Pengajuan')
        ->and($workflow['stages'][2]['label'])->toBe('Serahkan ke PPIC')
        ->and($workflow['stages'][3]['label'])->toBe('Done');
});
