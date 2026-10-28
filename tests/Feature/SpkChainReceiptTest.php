<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SerahTerimaChain;
use App\Models\SpkChain;
use App\Models\TrxChain;
use App\Support\SpkChainFulfillment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $schema = Schema::connection('third');

    foreach (['serah_terima_chain', 'trxchain', 'mschain'] as $table) {
        if (! $schema->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    if (! $schema->hasTable('spkchains') && ! $schema->hasTable('spk_chains')) {
        $this->markTestSkipped('SPK chains table is not available.');
    }
});

test('chain receipt store and print include chain item details', function () {
    $master = MsChain::factory()->create([
        'item_code' => 'RCT-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/RCPT/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'category' => 'Bracelet Chain',
        'item' => 'Cable Chain',
        'color' => 'White Gold',
        'size' => '45',
        'fulfillment_status' => SpkChainFulfillment::ALLOCATED,
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
        'color' => 'Rose Gold',
        'chain_length' => 44.5,
        'weight' => 10.25,
    ]);

    try {
        $response = $this->postJson(route('spk.print.chain-receipt.store'), [
            'spk_row_id' => $production->row_id,
            'chain_line_ids' => [(int) $chain->line_id],
            'tanggal' => '2026-10-26',
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
        ]);

        $response->assertCreated();

        if (
            Schema::connection('third')->hasColumn('spkchains', 'fulfillment_status')
            || Schema::connection('third')->hasColumn('spk_chains', 'fulfillment_status')
        ) {
            expect($chain->refresh()->fulfillment_status)
                ->toBe(SpkChainFulfillment::SENT_TO_WORKSHOP);
        }

        $receipt = SerahTerimaChain::query()->findOrFail($response->json('id'));

        expect($receipt->items[0])
            ->toMatchArray([
                'category' => 'Bracelet Chain',
                'item' => 'Cable Chain',
                'color' => 'Rose Gold',
                'size' => '45 cm',
                'chainLength' => '45',
            ])
            ->and($receipt->items[0])
            ->not->toHaveKey('allocatedRow');

        $this->get(route('spk.print.chain-receipt', $receipt))
            ->assertOk()
            ->assertSee('TANDA TERIMA RANTAI', false)
            ->assertDontSee('Diketahui oleh', false)
            ->assertSee('Cable Chain', false)
            ->assertSee('Bracelet Chain', false)
            ->assertSee('Rose Gold', false)
            ->assertDontSee('White Gold', false)
            ->assertDontSee('Rantai dialokasikan', false)
            ->assertSee('45 cm', false)
            ->assertDontSee('44.5', false)
            ->assertDontSee('44,5', false)
            ->assertSee('scan ketika menerima barang', false)
            ->assertSee('qrcode-generator.js', false)
            ->assertSee('chain_receipt='.$receipt->id, false);
    } finally {
        SerahTerimaChain::query()->where('spk_row_id', $production->row_id)->forceDelete();
        $unit->delete();
        $chain->delete();
        $production->delete();
        $master->delete();
    }
});

test('chain receipt print fills diterima oleh from workshop receive audit', function () {
    if (
        ! Schema::connection('third')->hasColumn('spkchains', 'workshop_received_at')
        && ! Schema::connection('third')->hasColumn('spk_chains', 'workshop_received_at')
    ) {
        $this->markTestSkipped('workshop_received audit columns are not available.');
    }

    $master = MsChain::factory()->create([
        'item_code' => 'RCV-'.Str::upper(Str::random(6)),
    ]);

    $production = Production::factory()->create([
        'spk_no' => 'TEST/CHAIN/RCV/'.Str::upper(Str::random(4)),
        'is_deleted' => 0,
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'fulfillment_status' => SpkChainFulfillment::RECEIVED_BY_WORKSHOP,
        'workshop_received_at' => '2026-10-27 14:30:00',
        'workshop_received_by' => 'Workshop Receiver',
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
    ]);

    $receipt = SerahTerimaChain::factory()->create([
        'spk_row_id' => $production->row_id,
        'tanggal' => '2026-10-26',
        'diserahkan_oleh' => 'Sender',
        'diterima_oleh' => null,
        'spk_chain_line_ids' => [(int) $chain->line_id],
        'items' => [[
            'spkChainLineId' => (int) $chain->line_id,
            'spkNo' => $production->spk_no,
            'category' => 'Bracelet',
            'item' => 'Chain',
            'color' => 'RG',
            'size' => '45 cm',
            'unitNo' => 'U1',
            'chainLength' => '45',
            'weight' => '1',
            'customer' => 'Cust',
        ]],
        'jumlah_baris' => 1,
    ]);

    try {
        $this->get(route('spk.print.chain-receipt', $receipt))
            ->assertOk()
            ->assertSee('Workshop Receiver', false)
            ->assertSee('27-Oct-2026', false);
    } finally {
        $receipt->forceDelete();
        $unit->delete();
        $chain->delete();
        $production->delete();
        $master->delete();
    }
});
