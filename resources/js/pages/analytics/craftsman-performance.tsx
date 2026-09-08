import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { InProgressProcessBarChart } from '@/components/dashboard/in-progress-process-bar-chart';
import { craftsmanPerformance } from '@/routes/analytics';

export type CraftsmanPerformanceAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    summary: {
        activeCraftsmen: number;
        totalJobs: number;
        totalShrink: string;
        avgJobsPerCraftsman: string | null;
        topCraftsman: string | null;
        topCraftsmanJobs: number;
        heaviestShrinkCraftsman: string | null;
        heaviestShrink: string;
    };
    ranking: Array<{
        name: string;
        jobCount: number;
        totalShrink: string;
        avgShrink: string | null;
    }>;
    byProcess: Array<{
        process: string;
        jobCount: number;
        craftsmanCount: number;
        totalShrink: string;
    }>;
};

type CraftsmanPerformanceDashboardProps = {
    analytics: CraftsmanPerformanceAnalytics;
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

function formatWeight(value: string): string {
    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    })} g`;
}

export default function CraftsmanPerformanceDashboard({
    analytics,
    navigation,
}: CraftsmanPerformanceDashboardProps) {
    const { summary, ranking, byProcess, period } = analytics;

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            craftsmanPerformance.url({
                query: { month },
            }),
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const processChartItems = byProcess.map((row) => ({
        label: row.process,
        count: row.jobCount,
    }));

    const kpiCards: Array<{
        label: string;
        value: string;
        hint: string;
        className?: string;
    }> = [
        {
            label: 'Pengrajin Aktif',
            value: summary.activeCraftsmen.toLocaleString('id-ID'),
            hint: `Periode ${period.label}`,
        },
        {
            label: 'Total Job',
            value: summary.totalJobs.toLocaleString('id-ID'),
            hint:
                summary.avgJobsPerCraftsman !== null
                    ? `Rata-rata ${summary.avgJobsPerCraftsman} job / pengrajin`
                    : 'Belum ada job',
            className: 'is-progress',
        },
        {
            label: 'Total Susut',
            value: formatWeight(summary.totalShrink),
            hint: 'Agregat job periode ini',
        },
        {
            label: 'Top Output',
            value: summary.topCraftsmanJobs.toLocaleString('id-ID'),
            hint: summary.topCraftsman
                ? `Job terbanyak · ${summary.topCraftsman}`
                : 'Belum ada data',
            className: 'is-done',
        },
        {
            label: 'Susut Tertinggi',
            value: formatWeight(summary.heaviestShrink),
            hint: summary.heaviestShrinkCraftsman
                ? summary.heaviestShrinkCraftsman
                : 'Belum ada data',
            className: 'is-target-warning',
        },
        {
            label: 'Proses Tercatat',
            value: byProcess.length.toLocaleString('id-ID'),
            hint: 'Proses dengan job pengrajin',
        },
    ];

    return (
        <>
            <Head title="Dashboard Performance Pengrajin" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">
                            Dashboard Performance Pengrajin
                        </h1>
                        <p className="dashPageSubtitle">
                            Produktivitas pengrajin: jumlah job, susut, dan
                            distribusi beban per proses.
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
                    aria-label="Ringkasan performance pengrajin"
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
                            <h2 className="dashPanelTitle">Job per Proses</h2>
                            <p className="dashPanelMeta">
                                {period.label} · beban pengrajin per proses
                            </p>
                        </header>
                        <InProgressProcessBarChart items={processChartItems} />
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Detail Job per Proses
                            </h2>
                            <p className="dashPanelMeta">
                                Jumlah pengrajin & susut
                            </p>
                        </header>
                        {byProcess.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data job per proses.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Proses</th>
                                            <th>Job</th>
                                            <th>Pengrajin</th>
                                            <th>Susut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {byProcess.map((row) => (
                                            <tr key={row.process}>
                                                <td>{row.process}</td>
                                                <td>
                                                    {row.jobCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {row.craftsmanCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatWeight(
                                                        row.totalShrink,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </article>
                </div>

                <article className="dashPanel">
                    <header className="dashPanelHeader">
                        <h2 className="dashPanelTitle">
                            Ranking Pengrajin
                        </h2>
                        <p className="dashPanelMeta">
                            Top 20 berdasarkan jumlah job · {period.label}
                        </p>
                    </header>
                    {ranking.length === 0 ? (
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
                                        <th>Total Susut</th>
                                        <th>Avg Susut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ranking.map((row, index) => (
                                        <tr key={`${row.name}-${index}`}>
                                            <td>{index + 1}</td>
                                            <td>{row.name}</td>
                                            <td>
                                                {row.jobCount.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td>
                                                {formatWeight(row.totalShrink)}
                                            </td>
                                            <td>
                                                {row.avgShrink !== null
                                                    ? formatWeight(
                                                          row.avgShrink,
                                                      )
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </article>
            </div>
        </>
    );
}

CraftsmanPerformanceDashboard.layout = {
    activeMenu: 'Performance Pengrajin',
    pageTitle: 'Performance Pengrajin',
};
