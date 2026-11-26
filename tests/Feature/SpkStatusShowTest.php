<?php

use App\Models\Production;
use App\Support\SpkStatusMapper;

test('spk show page includes mapped workflow status', function () {
    $production = Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $expected = (new SpkStatusMapper)->map($production);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/show')
            ->has('production.workflowStatus')
            ->where('production.workflowStatus.key', $expected['key'])
            ->where('production.workflowStatus.label', $expected['label'])
            ->where('production.workflowStatus.stageIndex', $expected['stageIndex'])
            ->where('production.workflowStatus.isOverdue', $expected['isOverdue'])
            ->has('production.workflowStatus.stages')
            ->where('production.workflowStatus.stages.0.key', 'draft')
            ->where('production.workflowStatus.stages.1.key', 'confirmed')
            ->where('production.workflowStatus.stages.2.key', 'inProgress')
            ->where('production.workflowStatus.stages.3.key', 'done')
            ->has('production.refSpkNo')
            ->has('detailUrl')
            ->has('approval')
            ->has('approvalTimeline')
            ->has('approvalFooter', 3)
        );
});

test('spk show exposes reference spk number when set', function () {
    $reference = Production::factory()->create([
        'spk_type' => 'Stock',
        'status' => 'SPKDONE',
        'is_deleted' => 0,
    ]);

    $child = Production::factory()->create([
        'spk_type' => 'Reparasi',
        'ref_spk_id' => $reference->row_id,
        'is_deleted' => 0,
    ]);

    try {
        $this->get(route('spk.show', $child))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('production.refSpkNo', (string) $reference->spk_no)
            );
    } finally {
        $child->delete();
        $reference->delete();
    }
});
