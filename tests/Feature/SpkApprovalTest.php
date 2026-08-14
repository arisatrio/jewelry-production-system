<?php

use App\Models\Production;
use App\Models\User;
use App\Support\SpkApprovalService;
use App\Support\SpkService;
use Illuminate\Support\Str;

test('spv can submit draft spk to manager and fill disetujui oleh footer', function () {
    $spv = User::factory()->spvPrd()->create([
        'name' => 'SPV Tester '.Str::upper(Str::random(4)),
    ]);
    $production = app(SpkService::class)->createStock($spv->name);
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
        'description' => 'Approval submit '.Str::upper(Str::random(4)),
    ]);

    $this->actingAs($spv)
        ->post(route('spk.submit', $production->row_id))
        ->assertRedirect(route('spk.show', $production->spk_no));

    $production->refresh();

    expect($production->status)->toBe(SpkApprovalService::STATUS_PENDING);

    $this->actingAs($spv)
        ->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approvalFooter.1.title', 'Disetujui Oleh')
            ->where('approvalFooter.1.name', $spv->name)
            ->where('approvalFooter.2.title', 'Manager Produksi')
            ->where('approvalFooter.2.name', '-')
        );

    $production->delete();
    $spv->delete();
});

test('admin cannot submit draft spk to manager', function () {
    $admin = User::factory()->adminSpk()->create();
    $production = app(SpkService::class)->createStock($admin->name);
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $this->actingAs($admin)
        ->post(route('spk.submit', $production->row_id))
        ->assertForbidden();

    expect($production->fresh()->status)->toBe('');

    $production->delete();
    $admin->delete();
});

test('manager can approve pending spk to spkdone', function () {
    $manager = User::factory()->managerProduksi()->create();
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $this->actingAs($manager)
        ->post(route('spk.approve', $production->row_id), ['notes' => null])
        ->assertRedirect(route('spk.show', $production->spk_no));

    expect($production->fresh()->status)->toBe(SpkApprovalService::STATUS_DONE);

    $this->actingAs($manager)
        ->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approvalFooter.2.title', 'Manager Produksi')
            ->where('approvalFooter.2.name', $manager->name)
        );

    $production->delete();
    $manager->delete();
});

test('manager reject returns spk to draft with notes', function () {
    $manager = User::factory()->managerProduksi()->create();
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $this->actingAs($manager)
        ->post(route('spk.reject', $production->row_id), [
            'notes' => 'Perlu revisi ukuran',
        ])
        ->assertRedirect(route('spk.form', $production->row_id));

    expect($production->fresh()->status)->toBe('');

    $production->delete();
    $manager->delete();
});

test('manager reject requires notes', function () {
    $manager = User::factory()->managerProduksi()->create();
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_PENDING,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(80000, 89999)),
    ]);

    $this->actingAs($manager)
        ->post(route('spk.reject', $production->row_id), ['notes' => ''])
        ->assertSessionHasErrors('notes');

    expect($production->fresh()->status)->toBe(SpkApprovalService::STATUS_PENDING);

    $production->delete();
    $manager->delete();
});

test('spk show includes approval abilities for current role', function () {
    $spv = User::factory()->spvPrd()->create();
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $production->update(['status' => '']);

    $this->actingAs($spv)
        ->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->where('approval.role', 'SPV PRODUCTION')
            ->where('approval.canSubmit', true)
            ->where('approval.canApprove', false)
            ->has('approvalFooter', 3)
            ->where('approvalFooter.1.title', 'Disetujui Oleh')
            ->where('approvalFooter.2.title', 'Manager Produksi')
        );

    $spv->delete();
});
