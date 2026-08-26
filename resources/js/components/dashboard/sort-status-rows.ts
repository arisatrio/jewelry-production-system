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

const DASHBOARD_MONTHS: Record<string, number> = {
    Jan: 0,
    Feb: 1,
    Mar: 2,
    Apr: 3,
    May: 4,
    Jun: 5,
    Jul: 6,
    Aug: 7,
    Sep: 8,
    Oct: 9,
    Nov: 10,
    Dec: 11,
};

/**
 * Parse dashboard date labels (d-M-Y, e.g. 18-Jan-2026) to local midnight.
 */
export function parseDashboardDateLabel(
    value: string | null | undefined,
): Date | null {
    if (isEmptySortValue(value)) {
        return null;
    }

    const match = String(value).trim().match(
        /^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/,
    );

    if (!match) {
        const fallback = Date.parse(String(value));

        if (Number.isNaN(fallback)) {
            return null;
        }

        const date = new Date(fallback);

        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    const day = Number(match[1]);
    const month = DASHBOARD_MONTHS[match[2] ?? ''];
    const year = Number(match[3]);

    if (
        Number.isNaN(day) ||
        month === undefined ||
        Number.isNaN(year) ||
        day < 1 ||
        day > 31
    ) {
        return null;
    }

    return new Date(year, month, day);
}

/** True when Est. Delivery is before today's local calendar date. */
export function isDashboardDateOverdue(
    value: string | null | undefined,
    today: Date = new Date(),
): boolean {
    const estimated = parseDashboardDateLabel(value);

    if (estimated === null) {
        return false;
    }

    const startOfToday = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );

    return estimated < startOfToday;
}
