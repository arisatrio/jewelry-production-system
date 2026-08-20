<?php

use App\Models\Production;
use App\Models\User;
use App\Policies\ProductionPolicy;
use App\Support\SpkApprovalService;
use App\Support\SpkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('draft abilities allow any authenticated user to edit and approve (kirim ke produksi)', function () {
    $production = new Production(['status' => '']);
    $user = new User(['name' => 'IT WHOJ']);
    $abilities = app(SpkApprovalService::class)->abilitiesFor($production, $user);

    expect($abilities['canEdit'])->toBeTrue()
        ->and($abilities['canApprove'])->toBeTrue()
        ->and($abilities['canManagerApprove'])->toBeFalse()
        ->and($abilities['canReject'])->toBeFalse()
        ->and($abilities['statusLabel'])->toBe('Draft');
});

test('pending abilities allow edit but not approve (waiting manager)', function () {
    $production = new Production(['status' => SpkApprovalService::STATUS_PENDING]);
    $user = new User(['name' => 'IT WHOJ']);
    $abilities = app(SpkApprovalService::class)->abilitiesFor($production, $user);

    expect($abilities['canEdit'])->toBeTrue()
        ->and($abilities['canApprove'])->toBeFalse()
        ->and($abilities['canManagerApprove'])->toBeFalse();
});

test('approved abilities still allow edit after sent to production', function () {
    $production = new Production(['status' => SpkApprovalService::STATUS_DONE]);
    $user = new User(['name' => 'IT WHOJ']);
    $abilities = app(SpkApprovalService::class)->abilitiesFor($production, $user);

    expect($abilities['canEdit'])->toBeTrue()
        ->and($abilities['canDelete'])->toBeFalse()
        ->and($abilities['canApprove'])->toBeFalse();
});

test('policy allows update before sending to production', function () {
    $policy = app(ProductionPolicy::class);
    $user = new User(['name' => 'IT WHOJ']);

    expect($policy->update($user, new Production(['status' => ''])))->toBeTrue()
        ->and($policy->update($user, new Production(['status' => SpkApprovalService::STATUS_PENDING])))->toBeTrue()
        ->and($policy->update($user, new Production(['status' => SpkApprovalService::STATUS_DONE])))->toBeTrue()
        ->and($policy->delete($user, new Production(['status' => SpkApprovalService::STATUS_DONE])))->toBeFalse();
});

test('approval service submits draft to pending manager', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('spv-tester');
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(81000, 81999)),
        'description' => 'Unit submit '.Str::upper(Str::random(4)),
    ]);

    $spv = User::factory()->spvPrd()->create();
    $draftAbilities = $service->abilitiesFor($production, $spv);
    expect($draftAbilities['canApprove'])->toBeTrue()
        ->and($draftAbilities['canSubmit'])->toBeTrue();

    $updated = $service->submit($production, 'spv-tester');

    expect($updated->status)->toBe(SpkApprovalService::STATUS_PENDING)
        ->and($service->isPendingManager($updated))->toBeTrue();

    $manager = User::factory()->managerProduksi()->create();
    $abilities = $service->abilitiesFor($updated, $manager);
    expect($abilities['canApprove'])->toBeTrue()
        ->and($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['role'])->toBe('MANAGER');

    $spvAbilities = $service->abilitiesFor($updated, $spv);
    expect($spvAbilities['canApprove'])->toBeTrue()
        ->and($spvAbilities['canReject'])->toBeFalse();

    $production->delete();
    $manager->delete();
    $spv->delete();
});

test('kirim ke produksi changes draft to pending and fills disetujui oleh', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(82000, 82999)),
    ]);

    $updated = $service->approve($production, 'spv-tester', 'OK');

    expect($updated->status)->toBe(SpkApprovalService::STATUS_PENDING)
        ->and($service->isPendingManager($updated))->toBeTrue();

    $footer = $service->footerColumns($updated);
    expect($footer)->toHaveCount(3)
        ->and($footer[1]['title'])->toBe('Disetujui Oleh')
        ->and($footer[1]['name'])->toBe('spv-tester')
        ->and($footer[2]['title'])->toBe('Manager Produksi')
        ->and($footer[2]['name'])->toBe('-');

    $production->delete();
});

test('manager approve changes status to spkdone', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(82000, 82999)),
    ]);

    $updated = $service->managerApprove($production, 'manager-tester', 'OK');

    expect($updated->status)->toBe(SpkApprovalService::STATUS_DONE)
        ->and($service->isApproved($updated))->toBeTrue();

    $footer = $service->footerColumns($updated);
    expect($footer)->toHaveCount(3)
        ->and($footer[2]['title'])->toBe('Manager Produksi')
        ->and($footer[2]['name'])->toBe('manager-tester');

    $production->delete();
});

test('approve rejects non-draft status', function () {
    $service = app(SpkApprovalService::class);
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(82100, 82199)),
    ]);

    expect(fn () => $service->approve($production, 'it-whoj', null))
        ->toThrow(InvalidArgumentException::class, 'Hanya SPK berstatus Draft yang dapat dikirim ke Produksi.');

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
