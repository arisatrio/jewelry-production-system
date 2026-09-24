export type DashboardStatusSpkItem = {
    spkNo: string;
    type: string;
    customer: string;
    item: string;
    typeSkuLabel: string | null;
    itemDescription: string | null;
    skuAssigned: boolean;
    description: string;
    createdDate: string | null;
    orderDate: string | null;
    estimatedDelivery: string | null;
    status: string;
    lastProcess: string | null;
    lastProcessDate: string | null;
    processSlaRemainingDays?: number | null;
};

export type DashboardSortKey =
    | 'spkNo'
    | 'type'
    | 'customer'
    | 'item'
    | 'typeSkuLabel'
    | 'itemDescription'
    | 'skuAssigned'
    | 'description'
    | 'createdDate'
    | 'orderDate'
    | 'estimatedDelivery'
    | 'status'
    | 'lastProcess'
    | 'lastProcessDate';

export type DashboardSortDirection = 'asc' | 'desc';

const DATE_SORT_KEYS = new Set<DashboardSortKey>([
    'createdDate',
    'orderDate',
    'estimatedDelivery',
    'lastProcessDate',
]);

export const DASHBOARD_SORT_COLUMNS: Array<{
    key: DashboardSortKey;
    label: string;
}> = [
    { key: 'spkNo', label: 'Produksi No' },
    { key: 'type', label: 'Tipe Produksi' },
    { key: 'description', label: 'Tipe | SKU' },
    { key: 'createdDate', label: 'Tanggal SPK Dibuat' },
    { key: 'orderDate', label: 'Tanggal Permintaan' },
    { key: 'estimatedDelivery', label: 'Tanggal Estimasi Selesai' },
    { key: 'lastProcess', label: 'Proses terakhir' },
    { key: 'status', label: 'Status' },
];

export const DASHBOARD_CENTERED_COLUMNS = new Set<DashboardSortKey>([
    'createdDate',
    'orderDate',
    'estimatedDelivery',
    'lastProcess',
    'status',
]);

function isEmptySortValue(value: string | null | undefined | boolean): boolean {
    return (
        value === null ||
        value === undefined ||
        value === '' ||
        value === '-' ||
        value === '—'
    );
}

export function compareDashboardStatusRows(
    a: DashboardStatusSpkItem,
    b: DashboardStatusSpkItem,
    key: DashboardSortKey,
    direction: DashboardSortDirection,
): number {
    const multiplier = direction === 'asc' ? 1 : -1;
    const aEmpty = isEmptySortValue(a[key] as string | null | undefined | boolean);
    const bEmpty = isEmptySortValue(b[key] as string | null | undefined | boolean);

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

export type DashboardStatusTab = 'inProgress' | 'done';

/** True when status is a DONE label (Rangka / Barang Jadi). */
export function isDashboardStatusDone(status: string | null | undefined): boolean {
    const value = String(status ?? '')
        .trim()
        .toUpperCase();

    return value.startsWith('DONE');
}

export function filterDashboardStatusRowsByTab(
    rows: DashboardStatusSpkItem[],
    tab: DashboardStatusTab,
): DashboardStatusSpkItem[] {
    return rows.filter((row) => {
        const isDone = isDashboardStatusDone(row.status);

        return tab === 'done' ? isDone : !isDone;
    });
}

export function countDashboardStatusRowsByTab(
    rows: DashboardStatusSpkItem[],
): Record<DashboardStatusTab, number> {
    let inProgress = 0;
    let done = 0;

    for (const row of rows) {
        if (isDashboardStatusDone(row.status)) {
            done += 1;
        } else {
            inProgress += 1;
        }
    }

    return { inProgress, done };
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

    const match = String(value)
        .trim()
        .match(/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/);

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

/**
 * Label sisa hari sampai estimasi selesai, contoh: "(2 hari lagi)".
 * Null jika tanggal invalid atau sudah lewat (pakai badge Overdue).
 */
export function formatEstimatedDeliveryRemainingLabel(
    value: string | null | undefined,
    today: Date = new Date(),
): string | null {
    const estimated = parseDashboardDateLabel(value);

    if (estimated === null) {
        return null;
    }

    const startOfToday = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    const days = Math.round(
        (estimated.getTime() - startOfToday.getTime()) / 86_400_000,
    );

    if (days < 0) {
        return null;
    }

    if (days === 0) {
        return '(hari ini)';
    }

    return `(${days} hari lagi)`;
}

/**
 * Label sisa/lewat hari dari target SLA proses,
 * contoh: "lewat 1 hari dari target proses JewelCAD".
 */
export function formatProcessSlaRemainingLabel(
    remainingDays: number | null | undefined,
    processName?: string | null,
): string | null {
    if (remainingDays === null || remainingDays === undefined) {
        return null;
    }

    const processLabel = (processName ?? '').trim();
    const targetSuffix =
        processLabel !== '' ? ` target proses ${processLabel}` : ' target';

    if (remainingDays > 0) {
        return `sisa ${remainingDays} hari dari${targetSuffix}`;
    }

    if (remainingDays === 0) {
        return processLabel !== ''
            ? `hari terakhir target proses ${processLabel}`
            : 'hari terakhir target';
    }

    return `lewat ${Math.abs(remainingDays)} hari dari${targetSuffix}`;
}
