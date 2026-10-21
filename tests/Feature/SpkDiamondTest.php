<?php

use App\Models\Production;
use App\Models\SpkDiamond;
use App\Support\StoneLedger;
use Illuminate\Support\Str;
use Mockery\MockInterface;

test('spk diamond show resolves by document number', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $document = SpkDiamond::query()->create([
        'no_spk_diamond' => 'MK-DM.RTE'.Str::upper(Str::random(3)),
        'spk_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'tanggal' => now()->toDateString(),
        'status' => SpkDiamond::STATUS_DRAFT,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $this->get(route('spk-diamond.show', $document->no_spk_diamond))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk-diamond/show')
            ->where('spkDiamondItem.noSpkDiamond', $document->no_spk_diamond));

    $this->get(route('spk-diamond.show', $document->id))
        ->assertOk();

    $document->delete();
    $production->delete();
});

test('spk diamond show resolves numeric document number by no_spk_diamond not id', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $document = SpkDiamond::query()->create([
        'no_spk_diamond' => '12345',
        'spk_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'tanggal' => now()->toDateString(),
        'status' => SpkDiamond::STATUS_DRAFT,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    expect($document->id)->not->toBe(12345);

    $this->get(route('spk-diamond.show', '12345'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk-diamond/show')
            ->where('spkDiamondItem.noSpkDiamond', '12345'));

    $document->delete();
    $production->delete();
});

test('spk diamond index is accessible', function () {
    $this->get(route('spk-diamond.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('spk-diamond/index'));
});

test('spk diamond can be stored as draft', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $response = $this->post(route('spk-diamond.store'), [
        'no_spk_diamond' => 'MK-DM.TEST'.Str::upper(Str::random(3)),
        'spk_id' => $production->row_id,
        'tanggal' => now()->toDateString(),
        'keterangan' => 'Tanpa Stopper',
        'weight_frame' => '13.29',
        'weight_stone' => '1.144',
        'setting_stones' => [],
        'diamonds' => [],
    ]);

    $document = SpkDiamond::query()
        ->where('spk_id', $production->row_id)
        ->orderByDesc('id')
        ->first();

    expect($document)->not->toBeNull();

    $response->assertRedirect(route('spk-diamond.show', $document));

    expect($document->status)->toBe(SpkDiamond::STATUS_DRAFT)
        ->and($document->keterangan)->toBe('Tanpa Stopper')
        ->and((string) $document->weight_total)->toBe('14.434');

    $document->delete();
    $production->delete();
});

test('spk diamond store requires weight frame and stone', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $this->post(route('spk-diamond.store'), [
        'no_spk_diamond' => 'MK-DM.WGT'.Str::upper(Str::random(3)),
        'spk_id' => $production->row_id,
        'tanggal' => now()->toDateString(),
        'setting_stones' => [],
        'diamonds' => [],
    ])
        ->assertSessionHasErrors(['weight_frame', 'weight_stone']);

    $production->delete();
});

test('spk diamond spk stones endpoint returns stone list shape', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $this->getJson(route('spk-diamond.select.spk-stones', ['spk_id' => $production->row_id]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data']);

    $production->delete();
});

test('spk diamond store shows error toast when stone stock is insufficient', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $this->mock(StoneLedger::class, function (MockInterface $mock): void {
        $mock->shouldReceive('activePeriod')
            ->once()
            ->andReturn(['id' => 1, 'label' => '2026-01']);
        $mock->shouldReceive('balancePcsByStone')
            ->once()
            ->with(1, [42])
            ->andReturn([42 => 2.0]);
    });

    $response = $this->from(route('spk-diamond.create'))
        ->post(route('spk-diamond.store'), [
            'no_spk_diamond' => 'MK-DM.STK'.Str::upper(Str::random(3)),
            'spk_id' => $production->row_id,
            'tanggal' => now()->toDateString(),
            'weight_frame' => '10',
            'weight_stone' => '1',
            'setting_stones' => [
                [
                    'stone_id' => 42,
                    'pcs' => '5',
                    'crt' => '0.5',
                    'notes' => null,
                ],
            ],
            'diamonds' => [],
        ]);

    $response
        ->assertRedirect(route('spk-diamond.create'))
        ->assertSessionHasErrors('setting_stones')
        ->assertSessionHas('inertia.flash_data.toast', [
            'type' => 'error',
            'message' => 'Stok batu tidak mencukupi.',
        ]);

    expect(SpkDiamond::query()->where('spk_id', $production->row_id)->exists())->toBeFalse();

    $production->delete();
});

test('pasang batu spk diamond search includes submitted documents with legacy status typo', function () {
    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/SD'.Str::upper(Str::random(4)),
    ]);

    $document = SpkDiamond::query()->create([
        'no_spk_diamond' => 'MK-DM.LEG'.Str::upper(Str::random(3)),
        'spk_id' => $production->row_id,
        'spk_no' => $production->spk_no,
        'tanggal' => now()->toDateString(),
        'status' => SpkDiamond::LEGACY_STATUS_SUBMITTED_TYPO,
        'is_deleted' => 0,
        'created_date' => now(),
        'created_by' => 'test',
        'modified_date' => now(),
        'modified_by' => 'test',
    ]);

    $this->getJson(route('pasang-batu.select.spk-diamonds', [
        'search' => $document->no_spk_diamond,
    ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.noSpkDiamond', $document->no_spk_diamond);

    $document->delete();
    $production->delete();
});
