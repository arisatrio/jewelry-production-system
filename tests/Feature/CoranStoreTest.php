<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Support\Str;

test('coran create page is accessible', function () {
    $this->get(route('coran.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/create')
            ->has('formDocumentNo')
            ->has('statusOptions')
            ->has('craftsmanOptions')
            ->has('form.transDate')
            ->has('form.details')
        );
});

test('coran store creates document with spk details', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORSTORE'.Str::upper(Str::random(3)),
    ]);

    $response = $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'craftsman_id' => null,
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '2.500',
                'status' => 'OK',
            ],
        ],
    ]);

    $coran = Coran::query()
        ->notDeleted()
        ->whereHas('details', fn ($query) => $query
            ->notDeleted()
            ->where('spk_id', $production->row_id))
        ->orderByDesc('row_id')
        ->first();

    expect($coran)->not->toBeNull();

    $response->assertRedirect(route('coran.show', $coran));

    expect($coran->doc_no)->toMatch('/^COR\d{7}$/')
        ->and($coran->status)->toBeNull()
        ->and((string) $coran->weight)->toBe('2.500');

    $detail = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->where('spk_id', $production->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->weight)->toBe('2.500')
        ->and($detail->status)->toBe(CoranSpk::STATUS_OK);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran store requires at least one spk', function () {
    $this->from(route('coran.create'))
        ->post(route('coran.store'), [
            'trans_date' => now()->format('Y-m-d'),
            'details' => [],
        ])
        ->assertRedirect(route('coran.create'))
        ->assertSessionHasErrors('details');
});

test('coran search spks returns json', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORSEL'.Str::upper(Str::random(3)),
    ]);

    $this->getJson(route('coran.select.spks', [
        'search' => $production->spk_no,
        'limit' => 10,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'rowId' => $production->row_id,
            'spkNo' => $production->spk_no,
        ]);

    $production->delete();
});
