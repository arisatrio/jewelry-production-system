<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SerahTerimaChain;
use App\Models\SpkChain;
use App\Models\TrxChain;
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

        $receipt = SerahTerimaChain::query()->findOrFail($response->json('id'));

        expect($receipt->items[0])
            ->toMatchArray([
                'category' => 'Bracelet Chain',
                'item' => 'Cable Chain',
                'color' => 'White Gold',
            ]);

        $this->get(route('spk.print.chain-receipt', $receipt))
            ->assertOk()
            ->assertSee('TANDA TERIMA RANTAI', false)
            ->assertSee('Cable Chain', false)
            ->assertSee('Bracelet Chain', false)
            ->assertSee('White Gold', false);
    } finally {
        SerahTerimaChain::query()->where('spk_row_id', $production->row_id)->forceDelete();
        $unit->delete();
        $chain->delete();
        $production->delete();
        $master->delete();
    }
});
