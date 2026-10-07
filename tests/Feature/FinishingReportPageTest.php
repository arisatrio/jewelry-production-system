<?php

use App\Models\FinishingHandmade;
use App\Models\SkuPrefixCategory;
use App\Support\SpkService;
use Illuminate\Support\Facades\DB;

test('finishing report page defaults to the current month', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(9, 0));

    $this->get(route('finishing.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finishing/report')
            ->where('filters.craftsman', '')
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('craftsmanOptions')
            ->has('summary')
            ->where('monthlyShrink.year', 2026)
            ->has('monthlyShrink.months', 12)
            ->where('monthlyShrink.months.0.month', 1)
            ->where('monthlyShrink.months.8.month', 9)
            ->where('monthlyShrink.months.8.includeInTrend', true)
            ->where('monthlyShrink.months.9.month', 10)
            ->where('monthlyShrink.months.9.shrink', '0.00')
            ->where('monthlyShrink.months.9.shrinkPercent', null)
            ->where('monthlyShrink.months.9.includeInTrend', false)
            ->where('monthlyShrink.months.11.month', 12)
            ->where('monthlyShrink.months.11.includeInTrend', false)
            ->has('byCraftsman')
            ->has('bySkuCategory')
            ->has('rows')
        );
});

test('finishing report page summarizes approved documents per craftsman', function () {
    $craftsmen = DB::connection('third')
        ->table('mscraftsman')
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('name')
        ->limit(2)
        ->get(['row_id', 'name'])
        ->all();

    if (count($craftsmen) < 2) {
        $this->markTestSkipped('Butuh minimal dua pengrajin di mscraftsman.');
    }

    [$firstCraftsman, $secondCraftsman] = $craftsmen;

    $skuCategory = SkuPrefixCategory::query()->active()->orderBy('id')->first();

    if ($skuCategory === null) {
        $this->markTestSkipped('Butuh minimal satu kategori prefix SKU aktif.');
    }

    $production = app(SpkService::class)->createStock('system');
    $production->update(['category_prefix_id' => $skuCategory->id]);

    $documents = collect([
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINRPT0001',
            'craftsman_id' => $secondCraftsman->row_id,
            'send_craftsman_date' => '1999-04-02 08:00:00',
            'received_craftsman_date' => '1999-04-02 10:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'finish_weight' => '9.00',
            'result_materialgold' => '0.50',
            'shrink' => '0.50',
            'process_name' => 'Handmade',
            'koreksi_qc' => 1,
            'spk_id' => $production->row_id,
        ]),
        FinishingHandmade::factory()->create([
            'doc_no' => 'FINRPT0002',
            'status' => FinishingHandmade::STATUS_TO_PPIC,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-03 08:00:00',
            'received_craftsman_date' => '1999-04-03 11:30:00',
            'start_weight' => '4.00',
            'submit_materialgold' => '1.00',
            'finish_weight' => '4.50',
            'result_materialgold' => '0.00',
            'shrink' => '0.50',
            'koreksi_qc' => 0,
        ]),
        FinishingHandmade::factory()->create([
            'doc_no' => 'FINRPT0003',
            'status' => FinishingHandmade::STATUS_OPEN,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-04 08:00:00',
        ]),
    ]);

    try {
        $this->get(route('finishing.report', [
            'date_from' => '1999-04-01',
            'date_to' => '1999-04-30',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('finishing/report')
                ->where('summary.documentCount', 2)
                ->where('summary.craftsmanCount', 2)
                ->where('summary.startWeight', '14.00')
                ->where('summary.submitMaterial', '1.00')
                ->where('summary.shrink', '1.00')
                ->where('summary.shrinkPercent', '6.67%')
                ->where('summary.spkCount', 1)
                ->where('summary.averageShrinkPerProcess', '0.50')
                ->where('summary.averageShrinkPercentPerProcess', '7.50%')
                ->where('summary.averageShrinkPerSpk', '0.50')
                ->where('summary.averageShrinkPercentPerSpk', '5.00%')
                ->where('summary.qcNotOkCount', 1)
                ->has('byCraftsman', 2)
                ->where('byCraftsman.0.craftsmanName', (string) $firstCraftsman->name)
                ->where('byCraftsman.0.documentCount', 1)
                ->where('byCraftsman.0.shrinkPercent', '10.00%')
                ->where('byCraftsman.1.craftsmanName', (string) $secondCraftsman->name)
                ->where('byCraftsman.1.qcNotOkCount', 1)
                ->where('byCraftsman.1.workMinutes', 120)
                ->where('byCraftsman.1.workMinutesCount', 1)
                ->where('byCraftsman.0.workMinutes', 210)
                ->where('byCraftsman.0.workMinutesCount', 1)
                ->where('summary.workMinutes', 330)
                ->has('bySkuCategory', 2)
                ->where('bySkuCategory.0.skuCategory', $skuCategory->displayName())
                ->where('bySkuCategory.0.shrinkPercent', '5.00%')
                ->where('bySkuCategory.1.skuCategory', 'Tanpa kategori SKU')
                ->where('bySkuCategory.1.shrink', '0.50')
                ->where('bySkuCategory.1.shrinkPercent', '10.00%')
                ->has('rows', 2)
                ->where('rows.0.docNo', 'FINRPT0002')
                ->where('rows.1.docNo', 'FINRPT0001')
                ->where('rows.1.id', $documents[0]->getKey())
                ->where('rows.1.skuCategory', $skuCategory->displayName())
                ->where('rows.0.skuCategory', null)
                ->where('rows.1.processName', 'Handmade')
                ->where('rows.0.processName', 'Finishing')
                ->where('rows.1.sendCraftsmanDate', '1999-04-02 08:00')
                ->where('rows.1.shrinkPercent', '5.00%')
                ->where('rows.1.qcStatus', 'NOT OK')
                ->where('rows.1.workDuration', '2 jam')
            );

        $this->get(route('finishing.report', [
            'craftsman' => $secondCraftsman->row_id,
            'date_from' => '1999-04-01',
            'date_to' => '1999-04-30',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.craftsman', (string) $secondCraftsman->row_id)
                ->has('rows', 1)
                ->where('rows.0.docNo', 'FINRPT0001')
            );
    } finally {
        $documents->each->delete();
        $production->delete();
    }
});

test('finishing report page charts year to date shrink by month', function () {
    $this->travelTo(now()->setDate(2099, 3, 15)->setTime(9, 0));

    if (FinishingHandmade::query()->whereYear('send_craftsman_date', 2099)->exists()) {
        $this->markTestSkipped('Ada data finishing di tahun 2099.');
    }

    $craftsmen = DB::connection('third')
        ->table('mscraftsman')
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('name')
        ->limit(2)
        ->get(['row_id', 'name'])
        ->all();

    if (count($craftsmen) < 2) {
        $this->markTestSkipped('Butuh minimal dua pengrajin di mscraftsman.');
    }

    [$firstCraftsman, $secondCraftsman] = $craftsmen;

    $documents = collect([
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINYTD0001',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-01-08 08:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'shrink' => '1.20',
        ]),
        FinishingHandmade::factory()->create([
            'doc_no' => 'FINYTD0002',
            'status' => FinishingHandmade::STATUS_TO_PPIC,
            'craftsman_id' => $secondCraftsman->row_id,
            'send_craftsman_date' => '2099-01-20 08:00:00',
            'start_weight' => '5.00',
            'submit_materialgold' => '0.00',
            'shrink' => '0.30',
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINYTD0003',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-02-02 08:00:00',
            'start_weight' => '5.00',
            'submit_materialgold' => '0.00',
            'shrink' => '-0.25',
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINYTD0004',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-03-15 08:00:00',
            'start_weight' => '8.00',
            'submit_materialgold' => '2.00',
            'shrink' => '0.40',
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINYTD0005',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-03-20 08:00:00',
            'shrink' => '9.00',
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINYTD0006',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2098-12-31 08:00:00',
            'shrink' => '5.00',
        ]),
        FinishingHandmade::factory()->create([
            'doc_no' => 'FINYTD0007',
            'status' => FinishingHandmade::STATUS_OPEN,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-01-09 08:00:00',
            'shrink' => '8.00',
        ]),
        FinishingHandmade::factory()->done()->deleted()->create([
            'doc_no' => 'FINYTD0008',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '2099-02-11 08:00:00',
            'shrink' => '7.00',
        ]),
    ]);

    try {
        $this->get(route('finishing.report', [
            'date_from' => '1999-01-01',
            'date_to' => '1999-01-31',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.date_from', '1999-01-01')
                ->where('monthlyShrink.year', 2099)
                ->has('monthlyShrink.months', 12)
                ->where('monthlyShrink.months.0.month', 1)
                ->where('monthlyShrink.months.0.shrink', '1.50')
                ->where('monthlyShrink.months.0.shrinkPercent', '10.00%')
                ->where('monthlyShrink.months.0.processCount', 2)
                ->where('monthlyShrink.months.0.includeInTrend', true)
                ->where('monthlyShrink.months.1.month', 2)
                ->where('monthlyShrink.months.1.shrink', '-0.25')
                ->where('monthlyShrink.months.1.shrinkPercent', '+5.00%')
                ->where('monthlyShrink.months.1.processCount', 1)
                ->where('monthlyShrink.months.2.month', 3)
                ->where('monthlyShrink.months.2.shrink', '0.40')
                ->where('monthlyShrink.months.2.shrinkPercent', '4.00%')
                ->where('monthlyShrink.months.2.processCount', 1)
                ->where('monthlyShrink.months.2.includeInTrend', true)
                ->where('monthlyShrink.months.3.month', 4)
                ->where('monthlyShrink.months.3.shrink', '0.00')
                ->where('monthlyShrink.months.3.shrinkPercent', null)
                ->where('monthlyShrink.months.3.processCount', 0)
                ->where('monthlyShrink.months.3.includeInTrend', false)
                ->where('monthlyShrink.months.11.month', 12)
            );

        $this->get(route('finishing.report', [
            'craftsman' => $firstCraftsman->row_id,
            'date_from' => '1999-01-01',
            'date_to' => '1999-01-31',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('monthlyShrink.months.0.shrink', '1.20')
                ->where('monthlyShrink.months.0.shrinkPercent', '12.00%')
                ->where('monthlyShrink.months.0.processCount', 1)
                ->where('monthlyShrink.months.1.shrink', '-0.25')
                ->where('monthlyShrink.months.1.shrinkPercent', '+5.00%')
                ->where('monthlyShrink.months.2.shrink', '0.40')
                ->where('monthlyShrink.months.2.shrinkPercent', '4.00%')
                ->where('monthlyShrink.months.11.shrink', '0.00')
                ->where('monthlyShrink.months.11.includeInTrend', false)
            );
    } finally {
        $documents->each->delete();
    }
});

test('finishing report page averages shrink per process and per spk', function () {
    $craftsman = DB::connection('third')
        ->table('mscraftsman')
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->orderBy('name')
        ->first(['row_id']);

    if ($craftsman === null) {
        $this->markTestSkipped('Butuh minimal satu pengrajin di mscraftsman.');
    }

    $spkService = app(SpkService::class);
    $firstProduction = $spkService->createStock('system');
    $secondProduction = $spkService->createStock('system');

    $documents = collect([
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINAVG0001',
            'craftsman_id' => $craftsman->row_id,
            'send_craftsman_date' => '1998-07-02 08:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'finish_weight' => '9.60',
            'result_materialgold' => '0.00',
            'shrink' => '0.40',
            'spk_id' => $firstProduction->row_id,
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINAVG0002',
            'craftsman_id' => $craftsman->row_id,
            'send_craftsman_date' => '1998-07-03 08:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'finish_weight' => '9.40',
            'result_materialgold' => '0.00',
            'shrink' => '0.60',
            'spk_id' => $firstProduction->row_id,
        ]),
        FinishingHandmade::factory()->done()->create([
            'doc_no' => 'FINAVG0003',
            'craftsman_id' => $craftsman->row_id,
            'send_craftsman_date' => '1998-07-04 08:00:00',
            'start_weight' => '10.00',
            'submit_materialgold' => '0.00',
            'finish_weight' => '9.80',
            'result_materialgold' => '0.00',
            'shrink' => '0.20',
            'spk_id' => $secondProduction->row_id,
        ]),
    ]);

    try {
        $this->get(route('finishing.report', [
            'date_from' => '1998-07-01',
            'date_to' => '1998-07-31',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.documentCount', 3)
                ->where('summary.spkCount', 2)
                ->where('summary.shrink', '1.20')
                ->where('summary.averageShrinkPerProcess', '0.40')
                ->where('summary.averageShrinkPercentPerProcess', '4.00%')
                ->where('summary.averageShrinkPerSpk', '0.60')
                ->where('summary.averageShrinkPercentPerSpk', '3.50%')
            );
    } finally {
        $documents->each->delete();
        $firstProduction->delete();
        $secondProduction->delete();
    }
});
