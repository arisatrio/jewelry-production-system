<?php

use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\User;
use App\Support\JewelCadApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('create footer shows creator and empty manager column', function () {
    $footer = app(JewelCadApprovalService::class)->createFooterColumns('Aditia Ardiana');

    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Aditia Ardiana')
        ->and($footer[0]['date'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('-')
        ->and($footer[1]['date'])->toBe('-');
});

test('jewelcad submit updates draft status to pending manager', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => 'DRAFT',
    ]);
    JewelCadRequestDetail::factory()->create([
        'row_id' => $request->row_id,
    ]);

    $updated = app(JewelCadApprovalService::class)->submit($request, 'Operator Test');

    expect($updated->status)->toBe(JewelCadApprovalService::STATUS_SUBMITTED);

});

test('jewelcad manager approve updates pending status to manager approved', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);

    $updated = app(JewelCadApprovalService::class)->managerApprove($request, 'Manager Test');

    expect($updated->status)->toBe(JewelCadApprovalService::STATUS_MANAGER);
});

test('jewelcad manager approve abilities are available for pending request', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);
    $user = new User(['name' => 'Manager Test']);

    $abilities = app(JewelCadApprovalService::class)->abilitiesFor($request, $user);

    expect($abilities['canManagerApprove'])->toBeTrue()
        ->and($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['canEdit'])->toBeFalse()
        ->and($abilities['canOpenEdit'])->toBeTrue()
        ->and($abilities['canDelete'])->toBeTrue()
        ->and($abilities['canComplete'])->toBeFalse();
});

test('jewelcad complete updates manager approved status to done', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);

    $updated = app(JewelCadApprovalService::class)->complete($request, 'Operator Test');

    expect($updated->status)->toBe(JewelCadApprovalService::STATUS_DONE);
});

test('jewelcad complete abilities are available for manager approved request', function () {
    $request = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);
    $user = new User(['name' => 'Operator Test']);

    $abilities = app(JewelCadApprovalService::class)->abilitiesFor($request, $user);

    expect($abilities['canComplete'])->toBeTrue()
        ->and($abilities['canManagerApprove'])->toBeFalse()
        ->and($abilities['canSubmit'])->toBeFalse()
        ->and($abilities['canEdit'])->toBeFalse()
        ->and($abilities['canOpenEdit'])->toBeTrue()
        ->and($abilities['canDelete'])->toBeTrue();
});

test('jewelcad status label maps known codes to readable text', function () {
    $service = app(JewelCadApprovalService::class);

    $draft = JewelCadRequest::factory()->create(['status' => 'DRAFT']);
    $submitted = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_SUBMITTED,
    ]);
    $manager = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_MANAGER,
    ]);
    $done = JewelCadRequest::factory()->create([
        'status' => JewelCadApprovalService::STATUS_DONE,
    ]);

    expect($service->statusLabelFor($draft))->toBe('Draft')
        ->and($service->statusLabelFor($submitted))->toBe('Pengajuan Approval')
        ->and($service->statusLabelFor($manager))->toBe('Serahkan ke JWCAD')
        ->and($service->statusLabelFor($done))->toBe('Completed');
});

test('footer columns map manager from sysapproval history', function () {
    if (! Schema::connection('third')->hasTable('sysapproval')) {
        $this->markTestSkipped('Tabel sysapproval tidak tersedia.');
    }

    $service = app(JewelCadApprovalService::class);
    $request = JewelCadRequest::factory()->create([
        'created_by' => 'Frida',
        'created_date' => now()->subDay(),
        'status' => JewelCadApprovalService::STATUS_DONE,
    ]);

    $now = now();

    DB::connection('third')->table('sysapproval')->insert([
        [
            'doc_id' => $request->row_id,
            'doc_no' => $request->doc_no,
            'doc_name' => JewelCadApprovalService::DOC_NAME,
            'status' => JewelCadApprovalService::STATUS_SUBMITTED,
            'approve' => JewelCadApprovalService::APPROVE_OK,
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
            'doc_id' => $request->row_id,
            'doc_no' => $request->doc_no,
            'doc_name' => JewelCadApprovalService::DOC_NAME,
            'status' => JewelCadApprovalService::STATUS_MANAGER,
            'approve' => JewelCadApprovalService::APPROVE_OK,
            'notes' => 'Serahkan ke JWCAD',
            'is_deleted' => 0,
            'created_date' => $now->copy()->subHour(),
            'created_by' => 'Manager Production',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
        [
            'doc_id' => $request->row_id,
            'doc_no' => $request->doc_no,
            'doc_name' => JewelCadApprovalService::DOC_NAME,
            'status' => JewelCadApprovalService::STATUS_DONE,
            'approve' => JewelCadApprovalService::APPROVE_OK,
            'notes' => 'Completed',
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => 'Anto',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
    ]);

    $footer = $service->footerColumns($request);

    expect($footer)->toHaveCount(2)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Frida')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('Manager Production');

});
