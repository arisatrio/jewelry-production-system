import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { InProgressProcessBarChart } from '@/components/dashboard/in-progress-process-bar-chart';
import { shopFloor } from '@/routes/analytics';

export type ShopFloorAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    backlogYear: number;
    summary: {
        wipSpk: number;
        overdueWipSpk: number;
        completedThisMonth: number;
        bottleneckProcess: string | null;
        bottleneckCount: number;
        avgAgeDays: string | null;
        agedOver7Days: number;
        activeProcesses: number;
    };
    wipByProcess: Array<{
        label: string;
        count: number;
    }>;
    agingBuckets: Array<{
        label: string;
        count: number;
    }>;
    agingByProcess: Array<{
        process: string;
        count: number;
        avgAgeDays: string | null;
        maxAgeDays: string | null;
    }>;
    craftsmen: Array<{
        name: string;
        jobCount: number;
        totalShrink: string;
    }>;
};

type ShopFloorDashboardProps = {
    analytics: ShopFloorAnalytics;
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

function formatDays(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })} hari`;
}

export default function ShopFloorDashboard({
    analytics,
    navigation,
}: ShopFloorDashboardProps) {
    const {
        summary,
        wipByProcess,
        agingBuckets,
        agingByProcess,
        craftsmen,
        period,
        backlogYear,
    } = analytics;

    const agingBucketTotal = agingBuckets.reduce(
        (sum, bucket) => sum + bucket.count,
        0,
    );

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            shopFloor.url({
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

    const kpiCards: Array<{
        label: string;
        value: string;
        hint: string;
        className?: string;
    }> = [
        {
            label: 'WIP Lantai',
            value: summary.wipSpk.toLocaleString('id-ID'),
            hint: `In progress · backlog ${backlogYear}`,
            className: 'is-progress',
        },
        {
            label: 'WIP Overdue',
            value: summary.overdueWipSpk.toLocaleString('id-ID'),
            hint: 'Lewat estimasi delivery',
            className:
                summary.overdueWipSpk > 0 ? 'is-target-critical' : 'is-target-good',
        },
        {
            label: 'Selesai Bulan Ini',
            value: summary.completedThisMonth.toLocaleString('id-ID'),
            hint: `Done · ${period.label}`,
            className: 'is-done',
        },
        {
            label: 'Bottleneck',
            value:
                summary.bottleneckCount > 0
                    ? summary.bottleneckCount.toLocaleString('id-ID')
                    : '—',
            hint: summary.bottleneckProcess
                ? `Terbanyak di ${summary.bottleneckProcess}`
                : 'Tidak ada WIP',
            className: 'is-target-warning',
        },
        {
            label: 'Usia Antrian Rata-rata',
            value: formatDays(summary.avgAgeDays),
            hint: 'Sejak tanggal proses terakhir',
        },
        {
            label: 'WIP > 7 Hari',
            value: summary.agedOver7Days.toLocaleString('id-ID'),
            hint: `${summary.activeProcesses.toLocaleString('id-ID')} proses aktif`,
            className:
                summary.agedOver7Days > 0
                    ? 'is-target-critical'
                    : 'is-target-good',
        },
    ];

    return (
        <>
            <Head title="Dashboard Shop Floor" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Dashboard Shop Floor</h1>
                        <p className="dashPageSubtitle">
                            Kesehatan lantai produksi: WIP per proses, usia
                            antrian, bottleneck, dan beban pengrajin.
                        </p>
                    </div>
                    <div className="dashPeriodPager" aria-label="Navigasi bulan">
                        <Button
                            design="Transparent"
                            icon={navigationLeftIcon}
                            tooltip="Bulan sebelumnya"
                            onClick={() => goToMonth(navigation.previousMonth)}
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

                <section
                    className="dashKpiGrid is-six"
                    aria-label="Ringkasan shop floor"
                >
                    {kpiCards.map((card) => (
                        <article
                            key={card.label}
                            className={`dashKpiCard is-today${card.className ? ` ${card.className}` : ''}`}
                        >
                            <span className="dashKpiLabel">{card.label}</span>
                            <strong className="dashKpiValue">{card.value}</strong>
                            <span className="dashKpiHint">{card.hint}</span>
                        </article>
                    ))}
                </section>

                <div className="dashPieRow dashMaterialYieldRow">
                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">WIP per Proses</h2>
                            <p className="dashPanelMeta">
                                Backlog {backlogYear} ·{' '}
                                {summary.wipSpk.toLocaleString('id-ID')} SPK in
                                progress
                            </p>
                        </header>
                        <InProgressProcessBarChart items={wipByProcess} />
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">Usia Antrian WIP</h2>
                            <p className="dashPanelMeta">
                                Distribusi umur sejak proses terakhir
                            </p>
                        </header>
                        {agingBucketTotal === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data usia antrian WIP.
                            </p>
                        ) : (
                            <div className="dashMaterialStatGrid">
                                {agingBuckets.map((bucket) => {
                                    const percent =
                                        agingBucketTotal > 0
                                            ? (
                                                  (bucket.count /
                                                      agingBucketTotal) *
                                                  100
                                              ).toFixed(1)
                                            : '0.0';

                                    return (
                                        <div
                                            key={bucket.label}
                                            className="dashMaterialStat"
                                        >
                                            <span className="dashMaterialStatLabel">
                                                {bucket.label}
                                            </span>
                                            <strong className="dashMaterialStatValue">
                                                {bucket.count.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </strong>
                                            <span className="dashKpiHint">
                                                {percent}% dari sampel WIP
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </article>
                </div>

                <div className="dashPieRow dashMaterialYieldRow">
                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Aging per Proses
                            </h2>
                            <p className="dashPanelMeta">
                                Rata-rata & max usia antrian per bottleneck
                            </p>
                        </header>
                        {agingByProcess.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data aging per proses.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Proses</th>
                                            <th>WIP</th>
                                            <th>Avg Usia</th>
                                            <th>Max Usia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {agingByProcess.map((row) => (
                                            <tr key={row.process}>
                                                <td>{row.process}</td>
                                                <td>
                                                    {row.count.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatDays(row.avgAgeDays)}
                                                </td>
                                                <td>
                                                    {formatDays(row.maxAgeDays)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Beban Pengrajin
                            </h2>
                            <p className="dashPanelMeta">
                                Top 10 job · periode {period.label}
                            </p>
                        </header>
                        {craftsmen.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data pengrajin untuk periode ini.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Pengrajin</th>
                                            <th>Job</th>
                                            <th>Susut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {craftsmen.map((row, index) => (
                                            <tr key={`${row.name}-${index}`}>
                                                <td>{index + 1}</td>
                                                <td>{row.name}</td>
                                                <td>
                                                    {row.jobCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {Number(
                                                        row.totalShrink,
                                                    ).toLocaleString('id-ID', {
                                                        minimumFractionDigits: 3,
                                                        maximumFractionDigits: 3,
                                                    })}{' '}
                                                    g
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </article>
                </div>
            </div>
        </>
    );
}

ShopFloorDashboard.layout = {
    activeMenu: 'Shop Floor',
    pageTitle: 'Shop Floor',
};
