<?php

use App\Models\Coran;
use App\Support\CoranApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('coran approval service maps done status and stages', function () {
    $service = app(CoranApprovalService::class);
    $coran = Coran::factory()->done()->make();

    $mapped = $service->map($coran);

    expect($mapped['key'])->toBe(CoranApprovalService::KEY_DONE)
        ->and($mapped['stageIndex'])->toBe(3)
        ->and($mapped['stages'])->toHaveCount(4)
        ->and($service->statusLabelFor($coran))->toBe('Completed');
});

test('coran approval service returns history rows for a document', function () {
    if (! Schema::connection('third')->hasTable('sysapproval')) {
        $this->markTestSkipped('sysapproval table is not available.');
    }

    $coran = Coran::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'status' => CoranApprovalService::STATUS_DONE,
    ]);

    $now = now();

    DB::connection('third')->table('sysapproval')->insert([
        [
            'doc_id' => $coran->row_id,
            'doc_no' => $coran->doc_no,
            'doc_name' => CoranApprovalService::DOC_NAME,
            'status' => CoranApprovalService::STATUS_SUBMITTED,
            'approve' => 'OK',
            'notes' => 'Pengajuan',
            'is_deleted' => 0,
            'created_date' => $now->copy()->subMinutes(2),
            'created_by' => 'Tester',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
        [
            'doc_id' => $coran->row_id,
            'doc_no' => $coran->doc_no,
            'doc_name' => CoranApprovalService::DOC_NAME,
            'status' => CoranApprovalService::STATUS_DONE,
            'approve' => 'OK',
            'notes' => '',
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => 'Tester',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ],
    ]);

    $history = app(CoranApprovalService::class)->history($coran);

    expect($history)->toHaveCount(2)
        ->and($history[0]['status'])->toBe(CoranApprovalService::STATUS_SUBMITTED)
        ->and($history[0]['createdBy'])->toBe('Tester')
        ->and($history[1]['status'])->toBe(CoranApprovalService::STATUS_DONE);

    DB::connection('third')
        ->table('sysapproval')
        ->where('doc_name', CoranApprovalService::DOC_NAME)
        ->where('doc_id', $coran->row_id)
        ->delete();
    $coran->delete();
});
