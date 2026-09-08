import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { ShrinkByProcessBarChart } from '@/components/dashboard/shrink-by-process-bar-chart';
import { materialYield } from '@/routes/analytics';

export type MaterialYieldAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    summary: {
        totalSpk: number;
        totalShrink: string;
        shrinkOkCount: number;
        shrinkNokCount: number;
        goldRequirement: string;
        goldIssued: string;
        goldReturned: string;
        goldUsed: string;
        goldVariance: string;
        stoneStartCrt: string;
        stoneEndCrt: string;
        stoneDifference: string;
        stoneLossPercent: string | null;
        avgYieldPercent: string | null;
        avgGoldYieldPercent: string | null;
    };
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
};

type MaterialYieldDashboardProps = {
    analytics: MaterialYieldAnalytics;
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

function formatWeight(value: string, unit = 'g'): string {
    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    })} ${unit}`;
}

function formatCrt(value: string): string {
    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    })} crt`;
}

function formatPercent(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}

function varianceToneClass(value: string): string {
    const amount = Number(value);

    if (Math.abs(amount) < 0.0005) {
        return '';
    }

    return amount > 0 ? 'is-target-critical' : 'is-target-good';
}

export default function MaterialYieldDashboard({
    analytics,
    navigation,
}: MaterialYieldDashboardProps) {
    const { summary, shrink, gold, stone, craftsmen, period } = analytics;
    const shrinkChecked = summary.shrinkOkCount + summary.shrinkNokCount;
    const shrinkOkPercent =
        shrinkChecked > 0
            ? ((summary.shrinkOkCount / shrinkChecked) * 100).toFixed(1)
            : null;

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            materialYield.url({
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
            label: 'Total Susut',
            value: formatWeight(summary.totalShrink),
            hint: `${summary.totalSpk.toLocaleString('id-ID')} SPK · ${period.label}`,
        },
        {
            label: 'Susut OK / NOK',
            value:
                shrinkChecked > 0
                    ? `${summary.shrinkOkCount.toLocaleString('id-ID')} / ${summary.shrinkNokCount.toLocaleString('id-ID')}`
                    : '—',
            hint:
                shrinkOkPercent !== null
                    ? `${shrinkOkPercent}% dalam toleransi finishing`
                    : 'Belum ada data toleransi finishing',
            className:
                summary.shrinkNokCount > 0 ? 'is-target-critical' : 'is-target-good',
        },
        {
            label: 'Yield Berat SPK',
            value: formatPercent(summary.avgYieldPercent),
            hint: 'Rata-rata last weight / gold weight (done)',
        },
        {
            label: 'Yield Emas vs Planning',
            value: formatPercent(summary.avgGoldYieldPercent),
            hint: 'Emas terpakai / kebutuhan planning',
        },
        {
            label: 'Selisih Emas vs Planning',
            value: formatWeight(summary.goldVariance),
            hint: 'Terpakai − kebutuhan (positif = over)',
            className: varianceToneClass(summary.goldVariance),
        },
        {
            label: 'Selisih Batu',
            value: formatCrt(summary.stoneDifference),
            hint:
                summary.stoneLossPercent !== null
                    ? `Loss ${formatPercent(summary.stoneLossPercent)} dari start`
                    : 'Start vs end carat',
            className: varianceToneClass(summary.stoneDifference),
        },
    ];

    return (
        <>
            <Head title="Dashboard Material & Yield" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">
                            Dashboard Material & Yield
                        </h1>
                        <p className="dashPageSubtitle">
                            Susut proses, aliran emas, selisih batu, dan yield
                            material untuk periode produksi.
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
                    aria-label="Ringkasan material & yield"
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
                            <h2 className="dashPanelTitle">Susut per Proses</h2>
                            <p className="dashPanelMeta">
                                {period.label} · total{' '}
                                {formatWeight(shrink.totalShrink)}
                            </p>
                        </header>
                        <ShrinkByProcessBarChart items={shrink.byProcess} />
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">Aliran Emas</h2>
                            <p className="dashPanelMeta">
                                Serah / kembali / terpakai · {period.label}
                            </p>
                        </header>
                        <div className="dashMaterialStatGrid">
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Kebutuhan Planning
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatWeight(summary.goldRequirement)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Serah (Issued)
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatWeight(gold.issued)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Kembali (Returned)
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatWeight(gold.returned)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Terpakai (Used)
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatWeight(gold.used)}
                                </strong>
                            </div>
                        </div>
                    </article>
                </div>

                <div className="dashPieRow dashMaterialYieldRow">
                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">Ringkasan Batu</h2>
                            <p className="dashPanelMeta">
                                Start vs end carat · {period.label}
                            </p>
                        </header>
                        <div className="dashMaterialStatGrid">
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Start
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatCrt(stone.startCrt)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">End</span>
                                <strong className="dashMaterialStatValue">
                                    {formatCrt(stone.endCrt)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Selisih
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatCrt(stone.difference)}
                                </strong>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Loss %
                                </span>
                                <strong className="dashMaterialStatValue">
                                    {formatPercent(summary.stoneLossPercent)}
                                </strong>
                            </div>
                        </div>
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Detail Susut per Proses
                            </h2>
                            <p className="dashPanelMeta">
                                Bobot, rata-rata %, dan NOK toleransi
                            </p>
                        </header>
                        {shrink.byProcess.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data susut per proses.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Proses</th>
                                            <th>Susut (g)</th>
                                            <th>Record</th>
                                            <th>Avg %</th>
                                            <th>NOK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shrink.byProcess.map((row) => (
                                            <tr key={row.process}>
                                                <td>{row.process}</td>
                                                <td>
                                                    {formatWeight(
                                                        row.totalShrink,
                                                    )}
                                                </td>
                                                <td>
                                                    {row.recordCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatPercent(
                                                        row.avgPercent,
                                                    )}
                                                </td>
                                                <td>
                                                    {row.nokCount !== null
                                                        ? row.nokCount.toLocaleString(
                                                              'id-ID',
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

                <article className="dashPanel">
                    <header className="dashPanelHeader">
                        <h2 className="dashPanelTitle">
                            Ranking Pengrajin (Susut)
                        </h2>
                        <p className="dashPanelMeta">
                            Top 10 berdasarkan jumlah job · {period.label}
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
                                        <th>Total Susut</th>
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
                                                {formatWeight(row.totalShrink)}
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

MaterialYieldDashboard.layout = {
    activeMenu: 'Material & Yield',
    pageTitle: 'Material & Yield',
};
