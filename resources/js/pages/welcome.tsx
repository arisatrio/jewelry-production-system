import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Eye } from 'lucide-react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DistributionPieChart } from '@/components/dashboard/distribution-pie-chart';
import { ForecastClusteredBarChart } from '@/components/dashboard/forecast-clustered-bar-chart';
import { InProgressProcessBarChart } from '@/components/dashboard/in-progress-process-bar-chart';
import { home } from '@/routes';
import { show as spkShow } from '@/routes/spk';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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

export type DashboardStatusKey =
    | 'draft'
    | 'confirmed'
    | 'inProgress'
    | 'overdue'
    | 'done';

export type DashboardTodayKey =
    | 'todayCreated'
    | 'todayInProcess'
    | 'todayTarget'
    | 'monthOverdue';

export type DashboardListKey = DashboardStatusKey | DashboardTodayKey;

export type DashboardAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    summary: {
        totalSpk: number;
        draftSpk: number;
        confirmedSpk: number;
        inProgressSpk: number;
        doneSpk: number;
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
    };
    statusLists: Record<DashboardStatusKey, DashboardStatusSpkItem[]>;
    todayLists: Record<DashboardTodayKey, DashboardStatusSpkItem[]>;
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

type WelcomeProps = {
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

export default function Welcome({ analytics, navigation }: WelcomeProps) {
    const {
        summary,
        statusLists,
        todayLists,
        forecast,
        today,
        period,
        inProgressByProcess,
    } = analytics;
    const [openList, setOpenList] = useState<DashboardListKey | null>(null);

    const statusCards: Array<{
        key: DashboardStatusKey;
        label: string;
        hint: string;
        count: number;
        className: string;
    }> = [
        {
            key: 'draft',
            label: 'Menunggu Approval Manager Produksi',
            hint: 'Belum Approved',
            count: summary.draftSpk,
            className: 'is-draft',
        },
        {
            key: 'confirmed',
            label: 'Approved by Manager Produksi',
            hint: 'belum diproses',
            count: summary.confirmedSpk,
            className: 'is-confirmed',
        },
        {
            key: 'inProgress',
            label: 'In Progress',
            hint: 'Sedang dikerjakan',
            count: summary.inProgressSpk,
            className: 'is-progress',
        },
        {
            key: 'overdue',
            label: 'SPK Overdue Estimasi',
            hint: 'Lewat estimasi delivery',
            count: summary.overdueSpk,
            className: 'is-overdue',
        },
        {
            key: 'done',
            label: 'Done',
            hint: 'Poles BJ completed / Serahkan ke JB',
            count: summary.doneSpk,
            className: 'is-done',
        },
    ];

    const todayCards: Array<{
        key: DashboardTodayKey;
        label: string;
        hint: string;
        count: number;
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
            hint: `In progress · di-update ${today.label}`,
            count: today.inProcessSpk,
        },
        {
            key: 'todayTarget',
            label: 'Target SPK Hari Ini',
            hint: `${today.label} · selesai ${today.targetDoneSpk.toLocaleString('id-ID')} · belum ${today.targetPendingSpk.toLocaleString('id-ID')}${today.targetQty > 0 ? ` · qty ${today.targetQty.toLocaleString('id-ID')}` : ''}`,
            count: today.targetSpk,
        },
        {
            key: 'monthOverdue',
            label: 'SPK Overdue Bulan Ini',
            hint: `Lewat estimasi · ${period.label}`,
            count: today.overdueSpk,
            className: 'is-overdue',
        },
    ];

    const openListMeta =
        statusCards.find((card) => card.key === openList) ??
        todayCards.find((card) => card.key === openList) ??
        null;
    const openListRows =
        openList === null
            ? []
            : openList in statusLists
              ? (statusLists[openList as DashboardStatusKey] ?? [])
              : (todayLists[openList as DashboardTodayKey] ?? []);
    const openListPeriodLabel =
        openList !== null &&
        openList in todayLists &&
        openList !== 'monthOverdue'
            ? today.label
            : period.label;

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

            <div className="dashShell">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Dashboard Produksi</h1>
                    </div>
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
                </header>


                <div className="dashHeroGrid">
                    <article className="dashPanel dashBacklogPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">Backlog SPK</h2>
                            <p className="dashPanelMeta">
                                All time · belum selesai / overdue
                            </p>
                        </header>
                        <section
                            className="dashStatusStack"
                            aria-label="Backlog SPK"
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
                                <article
                                    key={card.key}
                                    className={`dashKpiCard is-today${card.className ? ` ${card.className}` : ''}`}
                                >
                                    <span className="dashKpiLabel">
                                        {card.label}
                                    </span>
                                    <strong className="dashKpiValue">
                                        {card.count.toLocaleString('id-ID')}
                                    </strong>
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
                            ))}
                        </section>

                        <div className="dashPieRow">
                            <div className="dashPieColumn">
                                <article className="dashPanel dashPiePanel is-compact">
                                    <header className="dashPanelHeader">
                                        <h2 className="dashPanelTitle">
                                            Tipe Produksi
                                        </h2>
                                        <p className="dashPanelMeta">
                                            Dibuat atau estimasi selesai{' '}
                                            {period.label}
                                        </p>
                                    </header>
                                    <DistributionPieChart
                                        items={analytics.productionTypes}
                                        legendMetric="spk"
                                    />
                                </article>

                                <article className="dashPanel dashProcessBarPanel">
                                    <header className="dashPanelHeader">
                                        <h2 className="dashPanelTitle">
                                          Bottleneck Proses SPK
                                        </h2>
                                        <p className="dashPanelMeta">
                                            Dibuat atau estimasi selesai{' '}
                                            {period.label} ·{' '}
                                            {inProgressByProcess
                                                .reduce(
                                                    (sum, row) =>
                                                        sum + row.count,
                                                    0,
                                                )
                                                .toLocaleString('id-ID')}{' '}
                                            SPK in progress
                                        </p>
                                    </header>
                                    <InProgressProcessBarChart
                                        items={inProgressByProcess}
                                    />
                                </article>
                            </div>

                            <div className="dashForecastColumn">
                                <article className="dashPanel dashPiePanel is-compact">
                                    <header className="dashPanelHeader">
                                        <h2 className="dashPanelTitle">
                                            Item Produksi
                                        </h2>
                                        <p className="dashPanelMeta">
                                            Dibuat atau estimasi selesai{' '}
                                            {period.label}
                                        </p>
                                    </header>
                                    <DistributionPieChart
                                        items={analytics.itemDistribution}
                                        legendMetric="spk"
                                    />
                                </article>

                                <article className="dashPanel dashForecastPanel">
                                    <header className="dashPanelHeader">
                                        <h2 className="dashPanelTitle">
                                            Planning Estimasi Produksi Vs
                                            Realisasi Produksi
                                        </h2>
                                        <p className="dashPanelMeta">
                                            Estimasi selesai {period.label} ·{' '}
                                            {forecast.spkCount.toLocaleString(
                                                'id-ID',
                                            )}{' '}
                                            SPK · cluster per item
                                        </p>
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
                <DialogContent className="dashStatusModal">
                    <DialogHeader>
                        <DialogTitle>
                            {openListMeta?.label ?? 'Daftar SPK'}
                        </DialogTitle>
                        <DialogDescription>
                            {openListPeriodLabel} ·{' '}
                            {(openListMeta?.count ?? 0).toLocaleString(
                                'id-ID',
                            )}{' '}
                            SPK
                            {openListRows.length < (openListMeta?.count ?? 0)
                                ? ` · menampilkan ${openListRows.length.toLocaleString('id-ID')} terbaru`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    {openListRows.length === 0 ? (
                        <p className="dashEmpty">
                            Tidak ada SPK untuk kategori ini.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th>SPK No</th>
                                        <th>Tipe</th>
                                        <th>Customer</th>
                                        <th>Item</th>
                                        <th>Order</th>
                                        <th>Est. Delivery</th>
                                        <th>Proses terakhir</th>
                                        <th>Tanggal proses terakhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {openListRows.map((row) => (
                                        <tr key={row.spkNo}>
                                            <td>
                                                {row.spkNo !== '-' ? (
                                                    <button
                                                        type="button"
                                                        className="dashStatusSpkLink"
                                                        onClick={() =>
                                                            router.visit(
                                                                spkShow.url(
                                                                    row.spkNo,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {row.spkNo}
                                                    </button>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{row.type}</td>
                                            <td>{row.customer}</td>
                                            <td>{row.item}</td>
                                            <td>{row.orderDate ?? '—'}</td>
                                            <td>
                                                {row.estimatedDelivery ?? '—'}
                                            </td>
                                            <td>{row.lastProcess ?? '—'}</td>
                                            <td>
                                                {row.lastProcessDate ?? '—'}
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

Welcome.layout = {
    activeMenu: 'Dashboard',
    pageTitle: 'Dashboard',
};
