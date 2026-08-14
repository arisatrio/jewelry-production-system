<?php

use App\Models\User;
use App\Support\SpkApprovalRoles;
use App\Support\SpkPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('spv factory user can submit but cannot approve', function () {
    $user = User::factory()->spvPrd()->create();

    expect(SpkApprovalRoles::canSubmit($user))->toBeTrue()
        ->and(SpkApprovalRoles::canEditDraft($user))->toBeTrue()
        ->and(SpkApprovalRoles::canApprove($user))->toBeFalse()
        ->and(SpkApprovalRoles::roleLabel($user))->toBe('SPV PRODUCTION');

    $user->delete();
});

test('manager factory user can approve and reject', function () {
    $user = User::factory()->managerProduksi()->create();

    expect(SpkApprovalRoles::canApprove($user))->toBeTrue()
        ->and(SpkApprovalRoles::canReject($user))->toBeTrue()
        ->and(SpkApprovalRoles::canSubmit($user))->toBeFalse();

    $user->delete();
});

test('admin spk factory user can edit draft but cannot submit', function () {
    $user = User::factory()->adminSpk()->create();

    expect(SpkApprovalRoles::canEditDraft($user))->toBeTrue()
        ->and(SpkApprovalRoles::canSubmit($user))->toBeFalse()
        ->and(SpkApprovalRoles::canApprove($user))->toBeFalse();

    $user->delete();
});

test('administrator can submit and approve', function () {
    $user = User::factory()->administratorSpk()->create();

    expect(SpkApprovalRoles::canSubmit($user))->toBeTrue()
        ->and(SpkApprovalRoles::canApprove($user))->toBeTrue()
        ->and($user->permissionNames())->toContain(SpkPermissions::SUBMIT)
        ->and($user->permissionNames())->toContain(SpkPermissions::APPROVE);

    $user->delete();
});

test('guest permissions allow edit draft but not submit', function () {
    config([
        'spk.approval.guest_permissions' => [
            SpkPermissions::VIEW,
            SpkPermissions::CREATE,
            SpkPermissions::EDIT_DRAFT,
        ],
    ]);

    expect(SpkApprovalRoles::canEditDraft(null))->toBeTrue()
        ->and(SpkApprovalRoles::canSubmit(null))->toBeFalse()
        ->and(SpkApprovalRoles::roleLabel(null))->toBe('guest');
});
