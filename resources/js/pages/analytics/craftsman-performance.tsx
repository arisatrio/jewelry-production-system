import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { useState } from 'react';
import { InProgressProcessBarChart } from '@/components/dashboard/in-progress-process-bar-chart';
import {
    CraftsmanPerformanceJobsDialog,
    type CraftsmanPerformanceJobsDialogTarget,
} from '@/pages/analytics/craftsman-performance-jobs-dialog';
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
    jobProcesses: Array<{
        key: string;
        label: string;
    }>;
    ranking: Array<{
        craftsmanId: number;
        name: string;
        position: string;
        labelTukang: string | null;
        jobCount: number;
        jobsByProcess: Record<string, number>;
        spkCount: number;
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
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} g`;
}

const UNLABELED_LABEL_TUKANG = 'Tanpa label tukang';

function resolveLabelTukangGroupKey(labelTukang: string | null | undefined): string {
    const trimmed = (labelTukang ?? '').trim();

    return trimmed !== '' ? trimmed : UNLABELED_LABEL_TUKANG;
}

function groupRankingByLabelTukang(
    ranking: CraftsmanPerformanceAnalytics['ranking'],
): Array<{
    labelTukang: string;
    rows: CraftsmanPerformanceAnalytics['ranking'];
}> {
    const groups = new Map<string, CraftsmanPerformanceAnalytics['ranking']>();

    for (const row of ranking) {
        const key = resolveLabelTukangGroupKey(row.labelTukang);

        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key)?.push(row);
    }

    return [...groups.entries()]
        .sort(([leftLabel], [rightLabel]) => {
            if (leftLabel === UNLABELED_LABEL_TUKANG) {
                return 1;
            }

            if (rightLabel === UNLABELED_LABEL_TUKANG) {
                return -1;
            }

            return leftLabel.localeCompare(rightLabel, 'id');
        })
        .map(([labelTukang, rows]) => ({ labelTukang, rows }));
}

function JobCountButton({
    count,
    onClick,
}: {
    count: number;
    onClick: () => void;
}) {
    const formatted = count.toLocaleString('id-ID');

    if (count <= 0) {
        return <>{formatted}</>;
    }

    return (
        <button
            type="button"
            className="dashStatusSpkLink tabular-nums"
            onClick={onClick}
        >
            {formatted}
        </button>
    );
}

export default function CraftsmanPerformanceDashboard({
    analytics,
    navigation,
    filters,
}: CraftsmanPerformanceDashboardProps) {
    const { summary, ranking, byProcess, jobProcesses, period } = analytics;
    const [jobsDialogTarget, setJobsDialogTarget] =
        useState<CraftsmanPerformanceJobsDialogTarget | null>(null);

    const openJobsDialog = (
        row: CraftsmanPerformanceAnalytics['ranking'][number],
        processKey: string | null,
        processLabel: string,
    ): void => {
        setJobsDialogTarget({
            craftsmanId: row.craftsmanId,
            craftsmanName: row.name,
            processKey,
            processLabel,
            month: filters.month,
        });
    };

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

    const rankingByLabelTukang = groupRankingByLabelTukang(ranking);
    const rankingTableColumnCount =
        2 +
        (jobProcesses.length > 0 ? jobProcesses.length + 1 : 1) +
        3;

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
                            Semua pengrajin (berjob di atas · 0 job di bawah) ·
                            dikelompokkan label tukang · {period.label}
                        </p>
                    </header>
                    {ranking.length === 0 ? (
                        <p className="dashEmpty">
                            Belum ada master pengrajin.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th rowSpan={2}>#</th>
                                        <th rowSpan={2}>Pengrajin</th>
                                        <th
                                            className="is-text-center"
                                            colSpan={
                                                jobProcesses.length > 0
                                                    ? jobProcesses.length + 1
                                                    : 1
                                            }
                                        >
                                            Job (Proses Pekerjaan)
                                        </th>
                                        <th rowSpan={2} className="is-text-center">
                                            Total SPK
                                        </th>
                                        <th rowSpan={2} className="is-text-center">
                                            Total Susut
                                        </th>
                                        <th rowSpan={2} className="is-text-center">
                                            Rata-rata Susut per proses
                                        </th>
                                    </tr>
                                    <tr>
                                        {jobProcesses.length > 0 ? (
                                            jobProcesses.map((process) => (
                                                <th
                                                    key={process.key}
                                                    className="is-text-center"
                                                >
                                                    {process.label}
                                                </th>
                                            ))
                                        ) : (
                                            <th className="is-text-center">
                                                Job (Proses Pekerjaan)
                                            </th>
                                        )}
                                        {jobProcesses.length > 0 ? (
                                            <th className="is-text-center">
                                                Total
                                            </th>
                                        ) : null}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rankingByLabelTukang.flatMap((group) => [
                                        <tr
                                            key={`label-${group.labelTukang}`}
                                            className="dashStatusTableGroupHeader"
                                        >
                                            <td colSpan={rankingTableColumnCount}>
                                                {group.labelTukang}
                                            </td>
                                        </tr>,
                                        ...group.rows.map((row, index) => (
                                            <tr
                                                key={`${group.labelTukang}-${row.craftsmanId}`}
                                            >
                                                <td>{index + 1}</td>
                                                <td>
                                                    <div>{row.name}</div>
                                                    <div className="text-muted-foreground text-sm">
                                                        {row.position}
                                                    </div>
                                                </td>
                                                {jobProcesses.length > 0 ? (
                                                    jobProcesses.map(
                                                        (process) => {
                                                            const count =
                                                                row
                                                                    .jobsByProcess[
                                                                    process.key
                                                                ] ?? 0;

                                                            return (
                                                                <td
                                                                    key={
                                                                        process.key
                                                                    }
                                                                    className="is-text-center text-center tabular-nums"
                                                                >
                                                                    <JobCountButton
                                                                        count={
                                                                            count
                                                                        }
                                                                        onClick={() =>
                                                                            openJobsDialog(
                                                                                row,
                                                                                process.key,
                                                                                process.label,
                                                                            )
                                                                        }
                                                                    />
                                                                </td>
                                                            );
                                                        },
                                                    )
                                                ) : (
                                                    <td className="is-text-center text-center tabular-nums">
                                                        <JobCountButton
                                                            count={row.jobCount}
                                                            onClick={() =>
                                                                openJobsDialog(
                                                                    row,
                                                                    null,
                                                                    'Semua proses',
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                )}
                                                {jobProcesses.length > 0 ? (
                                                    <td className="is-text-center text-center tabular-nums">
                                                        <JobCountButton
                                                            count={row.jobCount}
                                                            onClick={() =>
                                                                openJobsDialog(
                                                                    row,
                                                                    null,
                                                                    'Semua proses',
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                ) : null}
                                                <td className="is-text-center text-center tabular-nums">
                                                    {row.spkCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td className="is-text-center text-center tabular-nums">
                                                    {formatWeight(
                                                        row.totalShrink,
                                                    )}
                                                </td>
                                                <td className="is-text-center text-center tabular-nums">
                                                    {row.avgShrink !== null
                                                        ? formatWeight(
                                                              row.avgShrink,
                                                          )
                                                        : '—'}
                                                </td>
                                            </tr>
                                        )),
                                    ])}
                                </tbody>
                            </table>
                        </div>
                    )}
                </article>
            </div>

            <CraftsmanPerformanceJobsDialog
                target={jobsDialogTarget}
                onOpenChange={(open) => {
                    if (!open) {
                        setJobsDialogTarget(null);
                    }
                }}
            />
        </>
    );
}

CraftsmanPerformanceDashboard.layout = {
    activeMenu: 'Performance Pengrajin',
    pageTitle: 'Performance Pengrajin',
};
