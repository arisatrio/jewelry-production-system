import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ChartColumn, LayoutGrid } from 'lucide-react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import {
    formatEstimatedDeliveryRemainingLabel,
    isDashboardDateOverdue,
    isDashboardStatusDone,
    type DashboardStatusSpkItem,
} from '@/components/dashboard/sort-status-rows';
import { cards as dashboardCards } from '@/routes/dashboard';
import { home } from '@/routes';
import { show as spkShow } from '@/routes/spk';
import type { DashboardAnalytics } from '@/pages/analytics/work-order';

type ProcessTab = {
    key: string;
    label: string;
};

type WorkOrderKanbanProps = {
    analytics: DashboardAnalytics;
    filters: {
        month: string;
    };
    navigation: {
        previousMonth: string;
        nextMonth: string | null;
        currentMonth: string;
        isCurrentMonth: boolean;
    };
    processTabs: ProcessTab[];
};

type KanbanColumnKey =
    | 'draft'
    | 'confirmed'
    | 'doneRangka'
    | 'doneBarangJadi'
    | 'other'
    | string;

type KanbanColumn = {
    key: KanbanColumnKey;
    label: string;
    hint: string;
    className: string;
    items: DashboardStatusSpkItem[];
};

const PROCESS_ALIASES: Record<string, string> = {
    finishinghandmade: 'Finishing',
    finishing: 'Finishing',
    jewelcud: 'JewelCAD',
    jewelcad: 'JewelCAD',
    polesbarangjadi: 'Poles Chrome',
    polishfinishedgood: 'Poles Chrome',
    poleschrome: 'Poles Chrome',
    polesrangka: 'Poles Rangka',
    polishframe: 'Poles Rangka',
    pasangbatu: 'Pasang Batu',
    resin: 'Resin',
    coran: 'Coran',
};

