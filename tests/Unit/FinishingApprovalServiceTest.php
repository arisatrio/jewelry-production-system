<?php

use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Models\User;
use App\Support\FinishingApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('finishing approval submit requires spk', function () {
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => null,
        'status' => null,
    ]);

    expect(fn () => app(FinishingApprovalService::class)->submit($document, 'system'))
        ->toThrow(InvalidArgumentException::class);

    $document->delete();
});

test('finishing approval flow updates status through stages', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/FINUT'.Str::upper(Str::random(3)),
    ]);
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => null,
    ]);

    $service = app(FinishingApprovalService::class);

    $submitted = $service->submit($document, 'Operator');
    expect($submitted->status)->toBe(FinishingApprovalService::STATUS_SUBMITTED);

    $approved = $service->managerApprove($submitted, 'Manager');
    expect($approved->status)->toBe(FinishingApprovalService::STATUS_MANAGER);

    $done = $service->complete($approved, 'Operator');
    expect($done->status)->toBe(FinishingApprovalService::STATUS_DONE)
        ->and($service->isDone($done))->toBeTrue();

    $footer = $service->footerColumns($done, 'system');
    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[1]['title'])->toBe('Manager Produksi');

    $document->delete();
    $production->delete();
});

test('finishing approval abilities respect roles', function () {
    $document = FinishingHandmade::factory()->create([
        'status' => FinishingApprovalService::STATUS_SUBMITTED,
        'spk_id' => 1,
    ]);

    $admin = User::factory()->adminSpk()->make(['name' => 'admin']);
    $abilities = app(FinishingApprovalService::class)->abilitiesFor($document, $admin);

    expect($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['canManagerApprove'])->toBeTrue()
        ->and($abilities['canComplete'])->toBeFalse();

    $document->delete();
});
