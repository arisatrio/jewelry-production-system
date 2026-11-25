<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranSpkEligibility;
use App\Support\ResinSpkEligibility;
use Illuminate\Support\Str;

test('coran create page is accessible', function () {
    $this->get(route('coran.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/create')
            ->has('formDocumentNo')
            ->has('statusOptions')
            ->has('craftsmanOptions')
            ->has('materialOptions')
            ->has('form.transDate')
            ->has('form.details')
            ->has('form.materials')
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
                'weight' => '2.50',
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
        ->and((string) $coran->weight)->toBe('2.50');

    $detail = CoranSpk::query()
        ->notDeleted()
        ->where('row_id', $coran->row_id)
        ->where('spk_id', $production->row_id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and((string) $detail->weight)->toBe('2.50')
        ->and($detail->status)->toBe(CoranSpk::STATUS_OK);

    $production->refresh();

    expect((float) $production->last_weight)->toBe(2.5);

    CoranSpk::query()->where('row_id', $coran->row_id)->delete();
    $coran->delete();
    $production->delete();
});

test('coran store updates spk last process from resin to coran', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORPROC'.Str::upper(Str::random(3)),
        'last_process' => ResinSpkEligibility::PROCESS_KEY,
        'is_inprocess' => 1,
    ]);

    $this->post(route('coran.store'), [
        'trans_date' => now()->format('Y-m-d'),
        'details' => [
            [
                'spk_id' => $production->row_id,
                'weight' => '1.20',
                'status' => 'OK',
            ],
        ],
    ])->assertRedirect();

    $production->refresh();

    expect($production->last_process)->toBe(CoranSpkEligibility::PROCESS_KEY)
        ->and($production->is_inprocess)->toBe(1);

    CoranSpk::query()
        ->where('spk_id', $production->row_id)
        ->get()
        ->each(function (CoranSpk $detail): void {
            Coran::query()->where('row_id', $detail->row_id)->delete();
            $detail->delete();
        });
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
