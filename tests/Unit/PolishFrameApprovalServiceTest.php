<?php

use App\Models\PolishFrame;
use App\Models\Production;
use App\Models\User;
use App\Support\PolishFrameApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('polish frame approval submit requires spk', function () {
    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => null,
        'status' => null,
    ]);

    expect(fn () => app(PolishFrameApprovalService::class)->submit($document, 'system'))
        ->toThrow(InvalidArgumentException::class);

    $document->delete();
});

test('polish frame approval flow updates status through stages', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/PRKUT'.Str::upper(Str::random(3)),
    ]);
    $document = PolishFrame::factory()->create([
        'doc_no' => 'PRK'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $service = app(PolishFrameApprovalService::class);

    $submitted = $service->submit($document, 'Operator');
    expect($submitted->status)->toBe(PolishFrameApprovalService::STATUS_SUBMITTED);

    $approved = $service->managerApprove($submitted, 'Manager');
    expect($approved->status)->toBe(PolishFrameApprovalService::STATUS_MANAGER);

    $done = $service->complete($approved, 'Operator');
    expect($done->status)->toBe(PolishFrameApprovalService::STATUS_DONE)
        ->and($service->isDone($done))->toBeTrue();

    $footer = $service->footerColumns($done, 'system');
    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[1]['title'])->toBe('Manager Produksi');

    $document->delete();
    $production->delete();
});

test('polish frame approval abilities respect roles', function () {
    $document = PolishFrame::factory()->create([
        'status' => PolishFrameApprovalService::STATUS_SUBMITTED,
        'spk_id' => 1,
    ]);

    $admin = User::factory()->adminSpk()->make(['name' => 'admin']);
    $abilities = app(PolishFrameApprovalService::class)->abilitiesFor($document, $admin);

    expect($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['canManagerApprove'])->toBeTrue()
        ->and($abilities['canComplete'])->toBeFalse();

    $document->delete();
});
