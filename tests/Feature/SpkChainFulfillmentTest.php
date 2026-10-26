<?php

use App\Models\MsChain;
use App\Models\Production;
use App\Models\SpkChain;
use App\Models\TrxChain;
use App\Support\SpkChainFulfillment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['spkchains', 'spk_chains', 'trxchain', 'mschain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }

    if (
        ! Schema::connection('third')->hasColumn('spk_chains', 'fulfillment_status')
        && ! Schema::connection('third')->hasColumn('spkchains', 'fulfillment_status')
    ) {
        $this->markTestSkipped('fulfillment_status column is not available.');
    }
});

test('spk chain fulfillment advances through workshop workflow', function () {
    $production = Production::query()->notDeleted()->orderByDesc('row_id')->first();

    expect($production)->not->toBeNull();

    $master = MsChain::factory()->create([
        'item_code' => 'FUL-'.Str::upper(Str::random(6)),
    ]);

    $chain = SpkChain::factory()->create([
        'row_id' => $production->row_id,
        'mschain_id' => $master->row_id,
        'fulfillment_status' => SpkChainFulfillment::ALLOCATED,
    ]);

    $unit = TrxChain::factory()->create([
        'mschain_id' => $master->row_id,
        'spk_id' => $production->row_id,
        'entry_date' => now()->toDateString(),
        'out_date' => now()->toDateString(),
    ]);

    try {
        $this->post(route('spk.chains.fulfillment', $chain), [
            'action' => SpkChainFulfillment::ACTION_SEND_TO_WORKSHOP,
        ])->assertRedirect();

        expect($chain->refresh()->fulfillment_status)
            ->toBe(SpkChainFulfillment::SENT_TO_WORKSHOP);

        $this->post(route('spk.chains.fulfillment', $chain), [
            'action' => SpkChainFulfillment::ACTION_RECEIVE_WORKSHOP,
        ])->assertRedirect();

        expect($chain->refresh()->fulfillment_status)
            ->toBe(SpkChainFulfillment::RECEIVED_BY_WORKSHOP);

        $this->post(route('spk.chains.fulfillment', $chain), [
            'action' => SpkChainFulfillment::ACTION_MARK_INSTALLED,
        ])->assertRedirect();

        expect($chain->refresh()->fulfillment_status)
            ->toBe(SpkChainFulfillment::INSTALLED);
    } finally {
        $unit->delete();
        $chain->delete();
        $master->delete();
    }
});
