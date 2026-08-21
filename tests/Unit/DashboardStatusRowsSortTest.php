<?php

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

test('dashboard status rows sort helper orders text and date columns', function () {
    $script = <<<'JS'
import { sortDashboardStatusRows } from './resources/js/components/dashboard/sort-status-rows.ts';

const rows = [
    {
        spkNo: '2026/PRD/01632',
        type: 'Stock',
        customer: 'Devi',
        item: 'Necklace',
        orderDate: '20-Aug-2026',
        estimatedDelivery: '31-Aug-2026',
        lastProcess: null,
        lastProcessDate: null,
    },
    {
        spkNo: '2026/PRD/01631',
        type: 'Pesanan',
        customer: 'Sandy Rachel W',
        item: 'Pendant',
        orderDate: '20-Aug-2026',
        estimatedDelivery: '25-Aug-2026',
        lastProcess: null,
        lastProcessDate: null,
    },
    {
        spkNo: '2026/PRD/01633',
        type: 'Pesanan',
        customer: 'Jessica',
        item: '-',
        orderDate: '19-Aug-2026',
        estimatedDelivery: null,
        lastProcess: 'Cor',
        lastProcessDate: '21-Aug-2026 10:00',
    },
];

const bySpk = sortDashboardStatusRows(rows, 'spkNo', 'asc').map((row) => row.spkNo);
const byEstAsc = sortDashboardStatusRows(rows, 'estimatedDelivery', 'asc').map((row) => row.spkNo);
const byEstDesc = sortDashboardStatusRows(rows, 'estimatedDelivery', 'desc').map((row) => row.spkNo);
const byCustomer = sortDashboardStatusRows(rows, 'customer', 'asc').map((row) => row.customer);
const unchanged = sortDashboardStatusRows(rows, null, 'asc').map((row) => row.spkNo);

const assert = (condition, message) => {
    if (!condition) {
        console.error(message);
        process.exit(1);
    }
};

assert(bySpk.join(',') === '2026/PRD/01631,2026/PRD/01632,2026/PRD/01633', 'spk asc failed');
assert(byEstAsc.join(',') === '2026/PRD/01631,2026/PRD/01632,2026/PRD/01633', 'est asc failed');
assert(byEstDesc.join(',') === '2026/PRD/01632,2026/PRD/01631,2026/PRD/01633', 'est desc failed');
assert(byCustomer.join(',') === 'Devi,Jessica,Sandy Rachel W', 'customer asc failed');
assert(unchanged.join(',') === '2026/PRD/01632,2026/PRD/01631,2026/PRD/01633', 'null sort key failed');

// Default modal sort: estimated delivery descending (empty values last)
const defaultModalSort = sortDashboardStatusRows(rows, 'estimatedDelivery', 'desc').map((row) => row.spkNo);
assert(defaultModalSort.join(',') === '2026/PRD/01632,2026/PRD/01631,2026/PRD/01633', 'default modal sort failed');

console.log('OK');
JS;

    $result = Process::path(base_path())
        ->run(['node', '--experimental-strip-types', '-e', $script]);

    expect($result->successful())->toBeTrue()
        ->and(trim($result->errorOutput().$result->output()))->toContain('OK');
});
