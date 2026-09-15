<?php

use App\Http\Controllers\FinishingController;
use App\Models\FinishingHandmade;

test('finishing handmade model uses third connection and finishinghandmade table', function () {
    $model = new FinishingHandmade;

    expect($model->getConnectionName())->toBe('third')
        ->and($model->getTable())->toBe('finishinghandmade')
        ->and($model->getKeyName())->toBe('row_id');
});

test('finishing handmade status labels map known codes', function (string $status, string $label) {
    $document = new FinishingHandmade(['status' => $status]);

    expect($document->statusLabel())->toBe($label)
        ->and($document->isDone())->toBe(in_array($status, [
            FinishingHandmade::STATUS_DONE,
            FinishingHandmade::STATUS_REPARATION_DONE,
        ], true));
})->with([
    'open null' => ['', 'Open'],
    'loket' => ['FHM010', 'Serahkan ke Loket'],
    'pengrajin' => ['FHM020', 'Serahkan ke Pengrajin'],
    'ppic' => ['FHM040', 'Serahkan ke PPIC'],
    'done' => ['FHMDONE', 'Completed'],
    'reparasi open' => ['RFH010', 'Serahkan ke Pengrajin'],
    'reparasi done' => ['RFHDONE', 'Completed'],
]);

test('finishing controller workflow stages follow finishing status flow', function () {
    $controller = new ReflectionClass(FinishingController::class);
    $method = $controller->getMethod('workflowStatus');
    $method->setAccessible(true);

    $document = new FinishingHandmade(['status' => FinishingHandmade::STATUS_TO_CRAFTSMAN]);
    $workflow = $method->invoke(app(FinishingController::class), $document);

    expect($workflow['key'])->toBe('craftsman')
        ->and($workflow['stageIndex'])->toBe(2)
        ->and($workflow['stages'])->toHaveCount(5)
        ->and($workflow['stages'][0]['label'])->toBe('Open')
        ->and($workflow['stages'][4]['label'])->toBe('Done');
});
