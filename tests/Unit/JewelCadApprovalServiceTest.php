<?php

use App\Models\JewelCadRequest;
use App\Support\JewelCadApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('create footer shows creator and empty manager and operator columns', function () {
    $footer = app(JewelCadApprovalService::class)->createFooterColumns('Aditia Ardiana');

    expect($footer)->toHaveCount(3)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Aditia Ardiana')
        ->and($footer[0]['date'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('-')
        ->and($footer[1]['date'])->toBe('-')
        ->and($footer[2]['title'])->toBe('Operator JewelCAD')
        ->and($footer[2]['name'])->toBe('-')
        ->and($footer[2]['date'])->toBe('-');
});

test('footer columns map manager and operator from sysapproval history', function () {
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

    expect($footer)->toHaveCount(3)
        ->and($footer[0]['title'])->toBe('Dibuat Oleh')
        ->and($footer[0]['name'])->toBe('Frida')
        ->and($footer[1]['title'])->toBe('Manager Produksi')
        ->and($footer[1]['name'])->toBe('Manager Production')
        ->and($footer[2]['title'])->toBe('Operator JewelCAD')
        ->and($footer[2]['name'])->toBe('Anto');

    DB::connection('third')
        ->table('sysapproval')
        ->where('doc_name', JewelCadApprovalService::DOC_NAME)
        ->where('doc_id', $request->row_id)
        ->delete();

    $request->delete();
});
