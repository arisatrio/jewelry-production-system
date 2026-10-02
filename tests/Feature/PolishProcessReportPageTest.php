<?php

use App\Models\PolishFinishedGood;
use App\Models\PolishFrame;
use Illuminate\Support\Facades\DB;

test('poles rangka report page defaults to the current month', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(9, 0));

    $this->get(route('poles-rangka.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-rangka/report')
            ->where('filters.craftsman', '')
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('summary')
            ->has('monthlyShrink.months', 12)
            ->has('rows')
        );
});

test('poles chrome report page defaults to the current month', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(9, 0));

    $this->get(route('poles-chrome.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('poles-chrome/report')
            ->where('filters.craftsman', '')
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->has('summary')
            ->has('monthlyShrink.months', 12)
            ->has('rows')
        );
});

test('poles rangka report page summarizes approved documents per craftsman', function () {
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
        PolishFrame::factory()->done()->create([
            'doc_no' => 'PRKRPT0001',
            'craftsman_id' => $secondCraftsman->row_id,
            'send_craftsman_date' => '1999-04-02 08:00:00',
            'received_craftsman_date' => '1999-04-02 10:00:00',
            'start_weight' => '10.00',
            'finish_weight' => '9.50',
            'shrink' => '0.50',
            'status_item' => 'NOK',
        ]),
        PolishFrame::factory()->create([
            'doc_no' => 'PRKRPT0002',
            'status' => PolishFrame::STATUS_TO_PPIC,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-03 08:00:00',
            'received_craftsman_date' => '1999-04-03 11:30:00',
            'start_weight' => '5.00',
            'finish_weight' => '4.50',
            'shrink' => '0.50',
            'status_item' => 'OK',
        ]),
        PolishFrame::factory()->create([
            'doc_no' => 'PRKRPT0003',
            'status' => PolishFrame::STATUS_TO_CRAFTSMAN,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-04 08:00:00',
        ]),
    ]);

    try {
        $this->get(route('poles-rangka.report', [
            'date_from' => '1999-04-01',
            'date_to' => '1999-04-30',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('poles-rangka/report')
                ->where('summary.documentCount', 2)
                ->where('summary.craftsmanCount', 2)
                ->where('summary.startWeight', '15.00')
                ->where('summary.shrink', '1.00')
                ->where('summary.shrinkPercent', '6.67%')
                ->where('summary.qcNotOkCount', 1)
                ->has('rows', 2)
                ->where('rows.0.docNo', 'PRKRPT0002')
                ->where('rows.1.docNo', 'PRKRPT0001')
                ->where('rows.1.qcStatus', 'NOT OK')
                ->where('rows.1.workDuration', '2 jam')
            );
    } finally {
        $documents->each->delete();
    }
});

test('poles chrome report page summarizes approved documents per craftsman', function () {
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
        PolishFinishedGood::factory()->done()->create([
            'doc_no' => 'PFGRPT0001',
            'process_name' => 'General',
            'craftsman_id' => $secondCraftsman->row_id,
            'send_craftsman_date' => '1999-04-02 08:00:00',
            'received_craftsman_date' => '1999-04-02 10:00:00',
            'start_weight' => '10.00',
            'finish_weight' => '9.50',
            'shrink' => '0.50',
            'status_item' => 'NOK',
        ]),
        PolishFinishedGood::factory()->create([
            'doc_no' => 'PFGRPT0002',
            'status' => PolishFinishedGood::STATUS_TO_PPIC,
            'process_name' => 'Reparation',
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-03 08:00:00',
            'received_craftsman_date' => '1999-04-03 11:30:00',
            'start_weight' => '5.00',
            'finish_weight' => '4.50',
            'shrink' => '0.50',
            'status_item' => 'OK',
        ]),
        PolishFinishedGood::factory()->create([
            'doc_no' => 'PFGRPT0003',
            'status' => PolishFinishedGood::STATUS_TO_CRAFTSMAN,
            'craftsman_id' => $firstCraftsman->row_id,
            'send_craftsman_date' => '1999-04-04 08:00:00',
        ]),
    ]);

    try {
        $this->get(route('poles-chrome.report', [
            'date_from' => '1999-04-01',
            'date_to' => '1999-04-30',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('poles-chrome/report')
                ->where('summary.documentCount', 2)
                ->where('summary.craftsmanCount', 2)
                ->where('summary.startWeight', '15.00')
                ->where('summary.shrink', '1.00')
                ->where('summary.qcNotOkCount', 1)
                ->has('rows', 2)
                ->where('rows.0.docNo', 'PFGRPT0002')
                ->where('rows.0.processName', 'Reparation')
                ->where('rows.1.docNo', 'PFGRPT0001')
                ->where('rows.1.processName', 'General')
                ->where('rows.1.qcStatus', 'NOT OK')
            );
    } finally {
        $documents->each->delete();
    }
});
