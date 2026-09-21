<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\CoranApprovalService;
use Illuminate\Support\Str;

test('finishing index includes spk status counts', function () {
    $this->get(route('finishing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/index')
            ->has('spkStatusCounts.pending')
            ->has('spkStatusCounts.inProgress')
            ->has('spkStatusCounts.completed')
        );
});

test('finishing select spks queue returns pending completed coran without finishing', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/FQ'.Str::upper(Str::random(4)),
    ]);
    $coran = Coran::factory()->create([
        'status' => CoranApprovalService::STATUS_DONE,
    ]);
    CoranSpk::factory()->create([
        'row_id' => $coran->row_id,
        'spk_id' => $spk->row_id,
    ]);

    $this->get(route('finishing.select.spks', [
        'queue' => 'pending',
        'limit' => 50,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => (int) $spk->row_id,
            'spkNo' => $spk->spk_no,
        ]);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $spk->delete();
});

test('finishing select spks queue returns in progress finishing documents', function () {
    $spk = Production::factory()->create([
        'spk_no' => '2026/PRD/FQI'.Str::upper(Str::random(4)),
    ]);
    $document = FinishingHandmade::factory()->create([
        'doc_no' => 'FIN'.Str::upper(Str::random(7)),
        'spk_id' => $spk->row_id,
        'status' => FinishingHandmade::STATUS_OPEN,
    ]);

    $this->get(route('finishing.select.spks', [
        'queue' => 'inProgress',
        'limit' => 50,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => (int) $spk->row_id,
            'spkNo' => $spk->spk_no,
            'finishingId' => (int) $document->row_id,
            'docNo' => $document->doc_no,
        ]);

    $document->delete();
    $spk->delete();
});
