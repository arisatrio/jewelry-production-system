import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, AlertTriangle, Eye } from 'lucide-react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DistributionPieChart } from '@/components/dashboard/distribution-pie-chart';
import { ForecastClusteredBarChart } from '@/components/dashboard/forecast-clustered-bar-chart';
import { InProgressProcessBarChart } from '@/components/dashboard/in-progress-process-bar-chart';
import {
    DASHBOARD_CENTERED_COLUMNS,
    DASHBOARD_SORT_COLUMNS,
    countDashboardStatusRowsByTab,
    filterDashboardStatusRowsByTab,
    formatEstimatedDeliveryRemainingLabel,
    formatProcessSlaRemainingLabel,
    isDashboardDateOverdue,
    sortDashboardStatusRows,
    type DashboardSortDirection,
    type DashboardSortKey,
    type DashboardStatusSpkItem,
    type DashboardStatusTab,
} from '@/components/dashboard/sort-status-rows';

import {
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    SpkTableStatusCell,
    SpkTableTipeProduksiCell,
} from '@/components/spk/spk-list-cells';
import { workOrder } from '@/routes/analytics';
import { show as spkShow } from '@/routes/spk';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type { DashboardStatusSpkItem };

export type DashboardDistributionItem = {
    label: string;
    count: number;
    qty: number;
    percent: string;
};

export type DashboardForecastItem = {
    label: string;
    count: number;
    qty: number;
    percent: string;
};

export type DashboardStatusKey =
    | 'draft'
    | 'confirmed'
    | 'inProgress'
    | 'overdue'
    | 'doneRangka'
    | 'doneBarangJadi';

export type DashboardTodayKey =
    | 'todayCreated'
    | 'todayInProcess'
    | 'todayTarget'
    | 'weekTarget'
    | 'needsAttention'
    | 'monthTarget'
    | 'monthOverdue';

export type DashboardChartKey =
    | 'productionTypes'
    | 'itemDistribution'
    | 'bottleneck'
    | 'forecast';

export type DashboardListKey =
    | DashboardStatusKey
    | DashboardTodayKey
    | DashboardChartKey;

export type DashboardAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    backlogYear: number;
    summary: {
        totalSpk: number;
        draftSpk: number;
        confirmedSpk: number;
        inProgressSpk: number;
        doneSpk: number;
        doneRangkaSpk: number;
        doneBarangJadiSpk: number;
        overdueSpk: number;
        totalShrink: string;
        shrinkOkCount: number;
        shrinkNokCount: number;
        avgLeadTimeDays: string | null;
        avgYieldPercent: string | null;
        goldUsed: string;
        goldRequirement: string;
        stoneDifference: string;
        forecastSpk: number;
        forecastQty: number;
        planningDoneSpk: number;
        planningPendingSpk: number;
        todayTargetSpk: number;
        todayTargetDoneSpk: number;
        todayTargetPendingSpk: number;
        todayCreatedSpk: number;
        todayInProcessSpk: number;
        weekTargetSpk: number;
        weekTargetDoneSpk: number;
        weekTargetPendingSpk: number;
        weekTargetLabel: string;
        needsAttentionSpk: number;
        monthOverdueSpk: number;
    };
    today: {
        date: string;
        label: string;
        targetSpk: number;
        targetDoneSpk: number;
        targetPendingSpk: number;
        targetQty: number;
        createdSpk: number;
        inProcessSpk: number;
        overdueSpk: number;
        needsAttentionSpk: number;
    };
    weekTarget?: {
        start: string;
        end: string;
        label: string;
        targetSpk: number;
        targetDoneSpk: number;
        targetPendingSpk: number;
    };
    statusLists: Record<DashboardStatusKey, DashboardStatusSpkItem[]>;
    todayLists: Record<DashboardTodayKey, DashboardStatusSpkItem[]>;
    chartLists: Record<DashboardChartKey, DashboardStatusSpkItem[]>;
    productionTypes: DashboardDistributionItem[];
    itemDistribution: DashboardDistributionItem[];
    inProgressByProcess: Array<{
        label: string;
        count: number;
    }>;
    shrink: {
        byProcess: Array<{
            process: string;
            totalShrink: string;
            recordCount: number;
            avgPercent: string | null;
            nokCount: number | null;
        }>;
        totalShrink: string;
        okCount: number;
        nokCount: number;
    };
    control: {
        avgLeadTimeDays: string | null;
        avgEstimatedDays: string | null;
        avgVarianceDays: string | null;
        avgYieldPercent: string | null;
        avgGoldYieldPercent: string | null;
    };
    craftsmen: Array<{
        name: string;
        jobCount: number;
        totalShrink: string;
    }>;
    gold: {
        issued: string;
        returned: string;
        used: string;
        difference: string;
    };
    stone: {
        startCrt: string;
        endCrt: string;
        difference: string;
    };
    forecast: {
        spkCount: number;
        qtyTotal: number;
        byItem: DashboardForecastItem[];
        byType: DashboardForecastItem[];
        types: string[];
        byItemType: Array<{
            item: string;
            total: number;
            values: Record<string, number>;
        }>;
    };
    planningDaily: {
        doneTotal: number;
        pendingTotal: number;
        days: Array<{
            date: string;
            label: string;
            done: number;
            pending: number;
        }>;
    };
};

