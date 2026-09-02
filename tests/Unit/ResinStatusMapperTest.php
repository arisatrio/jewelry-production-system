<?php

use App\Models\Resin;
use App\Support\ResinApprovalService;
use App\Support\ResinStatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('resin status mapper maps draft request to draft stage', function () {
    $resin = Resin::factory()->create(['status' => 'DRAFT']);

    $mapped = app(ResinStatusMapper::class)->map($resin);

    expect($mapped['key'])->toBe('draft')
        ->and($mapped['stageIndex'])->toBe(0)
        ->and($mapped['stages'])->toHaveCount(4)
        ->and($mapped['stages'][1]['key'])->toBe('submitted')
        ->and($mapped['stages'][2]['label'])->toBe('Serahkan ke Resin');
});

test('resin status mapper maps submitted request to pengajuan approval stage', function () {
    $resin = Resin::factory()->create([
        'status' => ResinApprovalService::STATUS_SUBMITTED,
    ]);

    $mapped = app(ResinStatusMapper::class)->map($resin);

    expect($mapped['key'])->toBe('submitted')
        ->and($mapped['stageIndex'])->toBe(1)
        ->and($mapped['label'])->toBe('Pengajuan Approval');
});

test('resin status mapper maps completed request to done stage', function () {
    $resin = Resin::factory()->done()->create();

    $mapped = app(ResinStatusMapper::class)->map($resin);

    expect($mapped['key'])->toBe('done')
        ->and($mapped['stageIndex'])->toBe(3)
        ->and($mapped['label'])->toBe('Done');
});
