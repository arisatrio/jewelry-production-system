<?php

use App\Models\Resin;
use App\Models\ResinDetail;
use App\Models\User;
use App\Support\ResinApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('create footer shows creator and empty manager column', function () {
    $footer = app(ResinApprovalService::class)->createFooterColumns('Aditia Ardiana');

    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Aditia Ardiana')
        ->and($footer[0]['date'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('-')
        ->and($footer[1]['date'])->toBe('-');
});

test('resin submit updates draft status to pending manager', function () {
    $resin = Resin::factory()->create([
        'status' => 'DRAFT',
    ]);
    ResinDetail::factory()->create([
        'row_id' => $resin->row_id,
    ]);

    $updated = app(ResinApprovalService::class)->submit($resin, 'Operator Test');

    expect($updated->status)->toBe(ResinApprovalService::STATUS_SUBMITTED);
});

test('resin manager approve updates pending status to manager approved', function () {
    $resin = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
    ]);

    $updated = app(ResinApprovalService::class)->managerApprove($resin, 'Manager Test');

    expect($updated->status)->toBe(ResinApprovalService::STATUS_MANAGER);
});

test('resin complete updates manager approved status to done', function () {
    $resin = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_MANAGER,
    ]);

    $updated = app(ResinApprovalService::class)->complete($resin, 'Operator Test');

    expect($updated->status)->toBe(ResinApprovalService::STATUS_DONE);
});

test('resin edit abilities stay enabled while pending manager approval', function () {
    $resin = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
    ]);
    $user = new User(['name' => 'Operator Test']);

    $abilities = app(ResinApprovalService::class)->abilitiesFor($resin, $user);

    expect($abilities['canEdit'])->toBeTrue()
        ->and($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['canManagerApprove'])->toBeTrue();
});

test('resin edit abilities are disabled after manager approval', function () {
    $resin = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_MANAGER,
    ]);
    $user = new User(['name' => 'Operator Test']);

    $abilities = app(ResinApprovalService::class)->abilitiesFor($resin, $user);

    expect($abilities['canEdit'])->toBeFalse()
        ->and($abilities['canComplete'])->toBeTrue();
});

test('resin status label maps known codes to readable text', function () {
    $service = app(ResinApprovalService::class);

    $draft = Resin::factory()->create(['status' => 'DRAFT']);
    $submitted = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
    ]);
    $manager = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_MANAGER,
    ]);
    $done = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_DONE,
    ]);

    expect($service->statusLabelFor($draft))->toBe('Draft')
        ->and($service->statusLabelFor($submitted))->toBe('Pengajuan Approval')
        ->and($service->statusLabelFor($manager))->toBe('Serahkan ke Resin')
        ->and($service->statusLabelFor($done))->toBe('Completed');
});

test('footer columns map manager from sysapproval history', function () {
    if (! Schema::connection('third')->hasTable('sysapproval')) {
        $this->markTestSkipped('Tabel sysapproval tidak tersedia.');
    }

    $service = app(ResinApprovalService::class);
    $resin = Resin::factory()->create([
        'created_by' => 'Frida',
        'created_date' => now()->subDay(),
        'status' => ResinApprovalService::STATUS_DONE,
    ]);

    $now = now();

    DB::connection('third')->table('sysapproval')->insert([
        [
            'doc_id' => $resin->row_id,
            'doc_no' => $resin->doc_no,
            'doc_name' => ResinApprovalService::DOC_NAME,
            'status' => ResinApprovalService::STATUS_SUBMITTED,
            'approve' => ResinApprovalService::APPROVE_OK,
            'notes' => 'Pengajuan Approval',
            'is_deleted' => 0,
            'created_date' => $now->copy()->subHours(2),
            'created_by' => 'Frida',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
        [
            'doc_id' => $resin->row_id,
            'doc_no' => $resin->doc_no,
            'doc_name' => ResinApprovalService::DOC_NAME,
            'status' => ResinApprovalService::STATUS_MANAGER,
            'approve' => ResinApprovalService::APPROVE_OK,
            'notes' => 'Serahkan ke Resin',
            'is_deleted' => 0,
            'created_date' => $now->copy()->subHour(),
            'created_by' => 'Manager Production',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
    ]);

    $footer = $service->footerColumns($resin);

    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Frida')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('Manager Production');
});
