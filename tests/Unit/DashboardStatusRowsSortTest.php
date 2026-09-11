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
        typeSkuLabel: 'NEC | WG-NEC-001',
        itemDescription: 'Necklace',
        skuAssigned: true,
        description: 'NEC | WG-NEC-001 Necklace',
        createdDate: '20-Aug-2026',
        orderDate: '20-Aug-2026',
        estimatedDelivery: '31-Aug-2026',
        status: 'In Progress',
        lastProcess: null,
        lastProcessDate: null,
    },
    {
        spkNo: '2026/PRD/01631',
        type: 'Pesanan',
        customer: 'DP-0001 (Sandy Rachel W)',
        item: 'Pendant',
        typeSkuLabel: null,
        itemDescription: 'Pendant',
        skuAssigned: false,
        description: 'Pendant belum assign SKU',
        createdDate: '20-Aug-2026',
        orderDate: '20-Aug-2026',
        estimatedDelivery: '25-Aug-2026',
        status: 'Approved',
        lastProcess: null,
        lastProcessDate: null,
    },
    {
        spkNo: '2026/PRD/01633',
        type: 'Pesanan',
        customer: 'Jessica',
        item: '-',
        typeSkuLabel: null,
        itemDescription: null,
        skuAssigned: false,
        description: 'belum assign SKU',
        createdDate: '19-Aug-2026',
        orderDate: '19-Aug-2026',
        estimatedDelivery: null,
        status: 'Done',
        lastProcess: 'Cor',
        lastProcessDate: '21-Aug-2026',
    },
];

const bySpk = sortDashboardStatusRows(rows, 'spkNo', 'asc').map((row) => row.spkNo);
const byEstAsc = sortDashboardStatusRows(rows, 'estimatedDelivery', 'asc').map((row) => row.spkNo);
const byEstDesc = sortDashboardStatusRows(rows, 'estimatedDelivery', 'desc').map((row) => row.spkNo);
const byCustomer = sortDashboardStatusRows(rows, 'customer', 'asc').map((row) => row.customer);
const byStatus = sortDashboardStatusRows(rows, 'status', 'asc').map((row) => row.status);
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
assert(byCustomer.join(',') === 'Devi,DP-0001 (Sandy Rachel W),Jessica', 'customer asc failed');
assert(byStatus.join(',') === 'Approved,Done,In Progress', 'status asc failed');
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
