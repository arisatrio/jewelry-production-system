<?php

use App\Models\User;
use App\Support\SpkApprovalService;
use App\Support\SpkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('approval service submits draft to pending manager', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('spv-tester');
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(81000, 81999)),
        'description' => 'Unit submit '.Str::upper(Str::random(4)),
    ]);

    $updated = $service->submit($production, 'spv-tester');

    expect($updated->status)->toBe(SpkApprovalService::STATUS_PENDING)
        ->and($service->isPendingManager($updated))->toBeTrue();

    $manager = User::factory()->managerProduksi()->create();
    $abilities = $service->abilitiesFor($updated, $manager);
    expect($abilities['canApprove'])->toBeTrue()
        ->and($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['role'])->toBe('MANAGER');

    $production->delete();
    $manager->delete();
});

test('approval service approves pending to spkdone', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(82000, 82999)),
    ]);

    $updated = $service->approve($production, 'manager-tester', 'OK');

    expect($updated->status)->toBe(SpkApprovalService::STATUS_DONE)
        ->and($service->isApproved($updated))->toBeTrue();

    $footer = $service->footerColumns($updated);
    expect($footer)->toHaveCount(3)
        ->and($footer[1]['title'])->toBe('Disetujui Oleh')
        ->and($footer[2]['title'])->toBe('Manager Produksi')
        ->and($footer[2]['name'])->toBe('manager-tester');

    $production->delete();
});

test('approval service rejects pending back to draft', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(83000, 83999)),
    ]);

    $updated = $service->reject($production, 'manager-tester', 'Revisi item');

    expect($updated->status)->toBe('')
        ->and($service->isDraft($updated))->toBeTrue();

    $production->delete();
});
