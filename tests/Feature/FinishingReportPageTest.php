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
