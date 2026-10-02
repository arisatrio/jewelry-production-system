<?php

use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Support\PolishFinishedGoodApprovalService;
use Illuminate\Support\Str;

test('spk show exposes poles chrome complete action when process is pending complete', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SPKPFG'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Chrome',
    ]);

    $document = PolishFinishedGood::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => PolishFinishedGoodApprovalService::STATUS_MANAGER,
    ]);

    try {
        $this->get(route('spk.show', $production))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('spk/show')
                ->where('polesChromeComplete.documentId', $document->row_id)
                ->where('polesChromeComplete.docNo', $document->doc_no)
                ->has('polesChromeComplete.completeUrl')
            );
    } finally {
        $document->delete();
        $production->delete();
    }
});

test('spk show hides poles chrome complete action when document is done', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SPKPFGD'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Chrome',
    ]);

    $document = PolishFinishedGood::factory()->done()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
    ]);

    try {
        $this->get(route('spk.show', $production))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('polesChromeComplete', null)
            );
    } finally {
        $document->delete();
        $production->delete();
    }
});

test('poles chrome complete from spk detail redirects back to spk show', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SPKPFGC'.Str::upper(Str::random(3)),
        'last_process' => 'Poles Chrome',
    ]);

    $document = PolishFinishedGood::factory()->create([
        'doc_no' => 'COR'.Str::upper(Str::random(7)),
        'spk_id' => $production->row_id,
        'status' => PolishFinishedGoodApprovalService::STATUS_MANAGER,
    ]);

    try {
        $this->from(route('spk.show', $production))
            ->post(route('poles-chrome.complete', $document), [
                'return_to' => 'spk',
            ])
            ->assertRedirect(route('spk.show', $production));

        $document->refresh();

        expect($document->status)->toBe(PolishFinishedGoodApprovalService::STATUS_DONE);
    } finally {
        $document->delete();
        $production->delete();
    }
});
