<?php

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\MsCraftsman;
use App\Models\Production;
use App\Support\CoranApprovalService;
use Illuminate\Support\Str;

test('coran report page defaults to the current month', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(9, 0));

    $this->get(route('coran.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/report')
            ->where('filters.craftsman', '')
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('craftsmanOptions')
            ->has('summary')
            ->has('materialBenchmark')
            ->where('monthlyShrink.year', 2026)
            ->has('monthlyShrink.months', 12)
            ->where('monthlyActivity.year', 2026)
            ->has('monthlyActivity.months', 12)
            ->has('byCraftsman')
            ->has('byStatus', 4)
            ->has('spkStatusCounts.pending')
            ->has('rows')
        );
});

test('coran report monthly activity aggregates ytd coran totals', function () {
    $this->travelTo(now()->setDate(2026, 4, 18)->setTime(9, 0));

    $craftsman = MsCraftsman::factory()->create();

    Coran::factory()->done()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '2026-01-12',
        'submit_material_rosegold' => '10.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '2.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'weight' => '7.00',
        'shrink' => '1.00',
    ]);

    Coran::factory()->done()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '2026-03-08',
        'submit_material_rosegold' => '4.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '1.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'weight' => '2.50',
        'shrink' => '0.50',
    ]);

    $this->get(route('coran.report', ['craftsman' => $craftsman->row_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/report')
            ->where('monthlyActivity.year', 2026)
            ->where('monthlyActivity.months.0.month', 1)
            ->where('monthlyActivity.months.0.documentCount', 1)
            ->where('monthlyActivity.months.0.submitMaterial', '10.00')
            ->where('monthlyActivity.months.0.spkWeight', '7.00')
            ->where('monthlyActivity.months.0.resultMaterial', '2.00')
            ->where('monthlyActivity.months.0.shrink', '1.00')
            ->where('monthlyActivity.months.2.month', 3)
            ->where('monthlyActivity.months.2.documentCount', 1)
            ->where('monthlyActivity.months.2.submitMaterial', '4.00')
            ->where('monthlyActivity.months.2.spkWeight', '2.50')
            ->where('monthlyActivity.months.2.resultMaterial', '1.00')
            ->where('monthlyActivity.months.2.shrink', '0.50')
            ->where('monthlyActivity.months.3.documentCount', 0)
        );
});

test('coran report corrects inflated shrink when header weight is empty but spk lines exist', function () {
    $craftsman = MsCraftsman::factory()->create();

    $misaligned = Coran::factory()->done()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '1888-06-01',
        'weight' => '0.000',
        'submit_material_rosegold' => '10.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '2.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'shrink' => '8.00',
    ]);

    CoranSpk::factory()->create([
        'row_id' => $misaligned->row_id,
        'spk_id' => Production::factory()->create()->row_id,
        'weight' => '7.50',
        'status_rosegold' => CoranSpk::STATUS_OK,
    ]);

    $this->get(route('coran.report', [
        'craftsman' => $craftsman->row_id,
        'date_from' => '1888-06-01',
        'date_to' => '1888-06-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.submitMaterial', '10.00')
            ->where('summary.spkWeight', '7.50')
            ->where('summary.resultMaterial', '2.00')
            ->where('summary.shrink', '0.50')
            ->where('summary.shrinkPercent', '5.00%')
        );
});

test('coran report summarizes ppic and done documents', function () {
    $craftsman = MsCraftsman::factory()->create();

    $production = Production::factory()->create([
        'spk_no' => '2026/PRD/CORRPT01',
    ]);

    $included = Coran::factory()->done()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '1888-05-10',
        'submit_material_rosegold' => '10.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '2.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'weight' => '7.50',
        'shrink' => '0.50',
    ]);

    CoranSpk::factory()->create([
        'row_id' => $included->row_id,
        'spk_id' => $production->row_id,
        'weight' => '7.50',
        'status_rosegold' => CoranSpk::STATUS_OK,
    ]);

    Coran::factory()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '1888-05-11',
        'status' => CoranApprovalService::STATUS_MANAGER,
        'submit_material_rosegold' => '5.00',
        'submit_material_whitegold' => '0.00',
        'submit_material_yellowgold' => '0.00',
        'result_material_rosegold' => '0.00',
        'result_material_whitegold' => '0.00',
        'result_material_yellowgold' => '0.00',
        'weight' => '4.50',
        'shrink' => '0.50',
    ]);

    Coran::factory()->create([
        'craftsman_id' => $craftsman->row_id,
        'doc_no' => 'COR'.Str::upper(Str::random(6)),
        'trans_date' => '1888-05-12',
        'status' => CoranApprovalService::STATUS_SUBMITTED,
        'shrink' => '9.99',
    ]);

    $this->get(route('coran.report', [
        'craftsman' => $craftsman->row_id,
        'date_from' => '1888-05-01',
        'date_to' => '1888-05-31',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coran/report')
            ->where('summary.documentCount', 2)
            ->where('summary.submitMaterial', '15.00')
            ->where('summary.spkWeight', '12.00')
            ->where('summary.resultMaterial', '2.00')
            ->where('summary.shrink', '1.00')
            ->where('summary.shrinkPercent', '6.67%')
            ->where('summary.spkCount', 1)
            ->where('materialBenchmark.documentSampleCount', 2)
            ->where('materialBenchmark.aggregate.hasilPercent', '80.00%')
            ->where('materialBenchmark.aggregate.sisaPercent', '13.33%')
            ->where('materialBenchmark.aggregate.shrinkPercent', '6.67%')
            ->where('materialBenchmark.perDocument.hasilPercent', '82.50%')
            ->where('materialBenchmark.perDocument.sisaPercent', '10.00%')
            ->where('materialBenchmark.perDocument.shrinkPercent', '7.50%')
            ->where('materialBenchmark.perDocument.allocationTotalPercent', '100.00%')
            ->where('materialBenchmark.aggregate.allocationTotalPercent', '100.00%')
            ->where('materialBenchmark.storedBalanceGapPercent', null)
            ->has('rows', 2)
        );
});
