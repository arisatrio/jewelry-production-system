<?php

use App\Models\MsComponent;
use App\Models\Production;
use App\Models\SerahTerimaComponent;
use App\Models\SpkComponent;
use App\Models\TrxComponent;
use App\Support\SpkComponentFulfillment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $schema = Schema::connection('third');

    foreach (['serah_terima_component', 'trxcomponent', 'mscomponent'] as $table) {
        if (! $schema->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    if (! $schema->hasTable('spkcomponents')) {
        $this->markTestSkipped('SPK components table is not available.');
    }
});

test('component receipt store and print include component item details', function () {
    $master = MsComponent::factory()->create([
        'item_code' => 'RCT-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/COMP/RCPT/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $component = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $master->row_id,
        'category' => 'Komponen',
        'item' => 'Stopper Chain',
        'color' => 'White Gold',
        'fulfillment_status' => SpkComponentFulfillment::ALLOCATED,
    ]);

    $unit = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
        'color' => 'Rose Gold',
        'component_size' => 12.5,
        'weight' => 1.75,
    ]);

    try {
        $response = $this->postJson(route('spk.print.component-receipt.store'), [
            'spk_row_id' => $production->row_id,
            'component_line_ids' => [(int) $component->line_id],
            'tanggal' => '2026-10-28',
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
        ]);

        $response->assertCreated();

        if (Schema::connection('third')->hasColumn('spkcomponents', 'fulfillment_status')) {
            expect($component->refresh()->fulfillment_status)
                ->toBe(SpkComponentFulfillment::SENT_TO_WORKSHOP);
        }

        $receipt = SerahTerimaComponent::query()->findOrFail($response->json('id'));

        expect($receipt->items[0])
            ->toMatchArray([
                'category' => 'Komponen',
                'item' => 'Stopper Chain',
                'color' => 'Rose Gold',
                'componentSize' => '12.5',
            ])
            ->and($receipt->items[0])
            ->not->toHaveKey('allocatedRow');

        $this->get(route('spk.print.component-receipt', $receipt))
            ->assertOk()
            ->assertSee('TANDA TERIMA KOMPONEN', false)
            ->assertSee('Stopper Chain', false)
            ->assertSee('Rose Gold', false)
            ->assertDontSee('White Gold', false)
            ->assertSee('scan ketika menerima barang', false)
            ->assertSee('component_receipt='.$receipt->id, false);
    } finally {
        SerahTerimaComponent::query()->where('spk_row_id', $production->row_id)->forceDelete();
        $unit->delete();
        $component->delete();
        $production->delete();
        $master->delete();
    }
});

test('component receipt store without line ids still marks components sent to workshop', function () {
    $master = MsComponent::factory()->create([
        'item_code' => 'RCT-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/COMP/NOIDS/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $component = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $master->row_id,
        'fulfillment_status' => SpkComponentFulfillment::ALLOCATED,
    ]);

    $unit = TrxComponent::factory()->create([
        'mscomponent_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
    ]);

    try {
        $response = $this->postJson(route('spk.print.component-receipt.store'), [
            'spk_row_id' => $production->row_id,
            'tanggal' => '2026-10-28',
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
        ]);

        $response->assertCreated();

        if (Schema::connection('third')->hasColumn('spkcomponents', 'fulfillment_status')) {
            expect($component->refresh()->fulfillment_status)
                ->toBe(SpkComponentFulfillment::SENT_TO_WORKSHOP);
        }
    } finally {
        SerahTerimaComponent::query()->where('spk_row_id', $production->row_id)->forceDelete();
        $unit->delete();
        $component->delete();
        $production->delete();
        $master->delete();
    }
});

test('component receipt print fills diterima oleh from workshop receive audit', function () {
    if (! Schema::connection('third')->hasColumn('spkcomponents', 'workshop_received_at')) {
        $this->markTestSkipped('workshop_received audit columns are not available.');
    }

    $master = MsComponent::factory()->create([
        'item_code' => 'RCV-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/COMP/RCV/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $component = SpkComponent::factory()->create([
        'row_id' => $production->row_id,
        'mscomponent_id' => $master->row_id,
        'fulfillment_status' => SpkComponentFulfillment::RECEIVED_BY_WORKSHOP,
        'workshop_received_at' => '2026-10-27 14:30:00',
        'workshop_received_by' => 'Workshop Receiver',
    ]);

    $receipt = SerahTerimaComponent::factory()->create([
        'spk_row_id' => $production->row_id,
        'tanggal' => '2026-10-28',
        'diserahkan_oleh' => 'Sender',
        'diterima_oleh' => null,
        'spk_component_line_ids' => [(int) $component->line_id],
        'items' => [[
            'spkComponentLineId' => (int) $component->line_id,
            'spkNo' => $production->spk_no,
            'category' => 'Komponen',
            'item' => 'Clip',
            'color' => 'RG',
            'unitNo' => 'KOM0001',
            'componentSize' => '10',
            'weight' => '1',
            'customer' => 'Cust',
        ]],
        'jumlah_baris' => 1,
    ]);

    try {
        $this->get(route('spk.print.component-receipt', $receipt))
            ->assertOk()
            ->assertSee('Workshop Receiver', false)
            ->assertSee('27-Oct-2026', false);
    } finally {
        $receipt->forceDelete();
        $component->delete();
        $production->delete();
        $master->delete();
    }
});