type WorkOrderDashboardProps = {
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
};

export default function WorkOrderDashboard({ analytics, navigation }: WorkOrderDashboardProps) {
    const {
        summary,
        statusLists,
        todayLists,
        chartLists,
        forecast,
        today,
        weekTarget,
        period,
        backlogYear,
        inProgressByProcess,
    } = analytics;
    const [openList, setOpenList] = useState<DashboardListKey | null>(null);
    const [listSortKey, setListSortKey] = useState<DashboardSortKey | null>(
        'estimatedDelivery',
    );
    const [listSortDirection, setListSortDirection] =
        useState<DashboardSortDirection>('asc');
    const [listStatusTab, setListStatusTab] =
        useState<DashboardStatusTab>('inProgress');
    const monthTargetSpk =
        summary.planningDoneSpk + summary.planningPendingSpk;
    const weekTargetSpk = weekTarget?.targetSpk ?? 0;
    const weekTargetDoneSpk = weekTarget?.targetDoneSpk ?? 0;
    const weekTargetLabel = weekTarget?.label ?? 'Minggu ini';
    const needsAttentionCount =
        today.needsAttentionSpk ?? summary.needsAttentionSpk ?? 0;
    const todayTargetCompletionPercent =
        today.targetSpk > 0
            ? ((today.targetDoneSpk / today.targetSpk) * 100).toFixed(1)
            : '0.0';
    const weekTargetCompletionPercent =
        weekTargetSpk > 0
            ? ((weekTargetDoneSpk / weekTargetSpk) * 100).toFixed(1)
            : '0.0';
    const monthTargetCompletionPercent =
        monthTargetSpk > 0
            ? ((summary.planningDoneSpk / monthTargetSpk) * 100).toFixed(1)
            : '0.0';
    const targetCardKeys = new Set<DashboardTodayKey>([
        'todayTarget',
        'weekTarget',
        'monthTarget',
    ]);

    const targetToneClass = (percentText: string, totalTarget: number): string => {
        if (totalTarget <= 0) {
            return '';
        }

        const percent = Number(percentText);

        if (percent >= 100) {
            return 'is-target-good';
        }

        if (percent >= 80) {
            return 'is-target-warning';
        }

        return 'is-target-critical';
    };

    const statusCards: Array<{
        key: DashboardStatusKey;
        label: string;
        hint: string;
        count: number;
        className: string;
    }> = [
        {
            key: 'draft',
            label: 'Menunggu Approval',
            hint: 'Sudah diajukan dan dikirim ke produksi',
            count: summary.draftSpk,
            className: 'is-draft',
        },
        {
            key: 'confirmed',
            label: 'Approved',
            hint: 'Sudah diapprove, belum diproses',
            count: summary.confirmedSpk,
            className: 'is-confirmed',
        },
        {
            key: 'inProgress',
            label: 'In Progress',
            hint: 'Sedang proses produksi',
            count: summary.inProgressSpk,
            className: 'is-progress',
        },
        {
            key: 'overdue',
            label: 'SPK Overdue Estimasi',
            hint: 'Lewat estimasi',
            count: summary.overdueSpk,
            className: 'is-overdue',
        },
        {
            key: 'doneRangka',
            label: 'DONE (Rangka)',
            hint: 'Poles Rangka selesai, tanpa Pasang Batu / Poles Chrome',
            count: summary.doneRangkaSpk ?? 0,
            className: 'is-done',
        },
        {
            key: 'doneBarangJadi',
            label: 'DONE (Barang Jadi)',
            hint: 'Poles Chrome completed / Serahkan ke JB',
            count: summary.doneBarangJadiSpk ?? 0,
            className: 'is-done',
        },
    ];

    const todayCards: Array<{
        key: DashboardTodayKey;
        label: string;
        hint: string;
        count: number;
        completionPercent?: string;
        className?: string;
    }> = [
        {
            key: 'todayCreated',
            label: 'SPK Dibuat Hari Ini',
            hint: `Created date · ${today.label}`,
            count: today.createdSpk,
        },
        {
            key: 'todayInProcess',
            label: 'SPK Diproses Hari Ini',
            hint: 'In progress',
            count: today.inProcessSpk,
        },
        {
            key: 'todayTarget',
            label: 'Target SPK Hari Ini',
            hint: `${today.label} · ${today.targetDoneSpk.toLocaleString('id-ID')} selesai dari ${today.targetSpk.toLocaleString('id-ID')}${today.targetQty > 0 ? ` · qty ${today.targetQty.toLocaleString('id-ID')}` : ''}`,
            count: today.targetSpk,
            completionPercent: todayTargetCompletionPercent,
        },
        {
            key: 'weekTarget',
            label: 'Target SPK Minggu Ini',
            hint: `${weekTargetLabel} · ${weekTargetDoneSpk.toLocaleString('id-ID')} selesai dari ${weekTargetSpk.toLocaleString('id-ID')}`,
            count: weekTargetSpk,
            completionPercent: weekTargetCompletionPercent,
        },
        {
            key: 'monthTarget',
            label: 'Target SPK Bulan Ini',
            hint: `${period.label} · ${summary.planningDoneSpk.toLocaleString('id-ID')} selesai dari ${monthTargetSpk.toLocaleString('id-ID')}`,
            count: monthTargetSpk,
            completionPercent: monthTargetCompletionPercent,
        },
        {
            key: 'monthOverdue',
            label: 'SPK Overdue Bulan Ini',
            hint: `Lewat estimasi · ${period.label}`,
            count: today.overdueSpk,
            className: 'is-overdue',
        },
    ];

    const bottleneckSpkCount = inProgressByProcess.reduce(
        (sum, row) => sum + row.count,
        0,
    );

    const chartCards: Array<{
        key: DashboardChartKey;
        label: string;
        hint: string;
        count: number;
    }> = [
        {
            key: 'productionTypes',
            label: 'Tipe Produksi',
            hint: `Dibuat atau estimasi selesai ${period.label}`,
            count: analytics.productionTypes.reduce(
                (sum, row) => sum + row.count,
                0,
            ),
        },
        {
            key: 'itemDistribution',
            label: 'Item Produksi',
            hint: `Dibuat atau estimasi selesai ${period.label}`,
            count: analytics.itemDistribution.reduce(
                (sum, row) => sum + row.count,
                0,
            ),
        },
        {
            key: 'bottleneck',
            label: 'Bottleneck Proses SPK',
            hint: `Lewat SLA proses · ${period.label}`,
            count: bottleneckSpkCount,
        },
        {
            key: 'forecast',
            label: 'Planning Estimasi Vs Realisasi',
            hint: `Estimasi selesai ${period.label}`,
            count: forecast.spkCount,
        },
    ];

    const openListMeta =
        statusCards.find((card) => card.key === openList) ??
        todayCards.find((card) => card.key === openList) ??
        chartCards.find((card) => card.key === openList) ??
        (openList === 'needsAttention'
            ? {
                  key: 'needsAttention' as const,
                  label: 'SPK Perlu Perhatian',
                  hint: 'In progress · H-1 SLA, sisa hari = 0 atau > 0',
                  count: needsAttentionCount,
              }
            : null);
    const openListRows =
        openList === null
            ? []
            : openList in statusLists
              ? (statusLists[openList as DashboardStatusKey] ?? [])
              : openList in todayLists
                ? (todayLists[openList as DashboardTodayKey] ?? [])
                : (chartLists[openList as DashboardChartKey] ?? []);
    const showStatusTabs =
        openList !== null && targetCardKeys.has(openList as DashboardTodayKey);
    const statusTabCounts = countDashboardStatusRowsByTab(openListRows);
    const statusFilteredRows = showStatusTabs
        ? filterDashboardStatusRowsByTab(openListRows, listStatusTab)
        : openListRows;
    const sortedOpenListRows = sortDashboardStatusRows(
        statusFilteredRows,
        listSortKey,
        listSortDirection,
    );
    const openListPeriodLabel =
        openList !== null &&
        openList in todayLists &&
        openList !== 'monthOverdue' &&
        openList !== 'monthTarget' &&
        openList !== 'weekTarget' &&
        openList !== 'needsAttention'
            ? today.label
            : openList === 'weekTarget'
              ? weekTargetLabel
              : openList === 'needsAttention' ||
                  (openList !== null && openList in statusLists)
                ? `Tahun ${backlogYear}`
                : period.label;

    useEffect(() => {
        setListSortKey('estimatedDelivery');
        setListSortDirection('asc');
        setListStatusTab('inProgress');
    }, [openList]);

    const toggleListSort = (key: DashboardSortKey): void => {
        if (listSortKey === key) {
            setListSortDirection((current) =>
                current === 'asc' ? 'desc' : 'asc',
            );

            return;
        }

        setListSortKey(key);
        setListSortDirection('asc');
    };

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            workOrder.url({
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

            <div className="dashShell">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Dashboard</h1>
                    </div>
                    <div className="dashHeaderActions">
                        <button
                            type="button"
                            className={
                                needsAttentionCount > 0
                                    ? 'dashNeedsAttentionChip dashNeedsAttentionChip--active'
                                    : 'dashNeedsAttentionChip'
                            }
                            title="In progress · H-1 SLA, sisa hari = 0 atau > 0"
                            onClick={() => setOpenList('needsAttention')}
                        >
                            <AlertTriangle
                                aria-hidden="true"
                                className="dashNeedsAttentionChipIcon"
                            />
                            <span className="dashNeedsAttentionChipText">
                                <strong className="dashNeedsAttentionChipValue">
                                    {needsAttentionCount.toLocaleString('id-ID')}
                                </strong>{' '}
                                SPK perlu perhatian
                            </span>
                        </button>
                        <div className="dashPeriodPager" aria-label="Navigasi bulan">
                            <Button
                                design="Transparent"
                                icon={navigationLeftIcon}
                                tooltip="Bulan sebelumnya"
                                onClick={() =>
                                    goToMonth(navigation.previousMonth)
                                }
                            />
                            <div className="dashPeriodBadge" aria-label="Periode">
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


                <div className="dashHeroGrid">
                    <article className="dashPanel dashBacklogPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Backlog SPK ({backlogYear})
                            </h2>
                            <p className="dashPanelMeta">
                                Tahun {backlogYear} · belum selesai / overdue
                            </p>
                        </header>
                        <section
                            className="dashStatusStack"
                            aria-label={`Backlog SPK (${backlogYear})`}
                        >
                            {statusCards.map((card) => (
                                <article
                                    key={card.key}
                                    className={`dashStatusRow ${card.className}`}
                                >
                                    <div className="dashStatusRowMain">
                                        <span className="dashStatusRowLabel">
                                            {card.label}
                                        </span>
                                        <span className="dashStatusRowHint">
                                            {card.hint}
                                        </span>
                                    </div>
                                    <strong className="dashStatusRowValue">
                                        {card.count.toLocaleString('id-ID')}
                                    </strong>
                                    <button
                                        type="button"
                                        className="dashKpiFileBtn"
                                        aria-label={`Lihat daftar ${card.label}`}
                                        title={`Lihat daftar ${card.label}`}
                                        onClick={() => setOpenList(card.key)}
                                    >
                                        <Eye aria-hidden="true" />
                                    </button>
                                </article>
                            ))}
                        </section>
                    </article>

                    <div className="dashHeroMain">
                        <section
                            className="dashTodayKpis"
                            aria-label="Ringkasan hari ini"
                        >
                            {todayCards.map((card) => (
                                <div key={card.key} className="dashKpiCardGroup">
                                    <article
                                        className={`dashKpiCard is-today${card.className ? ` ${card.className}` : ''}${
                                            targetCardKeys.has(card.key)
                                                ? ` ${targetToneClass(card.completionPercent ?? '0.0', card.count)}`
                                                : ''
                                        }`}
                                    >
                                        <span className="dashKpiLabel">
                                            {card.label}
                                        </span>
                                        {targetCardKeys.has(card.key) ? (
                                            <div className="dashKpiValueRow">
                                                <strong className="dashKpiValue">
                                                    {card.count.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </strong>
                                                {card.count > 0 ? (
                                                    <span className="dashKpiSubvalue">
                                                        {card.completionPercent}
                                                        %
                                                    </span>
                                                ) : null}
                                            </div>
                                        ) : (
                                            <strong className="dashKpiValue">
                                                {card.count.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </strong>
                                        )}
                                        <span className="dashKpiHint">
                                            {card.hint}
                                        </span>
                                        <button
                                            type="button"
                                            className="dashKpiFileBtn"
                                            aria-label={`Lihat daftar ${card.label}`}
                                            title={`Lihat daftar ${card.label}`}
                                            onClick={() => setOpenList(card.key)}
                                        >
                                            <Eye aria-hidden="true" />
                                        </button>
                                    </article>
                                </div>
                            ))}
                        </section>

                        <div className="dashPieRow">
                            <div className="dashPieColumn">
                                <article className="dashPanel dashPiePanel is-compact">
                                    <header className="dashPanelHeader is-with-action">
                                        <div className="dashPanelHeaderText">
                                            <h2 className="dashPanelTitle">
                                                Tipe Produksi
                                            </h2>
                                            <p className="dashPanelMeta">
                                                Dibuat atau estimasi selesai{' '}
                                                {period.label}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            className="dashKpiFileBtn dashPanelDetailBtn"
                                            aria-label="Lihat daftar Tipe Produksi"
                                            title="Lihat daftar SPK"
                                            onClick={() =>
                                                setOpenList('productionTypes')
                                            }
                                        >
                                            <Eye aria-hidden="true" />
                                        </button>
                                    </header>
                                    <DistributionPieChart
                                        items={analytics.productionTypes}
                                        legendMetric="spk"
                                    />
                                </article>

                                <article className="dashPanel dashProcessBarPanel">
                                    <header className="dashPanelHeader is-with-action">
                                        <div className="dashPanelHeaderText">
                                            <h2 className="dashPanelTitle">
                                                Bottleneck Proses SPK
                                            </h2>
                                            <p className="dashPanelMeta">
                                                Dibuat atau estimasi selesai{' '}
                                                {period.label} ·{' '}
                                                {bottleneckSpkCount.toLocaleString(
                                                    'id-ID',
                                                )}{' '}
                                                SPK lewat SLA proses
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            className="dashKpiFileBtn dashPanelDetailBtn"
                                            aria-label="Lihat daftar Bottleneck Proses SPK"
                                            title="Lihat daftar SPK"
                                            onClick={() =>
                                                setOpenList('bottleneck')
                                            }
                                        >
                                            <Eye aria-hidden="true" />
                                        </button>
                                    </header>
                                    <InProgressProcessBarChart
                                        items={inProgressByProcess}
                                    />
                                </article>
                            </div>

                            <div className="dashForecastColumn">
                                <article className="dashPanel dashPiePanel is-compact">
                                    <header className="dashPanelHeader is-with-action">
                                        <div className="dashPanelHeaderText">
                                            <h2 className="dashPanelTitle">
                                                Item Produksi
                                            </h2>
                                            <p className="dashPanelMeta">
                                                Dibuat atau estimasi selesai{' '}
                                                {period.label}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            className="dashKpiFileBtn dashPanelDetailBtn"
                                            aria-label="Lihat daftar Item Produksi"
                                            title="Lihat daftar SPK"
                                            onClick={() =>
                                                setOpenList('itemDistribution')
                                            }
                                        >
                                            <Eye aria-hidden="true" />
                                        </button>
                                    </header>
                                    <DistributionPieChart
                                        items={analytics.itemDistribution}
                                        legendMetric="spk"
                                    />
                                </article>

                                <article className="dashPanel dashForecastPanel">
                                    <header className="dashPanelHeader is-with-action">
                                        <div className="dashPanelHeaderText">
                                            <h2 className="dashPanelTitle">
                                                Planning Estimasi Produksi Vs
                                                Realisasi Produksi
                                            </h2>
                                            <p className="dashPanelMeta">
                                                Estimasi selesai {period.label}{' '}
                                                ·{' '}
                                                {forecast.spkCount.toLocaleString(
                                                    'id-ID',
                                                )}{' '}
                                                SPK · cluster per item
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            className="dashKpiFileBtn dashPanelDetailBtn"
                                            aria-label="Lihat daftar Planning Estimasi Vs Realisasi"
                                            title="Lihat daftar SPK"
                                            onClick={() =>
                                                setOpenList('forecast')
                                            }
                                        >
                                            <Eye aria-hidden="true" />
                                        </button>
                                    </header>
                                    <ForecastClusteredBarChart
                                        types={forecast.types}
                                        rows={forecast.byItemType}
                                    />
                                </article>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <Dialog
                open={openList !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenList(null);
                    }
                }}
            >
                <DialogContent className="dashStatusModal sm:max-w-[min(92rem,calc(100vw-2rem))]">
                    <DialogHeader>
                        <DialogTitle>
                            {openListMeta?.label ?? 'Daftar SPK'}
                        </DialogTitle>
                        <DialogDescription>
                            {openListPeriodLabel} ·{' '}
                            {(showStatusTabs
                                ? statusFilteredRows.length
                                : (openListMeta?.count ?? 0)
                            ).toLocaleString('id-ID')}{' '}
                            SPK
                            {!showStatusTabs &&
                            openListRows.length < (openListMeta?.count ?? 0)
                                ? ` · menampilkan ${openListRows.length.toLocaleString('id-ID')} terbaru`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    {showStatusTabs ? (
                        <div
                            className="dashStatusModalTabs"
                            role="tablist"
                            aria-label="Filter status SPK"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={listStatusTab === 'inProgress'}
                                className={`dashStatusModalTab${listStatusTab === 'inProgress' ? ' is-active' : ''}`}
                                onClick={() => setListStatusTab('inProgress')}
                            >
                                <span>In Progress</span>
                                <span className="dashStatusModalTabBadge">
                                    {statusTabCounts.inProgress.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={listStatusTab === 'done'}
                                className={`dashStatusModalTab${listStatusTab === 'done' ? ' is-active' : ''}`}
                                onClick={() => setListStatusTab('done')}
                            >
                                <span>Done</span>
                                <span className="dashStatusModalTabBadge">
                                    {statusTabCounts.done.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                            </button>
                        </div>
                    ) : null}

                    {statusFilteredRows.length === 0 ? (
                        <p className="dashEmpty">
                            Tidak ada SPK untuk kategori ini.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        {DASHBOARD_SORT_COLUMNS.map(
                                            (column) => {
                                                const isActive =
                                                    listSortKey === column.key;
                                                const ariaSort = !isActive
                                                    ? 'none'
                                                    : listSortDirection ===
                                                        'asc'
                                                      ? 'ascending'
                                                      : 'descending';
                                                const SortIcon = !isActive
                                                    ? ArrowUpDown
                                                    : listSortDirection ===
                                                        'asc'
                                                      ? ArrowUp
                                                      : ArrowDown;

                                                return (
                                                    <th
                                                        key={column.key}
                                                        aria-sort={ariaSort}
                                                        className={[
                                                            DASHBOARD_CENTERED_COLUMNS.has(
                                                                column.key,
                                                            )
                                                                ? 'is-text-center'
                                                                : undefined,
                                                            column.key ===
                                                            'lastProcess'
                                                                ? 'dashStatusColLastProcess'
                                                                : undefined,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ') ||
                                                            undefined}
                                                    >
                                                        <button
                                                            type="button"
                                                            className={`dashStatusSortBtn${isActive ? ' is-active' : ''}`}
                                                            onClick={() =>
                                                                toggleListSort(
                                                                    column.key,
                                                                )
                                                            }
                                                        >
                                                            <span>
                                                                {column.label}
                                                            </span>
                                                            <SortIcon
                                                                aria-hidden="true"
                                                                className="dashStatusSortIcon"
                                                            />
                                                        </button>
                                                    </th>
                                                );
                                            },
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedOpenListRows.map((row) => (
                                        <tr key={row.spkNo}>
                                            <td>
                                                {row.spkNo !== '-' ? (
                                                    <a
                                                        href={spkShow.url(
                                                            row.spkNo,
                                                        )}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="dashStatusSpkLink"
                                                    >
                                                        {row.spkNo}
                                                    </a>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="spkTableCustomer">
                                                <SpkTableTipeProduksiCell
                                                    row={{
                                                        tipeProduksi: row.type,
                                                        customer: row.customer,
                                                    }}
                                                />
                                            </td>
                                            <td>
                                                <SpkTableDescriptionCell
                                                    row={{
                                                        typeSkuLabel:
                                                            row.typeSkuLabel,
                                                        itemDescription:
                                                            row.itemDescription,
                                                        skuAssigned:
                                                            row.skuAssigned,
                                                    }}
                                                />
                                            </td>
                                            <td className="is-text-center">
                                                {row.createdDate ?? '—'}
                                            </td>
                                            <td className="is-text-center">
                                                {row.orderDate ?? '—'}
                                            </td>
                                            <td className="is-text-center">
                                                {(() => {
                                                    const remainingLabel =
                                                        formatEstimatedDeliveryRemainingLabel(
                                                            row.estimatedDelivery,
                                                        );

                                                    return (
                                                        <div className="dashStatusDateCell">
                                                            <span>
                                                                {row.estimatedDelivery ??
                                                                    '—'}
                                                            </span>
                                                            {remainingLabel ? (
                                                                <span className="dashStatusRemainingHint">
                                                                    {
                                                                        remainingLabel
                                                                    }
                                                                </span>
                                                            ) : null}
                                                            {isDashboardDateOverdue(
                                                                row.estimatedDelivery,
                                                            ) ? (
                                                                <span className="dashStatusOverdueBadge">
                                                                    Overdue
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    );
                                                })()}
                                            </td>
                                            <td className="is-text-center dashStatusColLastProcess">
                                                <SpkTableLastProcessCell
                                                    row={{
                                                        prosesTerakhir:
                                                            row.lastProcess ??
                                                            '',
                                                        prosesTerakhirDate:
                                                            row.lastProcessDate ??
                                                            '',
                                                        status: row.status,
                                                        processSlaHint:
                                                            formatProcessSlaRemainingLabel(
                                                                row.processSlaRemainingDays,
                                                                row.lastProcess,
                                                            ),
                                                        processSlaPastTarget:
                                                            typeof row.processSlaRemainingDays ===
                                                                'number' &&
                                                            row.processSlaRemainingDays <
                                                                0,
                                                    }}
                                                />
                                            </td>
                                            <td className="is-text-center">
                                                <SpkTableStatusCell
                                                    row={{
                                                        status: row.status,
                                                    }}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

WorkOrderDashboard.layout = {
    activeMenu: 'Work Order',
    pageTitle: 'Work Order',
};