function normalizeProcessName(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function matchProcessTabKey(
    lastProcess: string | null | undefined,
    processTabs: ProcessTab[],
): string | null {
    const value = (lastProcess ?? '').trim();

    if (value === '' || value.toLowerCase() === 'done') {
        return null;
    }

    const exact = processTabs.find(
        (tab) =>
            tab.key.localeCompare(value, undefined, { sensitivity: 'accent' }) ===
                0 ||
            tab.label.localeCompare(value, undefined, {
                sensitivity: 'accent',
            }) === 0,
    );

    if (exact) {
        return exact.key;
    }

    const normalized = normalizeProcessName(value);
    const aliasedKey = PROCESS_ALIASES[normalized];

    if (aliasedKey) {
        const aliased = processTabs.find((tab) => tab.key === aliasedKey);

        if (aliased) {
            return aliased.key;
        }
    }

    return (
        processTabs.find(
            (tab) =>
                normalizeProcessName(tab.key) === normalized ||
                normalizeProcessName(tab.label) === normalized,
        )?.key ?? null
    );
}

function isDoneRangkaStatus(status: string | null | undefined): boolean {
    return String(status ?? '')
        .trim()
        .toUpperCase()
        .includes('RANGKA');
}

function columnKeyForItem(
    item: DashboardStatusSpkItem,
    processTabs: ProcessTab[],
): KanbanColumnKey {
    if (isDashboardStatusDone(item.status)) {
        return isDoneRangkaStatus(item.status)
            ? 'doneRangka'
            : 'doneBarangJadi';
    }

    const processKey = matchProcessTabKey(item.lastProcess, processTabs);

    if (processKey !== null) {
        return processKey;
    }

    if (!item.lastProcess || item.lastProcess.trim() === '') {
        const status = String(item.status ?? '')
            .trim()
            .toLowerCase();

        if (
            status === 'draft' ||
            status.includes('menunggu') ||
            status.includes('approval')
        ) {
            return 'draft';
        }

        return 'confirmed';
    }

    return 'other';
}

function collectUniqueKanbanItems(
    analytics: DashboardAnalytics,
): DashboardStatusSpkItem[] {
    const { statusLists } = analytics;
    const sources: DashboardStatusSpkItem[][] = [
        statusLists.doneBarangJadi ?? [],
        statusLists.doneRangka ?? [],
        statusLists.inProgress ?? [],
        statusLists.confirmed ?? [],
        statusLists.draft ?? [],
        // Overdue is a cross-cutting flag; keep unique cards from primary lists only.
    ];

    const bySpkNo = new Map<string, DashboardStatusSpkItem>();

    for (const list of sources) {
        for (const item of list) {
            const key = item.spkNo || `${item.type}-${item.customer}-${item.item}`;

            if (!bySpkNo.has(key)) {
                bySpkNo.set(key, item);
            }
        }
    }

    return [...bySpkNo.values()];
}

function compactSlaLabel(
    remainingDays: number | null | undefined,
): string | null {
    if (remainingDays === null || remainingDays === undefined) {
        return null;
    }

    if (remainingDays > 0) {
        return `SLA ${remainingDays}h`;
    }

    if (remainingDays === 0) {
        return 'SLA hari ini';
    }

    return `Lewat ${Math.abs(remainingDays)}h`;
}

function cardItemLabel(item: DashboardStatusSpkItem): string {
    const typeSku = item.typeSkuLabel?.trim() ?? '';
    const description = item.itemDescription?.trim() ?? '';

    if (item.skuAssigned === false) {
        return description !== '' ? description : 'Belum assign SKU';
    }

    if (typeSku !== '' && description !== '') {
        return `${typeSku} · ${description}`;
    }

    return typeSku || description || item.item || '—';
}

function KanbanCard({ item }: { item: DashboardStatusSpkItem }) {
    const overdue = isDashboardDateOverdue(item.estimatedDelivery);
    const remainingLabel = formatEstimatedDeliveryRemainingLabel(
        item.estimatedDelivery,
    );
    const slaLabel = compactSlaLabel(item.processSlaRemainingDays);
    const needsAttention =
        item.processSlaRemainingDays !== null &&
        item.processSlaRemainingDays !== undefined &&
        item.processSlaRemainingDays <= 1;
    const customerLine = [item.type, item.customer]
        .filter((value) => value && value !== '-' && value !== '—')
        .join(' · ');

    return (
        <article
            className={`woKanbanCard${overdue ? ' is-overdue' : ''}${needsAttention ? ' is-attention' : ''}`}
            title={[
                item.spkNo,
                item.status,
                customerLine,
                cardItemLabel(item),
                item.estimatedDelivery
                    ? `Est. ${item.estimatedDelivery}`
                    : null,
                slaLabel,
            ]
                .filter(Boolean)
                .join(' · ')}
        >
            <header className="woKanbanCardHeader">
                {item.spkNo !== '-' ? (
                    <a
                        href={spkShow.url(item.spkNo)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="woKanbanCardSpk"
                    >
                        {item.spkNo}
                    </a>
                ) : (
                    <span className="woKanbanCardSpk">—</span>
                )}
                <div className="woKanbanCardBadges">
                    {overdue ? (
                        <span className="woKanbanCardBadge is-overdue">OD</span>
                    ) : needsAttention ? (
                        <span className="woKanbanCardBadge is-attention">!</span>
                    ) : null}
                </div>
            </header>

            <p className="woKanbanCardLine is-primary">
                {cardItemLabel(item)}
            </p>
            {customerLine !== '' ? (
                <p className="woKanbanCardLine is-muted">{customerLine}</p>
            ) : null}

            <footer className="woKanbanCardFooter">
                <span>
                    {item.estimatedDelivery ?? '—'}
                    {remainingLabel ? (
                        <span className="woKanbanCardMetaHint">
                            {' '}
                            {remainingLabel}
                        </span>
                    ) : null}
                </span>
                {slaLabel ? (
                    <span
                        className={`woKanbanCardSla${needsAttention || (item.processSlaRemainingDays ?? 1) < 0 ? ' is-warn' : ''}`}
                    >
                        {slaLabel}
                    </span>
                ) : null}
            </footer>
        </article>
    );
}

export default function WorkOrderKanbanDashboard({
    analytics,
    filters,
    navigation,
    processTabs,
}: WorkOrderKanbanProps) {
    const { period, today, summary } = analytics;
    const needsAttentionCount =
        today.needsAttentionSpk ?? summary.needsAttentionSpk ?? 0;

    const uniqueItems = collectUniqueKanbanItems(analytics);

    const itemsByColumn = new Map<KanbanColumnKey, DashboardStatusSpkItem[]>();

    for (const item of uniqueItems) {
        const key = columnKeyForItem(item, processTabs);
        const bucket = itemsByColumn.get(key);

        if (bucket) {
            bucket.push(item);
        } else {
            itemsByColumn.set(key, [item]);
        }
    }

    const processColumns: KanbanColumn[] = [];

    for (const tab of processTabs) {
        processColumns.push({
            key: tab.key,
            label: tab.label,
            hint: '',
            className: 'is-process',
            items: itemsByColumn.get(tab.key) ?? [],
        });

        if (tab.key === 'Poles Rangka') {
            processColumns.push({
                key: 'doneRangka',
                label: 'DONE (Rangka)',
                hint: '',
                className: 'is-done',
                items: itemsByColumn.get('doneRangka') ?? [],
            });
        }
    }

    const columns: KanbanColumn[] = [
        {
            key: 'draft',
            label: 'Menunggu Approval',
            hint: '',
            className: 'is-draft',
            items: itemsByColumn.get('draft') ?? [],
        },
        {
            key: 'confirmed',
            label: 'Approved',
            hint: '',
            className: 'is-confirmed',
            items: itemsByColumn.get('confirmed') ?? [],
        },
        ...processColumns,
        {
            key: 'other',
            label: 'Proses Lain',
            hint: '',
            className: 'is-other',
            items: itemsByColumn.get('other') ?? [],
        },
        {
            key: 'doneBarangJadi',
            label: 'DONE (Barang Jadi)',
            hint: '',
            className: 'is-done',
            items: itemsByColumn.get('doneBarangJadi') ?? [],
        },
    ].filter(
        (column) =>
            column.key !== 'other' || column.items.length > 0,
    );

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            home.url({
                query: {
                    month,
                },
            }),
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Dashboard" />

            <div className="dashShell woKanbanShell">
                <header className="dashPageHeader woKanbanPageHeader">
                    <div className="woKanbanPageHeaderStart">
                        <h1 className="dashPageTitle">Dashboard</h1>
                    </div>
                    <div className="woKanbanPageHeaderCenter">
                        <div
                            className="woKanbanViewSwitch"
                            role="group"
                            aria-label="Tampilan Dashboard"
                        >
                            <Link
                                href={dashboardCards.url({
                                    query: {
                                        month: filters.month,
                                    },
                                })}
                                className="woKanbanViewSwitchLink"
                                prefetch
                            >
                                <ChartColumn
                                    aria-hidden="true"
                                    className="woKanbanViewSwitchIcon"
                                />
                                Dashboard
                            </Link>
                            <span
                                className="woKanbanViewSwitchLink is-active"
                                aria-current="page"
                            >
                                <LayoutGrid
                                    aria-hidden="true"
                                    className="woKanbanViewSwitchIcon"
                                />
                                Kanban
                            </span>
                        </div>
                    </div>
                    <div className="dashHeaderActions woKanbanPageHeaderEnd">
                        {needsAttentionCount > 0 ? (
                            <div
                                className="dashNeedsAttentionChip dashNeedsAttentionChip--active"
                                title="In progress · H-1 SLA, sisa hari = 0 atau > 0"
                            >
                                <AlertTriangle
                                    aria-hidden="true"
                                    className="dashNeedsAttentionChipIcon"
                                />
                                <span className="dashNeedsAttentionChipText">
                                    <strong className="dashNeedsAttentionChipValue">
                                        {needsAttentionCount.toLocaleString(
                                            'id-ID',
                                        )}
                                    </strong>{' '}
                                    SPK perlu perhatian
                                </span>
                            </div>
                        ) : null}

                        <div
                            className="dashPeriodPager"
                            aria-label="Navigasi bulan"
                        >
                            <Button
                                design="Transparent"
                                icon={navigationLeftIcon}
                                tooltip="Bulan sebelumnya"
                                onClick={() =>
                                    goToMonth(navigation.previousMonth)
                                }
                            />
                            <div
                                className="dashPeriodBadge"
                                aria-label="Periode"
                            >
                                {period.label}
                            </div>
                            <Button
                                design="Transparent"
                                icon={navigationRightIcon}
                                tooltip="Bulan berikutnya"
                                disabled={navigation.nextMonth === null}
                                onClick={() => goToMonth(navigation.nextMonth)}
                            />
                            {!navigation.isCurrentMonth ? (
                                <Button
                                    design="Transparent"
                                    className="dashPeriodTodayBtn"
                                    onClick={() =>
                                        goToMonth(navigation.currentMonth)
                                    }
                                >
                                    Bulan ini
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </header>

                <div
                    className="woKanbanBoard"
                    aria-label="Dashboard Kanban per proses"
                >
                    {columns.map((column) => (
                        <section
                            key={column.key}
                            className={`woKanbanColumn ${column.className}`}
                            aria-label={column.label}
                        >
                            <header className="woKanbanColumnHeader">
                                <div className="woKanbanColumnTitleBlock">
                                    <h2 className="woKanbanColumnTitle">
                                        {column.label}
                                    </h2>
                                    {column.hint !== '' ? (
                                        <p className="woKanbanColumnHint">
                                            {column.hint}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="woKanbanColumnCounts">
                                    <strong className="woKanbanColumnCount">
                                        {column.items.length.toLocaleString(
                                            'id-ID',
                                        )}
                                    </strong>
                                </div>
                            </header>

                            <div className="woKanbanColumnBody">
                                {column.items.length === 0 ? (
                                    <p className="woKanbanEmpty">
                                        Tidak ada SPK
                                    </p>
                                ) : (
                                    column.items.map((item) => (
                                        <KanbanCard
                                            key={`${column.key}-${item.spkNo}`}
                                            item={item}
                                        />
                                    ))
                                )}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

WorkOrderKanbanDashboard.layout = {
    activeMenu: 'Dashboard',
    pageTitle: 'Dashboard',
};
