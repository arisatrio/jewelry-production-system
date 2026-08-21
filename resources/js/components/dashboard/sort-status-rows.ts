export type DashboardStatusSpkItem = {
    spkNo: string;
    type: string;
    customer: string;
    item: string;
    orderDate: string | null;
    estimatedDelivery: string | null;
    lastProcess: string | null;
    lastProcessDate: string | null;
};

export type DashboardSortKey = keyof DashboardStatusSpkItem;
export type DashboardSortDirection = 'asc' | 'desc';

const DATE_SORT_KEYS = new Set<DashboardSortKey>([
    'orderDate',
    'estimatedDelivery',
    'lastProcessDate',
]);

export const DASHBOARD_SORT_COLUMNS: Array<{
    key: DashboardSortKey;
    label: string;
}> = [
    { key: 'spkNo', label: 'SPK No' },
    { key: 'type', label: 'Tipe' },
    { key: 'customer', label: 'Customer' },
    { key: 'item', label: 'Item' },
    { key: 'orderDate', label: 'Order' },
    { key: 'estimatedDelivery', label: 'Est. Delivery' },
    { key: 'lastProcess', label: 'Proses terakhir' },
    { key: 'lastProcessDate', label: 'Tanggal proses terakhir' },
];

function isEmptySortValue(value: string | null | undefined): boolean {
    return value === null || value === undefined || value === '' || value === '-';
}

export function compareDashboardStatusRows(
    a: DashboardStatusSpkItem,
    b: DashboardStatusSpkItem,
    key: DashboardSortKey,
    direction: DashboardSortDirection,
): number {
    const multiplier = direction === 'asc' ? 1 : -1;
    const aEmpty = isEmptySortValue(a[key]);
    const bEmpty = isEmptySortValue(b[key]);

    if (aEmpty && bEmpty) {
        return 0;
    }

    if (aEmpty) {
        return 1;
    }

    if (bEmpty) {
        return -1;
    }

    const aValue = String(a[key]);
    const bValue = String(b[key]);

    if (DATE_SORT_KEYS.has(key)) {
        const aTime = Date.parse(aValue);
        const bTime = Date.parse(bValue);

        if (!Number.isNaN(aTime) && !Number.isNaN(bTime)) {
            return (aTime - bTime) * multiplier;
        }
    }

    return (
        aValue.localeCompare(bValue, 'id', {
            numeric: true,
            sensitivity: 'base',
        }) * multiplier
    );
}

export function sortDashboardStatusRows(
    rows: DashboardStatusSpkItem[],
    key: DashboardSortKey | null,
    direction: DashboardSortDirection,
): DashboardStatusSpkItem[] {
    if (key === null) {
        return rows;
    }

    return [...rows].sort((a, b) =>
        compareDashboardStatusRows(a, b, key, direction),
    );
}
