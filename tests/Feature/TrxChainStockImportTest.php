<?php

use App\Models\MsChain;
use App\Models\TrxChain;
use App\Support\TrxChainStockImporter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (['mschain', 'trxchain'] as $table) {
        if (! Schema::connection('third')->hasTable($table)) {
            $this->markTestSkipped("Table {$table} is not available.");
        }
    }
});

test('trx chain stock importer creates unit rows from csv', function () {
    $master = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item_name' => 'Brac Classic 1.8gr - ZN264853 Series - 18 cm',
        'item_code' => 'ZN264853 Series',
        'length' => '18.00',
    ]);

    $path = sys_get_temp_dir().'/trxchain-import-'.Str::lower(Str::random(8)).'.csv';

    file_put_contents($path, implode("\n", [
        'Kategori Rantai,Nama Master Item,Kode Model,Panjang,Warna Emas,Tgl Masuk,Pcs,Berat (gr),Harga Beli,Status Availability,Tgl Terpakai,No PRD,PLU,Supplier',
        'Bracelet Chain,Brac Classic 1.8gr - ZN264853 Series - 18 cm,ZN264853-TEST,18 cm,Rose Gold,27/03/2026,1,"2,03","$215,00",USED,23/10/2026,,,Season City',
        'Bracelet Chain,Brac Classic 1.8gr - ZN264853 Series - 18 cm,ZN264853-READY,18 cm,White Gold,10/09/2026,1,"1,50","$100,00",READY,,,,Hongkong',
    ]));

    try {
        $result = app(TrxChainStockImporter::class)->importFromPath($path, 'tester');

        expect($result['created'])->toBe(2)
            ->and($result['missing_master'])->toBe(0);

        $used = TrxChain::query()->where('item_code', 'ZN264853-TEST')->first();
        $ready = TrxChain::query()->where('item_code', 'ZN264853-READY')->first();

        expect($used)->not->toBeNull()
            ->and($used->out_date?->format('Y-m-d'))->toBe('2026-10-23')
            ->and($used->purchase_price)->toBe('215.00')
            ->and($used->supplier)->toBe('Season City')
            ->and($ready)->not->toBeNull()
            ->and($ready->out_date)->toBeNull()
            ->and($ready->supplier)->toBe('Hongkong');

        $this->artisan('chain:import', [
            '--stock' => $path,
            '--actor' => 'tester',
            '--only' => 'stock',
        ])->assertSuccessful();

        TrxChain::query()
            ->where('item_code', 'like', 'ZN264853-TEST%')
            ->orWhere('item_code', 'like', 'ZN264853-READY%')
            ->delete();
    } finally {
        @unlink($path);
        $master->delete();
    }
});

test('trx chain stock importer sets entry date when ready row has no tgl masuk', function () {
    $token = 'ReadyNoDate '.Str::upper(Str::random(8));
    $modelCode = 'READY-NODATE-'.Str::upper(Str::random(6));
    $master = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item_name' => $token,
        'item_code' => 'RD-'.Str::upper(Str::random(6)),
        'length' => '18.00',
    ]);

    $path = sys_get_temp_dir().'/trxchain-ready-no-date-'.Str::lower(Str::random(8)).'.csv';

    file_put_contents($path, implode("\n", [
        'Kategori Rantai,Nama Master Item,Kode Model,Panjang,Warna Emas,Tgl Masuk,Pcs,Berat (gr),Harga Beli,Status Availability,Tgl Terpakai,No PRD,PLU',
        "Bracelet Chain,{$token},{$modelCode},18 cm,White Gold,,1,\"1,88\",,READY,,,",
    ]));

    try {
        $result = app(TrxChainStockImporter::class)->importFromPath($path, 'tester');

        expect($result['created'])->toBe(1);

        $ready = TrxChain::query()->where('item_code', $modelCode)->first();

        expect($ready)->not->toBeNull()
            ->and($ready->entry_date)->not->toBeNull()
            ->and($ready->out_date)->toBeNull();

        $ready->delete();
    } finally {
        @unlink($path);
        $master->delete();
    }
});

test('trx chain stock importer allows duplicate model codes on separate rows', function () {
    $master = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item_name' => 'Chain HK - K18SHM2P18 - 18 cm',
        'item_code' => 'K18SHM2P18',
        'length' => '18.00',
    ]);

    $path = sys_get_temp_dir().'/trxchain-dup-'.Str::lower(Str::random(8)).'.csv';

    file_put_contents($path, implode("\n", [
        'Kategori Rantai,Nama Master Item,Kode Model,Panjang,Warna Emas,Tgl Masuk,Pcs,Berat (gr),Harga Beli,Status Availability,Tgl Terpakai,No PRD,PLU',
        'Bracelet Chain,Chain HK - K18SHM2P18 - 18 cm,K18SHM2P18,18 cm,Rose Gold,27/03/2026,1,"2,37","$215,00",USED,18/08/2026,,',
        'Bracelet Chain,Chain HK - K18SHM2P18 - 18 cm,K18SHM2P18,18 cm,Rose Gold,28/03/2026,1,"2,36","$215,00",USED,27/07/2026,,',
    ]));

    try {
        $result = app(TrxChainStockImporter::class)->importFromPath($path, 'tester');

        expect($result['created'])->toBe(2)
            ->and($result['skipped'])->toBe(0);

        expect(
            TrxChain::query()
                ->where('item_code', 'K18SHM2P18')
                ->whereDate('out_date', '2026-08-18')
                ->exists(),
        )->toBeTrue()
            ->and(
                TrxChain::query()
                    ->where('item_code', 'K18SHM2P18-2')
                    ->whereDate('out_date', '2026-07-27')
                    ->exists(),
            )->toBeTrue();

        TrxChain::query()
            ->whereIn('item_code', ['K18SHM2P18', 'K18SHM2P18-2'])
            ->delete();
    } finally {
        @unlink($path);
        $master->delete();
    }
});

test('trx chain stock importer accepts csv without supplier column', function () {
    $master = MsChain::factory()->create([
        'chain_category' => MsChain::CATEGORY_BRACELET,
        'item_name' => 'Legacy CSV - ZN999 - 18 cm',
        'item_code' => 'ZN999',
        'length' => '18.00',
    ]);

    $path = sys_get_temp_dir().'/trxchain-legacy-'.Str::lower(Str::random(8)).'.csv';

    file_put_contents($path, implode("\n", [
        'Kategori Rantai,Nama Master Item,Kode Model,Panjang,Warna Emas,Tgl Masuk,Pcs,Berat (gr),Harga Beli,Status Availability,Tgl Terpakai,No PRD,PLU',
        'Bracelet Chain,Legacy CSV - ZN999 - 18 cm,ZN999-LEG,18 cm,White Gold,10/09/2026,1,"1,00",,READY,,,',
    ]));

    try {
        $result = app(TrxChainStockImporter::class)->importFromPath($path, 'tester');

        expect($result['created'])->toBe(1);

        $unit = TrxChain::query()->where('item_code', 'ZN999-LEG')->first();

        expect($unit)->not->toBeNull()
            ->and($unit->supplier)->toBeNull();

        $unit->delete();
    } finally {
        @unlink($path);
        $master->delete();
    }
});
