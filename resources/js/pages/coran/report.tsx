import { Head, Link, router } from '@inertiajs/react';
import activityItemsIcon from '@ui5/webcomponents-icons/dist/activity-items.js';
import barChartIcon from '@ui5/webcomponents-icons/dist/bar-chart.js';
import documentTextIcon from '@ui5/webcomponents-icons/dist/document-text.js';
import inboxIcon from '@ui5/webcomponents-icons/dist/inbox.js';
import outboxIcon from '@ui5/webcomponents-icons/dist/outbox.js';
import qualityIssueIcon from '@ui5/webcomponents-icons/dist/quality-issue.js';
import trendDownIcon from '@ui5/webcomponents-icons/dist/trend-down.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DatePicker } from '@ui5/webcomponents-react/DatePicker';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { Table2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CoranMonthlyActivityTrendChart } from '@/components/coran/coran-monthly-activity-trend-chart';
import type { CoranMonthlyActivityPoint } from '@/components/coran/coran-monthly-activity-trend-chart';
import { CraftsmanShrinkProcessChart } from '@/components/finishing/craftsman-shrink-process-chart';
import {
    MonthlyShrinkTrendChart,
    monthlyShrinkPeriodLabel,
} from '@/components/finishing/monthly-shrink-trend-chart';
import type { MonthlyShrinkPoint } from '@/components/finishing/monthly-shrink-trend-chart';
import { ShrinkSharePieChart } from '@/components/finishing/shrink-share-pie-chart';
import type { ShrinkSharePieItem } from '@/components/finishing/shrink-share-pie-chart';
import { ShrinkByProcessBarChart } from '@/components/dashboard/shrink-by-process-bar-chart';
import { SpkProcessStatusCards } from '@/components/spk/spk-process-status-cards';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as coranIndex, report, show as coranShow } from '@/routes/coran';
import { show as spkShow } from '@/routes/spk';

type ReportAggregate = {
    documentCount: number;
    submitMaterial: string;
    spkWeight: string;
    resultMaterial: string;
    shrink: string;
    shrinkPercent: string | null;
    hasilPercent: string | null;
    sisaPercent: string | null;
    qcNotOkCount: number;
    qcDetailCount: number;
};

type ReportRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    craftsmanName: string | null;
    spkCount: number;
    spkNos: string[];
    submitMaterial: string | null;
    spkWeight: string | null;
    resultMaterial: string | null;
    shrink: string | null;
    shrinkPercent: string | null;
    hasilPercent: string | null;
    sisaPercent: string | null;
    qcNotOkCount: number;
    qcDetailCount: number;
};

type StatusBucket = {
    statusKey: string;
    label: string;
    documentCount: number;
};

type CoranMaterialBenchmarkShares = {
    hasilShare: number;
    sisaShare: number;
    shrinkShare: number;
    hasilPercent: string;
    sisaPercent: string;
    shrinkPercent: string;
    allocationTotalPercent: string;
};

type CoranMaterialBenchmark = {
    documentSampleCount: number;
    perDocument: CoranMaterialBenchmarkShares | null;
    aggregate: CoranMaterialBenchmarkShares | null;
    storedBalanceGapPercent: string | null;
};

type CoranReportProps = {
    filters: {
        craftsman: string;
        date_from: string;
        date_to: string;
    };
    craftsmanOptions: { value: string; label: string }[];
    summary: ReportAggregate & {
        craftsmanCount: number;
        spkCount: number;
        averageShrinkPerDocument: string | null;
        averageShrinkPercentPerDocument: string | null;
        averageShrinkPerSpk: string | null;
        averageShrinkPercentPerSpk: string | null;
    };
    monthlyShrink: {
        year: number;
        months: MonthlyShrinkPoint[];
    };
    monthlyActivity: {
        year: number;
        months: CoranMonthlyActivityPoint[];
    };
    byCraftsman: (ReportAggregate & { craftsmanName: string })[];
    byStatus: StatusBucket[];
    spkStatusCounts: {
        pending: number;
        inProgress: number;
        completed: number;
    };
    materialBenchmark: CoranMaterialBenchmark;
    rows: ReportRow[];
};

const TOP_SHRINK_LIMIT = 10;

function formatGram(value: number | string): string {
    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} g`;
}

function formatQcNokPercent(
    notOk: number,
    total: number,
): string | undefined {
    if (total <= 0) {
        return undefined;
    }

    return `${((notOk / total) * 100).toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })}%`;
}

function periodLabel(dateFrom: string, dateTo: string): string {
    if (dateFrom === dateTo) {
        return dateFrom;
    }

    return `${dateFrom} s/d ${dateTo}`;
}

export default function CoranReport({
    filters,
    craftsmanOptions,
    summary,
    monthlyShrink,
    monthlyActivity,
    byCraftsman,
    byStatus,
    spkStatusCounts,
    materialBenchmark,
    rows,
}: CoranReportProps) {
    const [draft, setDraft] = useState(filters);
    const [craftsmanRecapOpen, setCraftsmanRecapOpen] = useState(false);

    const hasDraftChanges =
        draft.craftsman !== filters.craftsman ||
        draft.date_from !== filters.date_from ||
        draft.date_to !== filters.date_to;

    const isDraftValid =
        draft.date_from !== '' &&
        draft.date_to !== '' &&
        draft.date_to >= draft.date_from;

    const applyFilters = () => {
        router.visit(
            report.url({
                query: {
                    craftsman: draft.craftsman || undefined,
                    date_from: draft.date_from,
                    date_to: draft.date_to,
                },
            }),
        );
    };

    const period = periodLabel(filters.date_from, filters.date_to);
    const trendPeriodLabel = monthlyShrinkPeriodLabel(
        monthlyShrink.year,
        monthlyShrink.months,
    );
    const activityPeriodLabel = monthlyShrinkPeriodLabel(
        monthlyActivity.year,
        monthlyActivity.months.map((month) => ({
            month: month.month,
            shrink: month.shrink,
            shrinkPercent: null,
            processCount: month.documentCount,
            includeInTrend: month.includeInTrend,
        })),
    );

    const craftsmanShrinkProcessItems = useMemo(
        () =>
            byCraftsman.map((row) => ({
                craftsmanName: row.craftsmanName,
                shrink: row.shrink,
                shrinkPercent: row.shrinkPercent,
                processCount: row.documentCount,
            })),
        [byCraftsman],
    );

    const materialShareItems = useMemo((): ShrinkSharePieItem[] => {
        const spkWeight = Number(summary.spkWeight);
        const resultMaterial = Number(summary.resultMaterial);
        const shrink = Number(summary.shrink);

        if (spkWeight + resultMaterial + shrink <= 0) {
            return [];
        }

        return [
            {
                label: 'Hasil cor (SPK)',
                shrink: spkWeight,
                recordCount: summary.documentCount,
            },
            {
                label: 'Sisa material',
                shrink: resultMaterial,
                recordCount: summary.documentCount,
            },
            {
                label: 'Susut',
                shrink: Math.max(shrink, 0),
                recordCount: summary.documentCount,
            },
        ];
    }, [summary]);

    const statusChartItems = useMemo(
        () =>
            byStatus
                .filter((row) => row.documentCount > 0)
                .map((row) => ({
                    process: row.label,
                    totalShrink: String(row.documentCount),
                    recordCount: row.documentCount,
                })),
        [byStatus],
    );

    const topShrinkDocuments = useMemo(
        () =>
            [...rows]
                .filter(
                    (row) =>
                        row.shrink !== null &&
                        !row.shrink.startsWith('+') &&
                        Number(row.shrink) > 0,
                )
                .sort(
                    (left, right) =>
                        Number(right.shrink) - Number(left.shrink),
                )
                .slice(0, TOP_SHRINK_LIMIT),
        [rows],
    );

    const allocationBreakdownValue = (
        shares: CoranMaterialBenchmark['perDocument'],
    ): string =>
        `Hasil ${shares.hasilPercent} · Sisa ${shares.sisaPercent} · Susut ${shares.shrinkPercent}`;

    const materialTrendStats = useMemo(() => {
        const perDocument = materialBenchmark.perDocument;
        const aggregate = materialBenchmark.aggregate;

        if (!perDocument && !aggregate) {
            return null;
        }

        const stats: Array<{
            label: string;
            value: string;
            hint: string;
            tone: 'high' | 'low' | 'avg';
        }> = [];

        const balanceNote =
            materialBenchmark.storedBalanceGapPercent !== null
                ? `Selisih data tersimpan vs bahan: ${materialBenchmark.storedBalanceGapPercent} (berat hasil/SK berbeda field susut)`
                : 'Susut dihitung: Bahan − Hasil − Sisa (total komposisi ≈ 100%)';

        if (perDocument) {
            stats.push({
                label: 'Rata alokasi per dokumen (% dari bahan)',
                value: allocationBreakdownValue(perDocument),
                hint: `${materialBenchmark.documentSampleCount.toLocaleString('id-ID')} dokumen · jumlah ${perDocument.allocationTotalPercent} · ${balanceNote}`,
                tone: 'avg',
            });
        }

        if (aggregate) {
            stats.push({
                label: 'Proporsi periode (% dari bahan)',
                value: allocationBreakdownValue(aggregate),
                hint: `${period} · jumlah ${aggregate.allocationTotalPercent} · ${balanceNote}`,
                tone: 'high',
            });
        }

        return stats.length > 0 ? stats : null;
    }, [
        materialBenchmark.aggregate,
        materialBenchmark.documentSampleCount,
        materialBenchmark.perDocument,
        materialBenchmark.storedBalanceGapPercent,
        period,
    ]);

    const kpiCards: Array<{
        label: string;
        value: string;
        subvalue?: string;
        hint: string;
        icon: string;
        tone: 'blue' | 'amber' | 'green' | 'orange' | 'red';
    }> = [
        {
            label: 'Dokumen Coran',
            value: summary.documentCount.toLocaleString('id-ID'),
            hint: `${summary.craftsmanCount.toLocaleString('id-ID')} pengrajin`,
            icon: activityItemsIcon,
            tone: 'blue' as const,
        },
        {
            label: 'SPK Unik',
            value: summary.spkCount.toLocaleString('id-ID'),
            hint: 'Pada periode laporan',
            icon: documentTextIcon,
            tone: 'blue' as const,
        },
        {
            label: 'Total Bahan',
            value: formatGram(summary.submitMaterial),
            hint: 'Rose + white + yellow gold',
            icon: inboxIcon,
            tone: 'amber' as const,
        },
        {
            label: 'Hasil Cor',
            value: formatGram(summary.spkWeight),
            subvalue: summary.hasilPercent ?? undefined,
            hint: 'Berat hasil cor SPK',
            icon: outboxIcon,
            tone: 'green' as const,
        },
        {
            label: 'Sisa Material',
            value: formatGram(summary.resultMaterial),
            subvalue: summary.sisaPercent ?? undefined,
            hint: 'Material kembali',
            icon: outboxIcon,
            tone: 'green' as const,
        },
        {
            label: 'Total Susut',
            value: formatGram(summary.shrink),
            subvalue: summary.shrinkPercent ?? undefined,
            hint: 'Susut / total bahan',
            icon: trendDownIcon,
            tone: 'orange' as const,
        },
        {
            label: 'Rata Susut / Dokumen',
            value: formatGram(summary.averageShrinkPerDocument ?? '0'),
            subvalue: summary.averageShrinkPercentPerDocument ?? undefined,
            hint: 'Dokumen PPIC & Done',
            icon: trendDownIcon,
            tone: 'orange' as const,
        },
        {
            label: 'QC NOK',
            value: summary.qcNotOkCount.toLocaleString('id-ID'),
            subvalue: formatQcNokPercent(
                summary.qcNotOkCount,
                summary.qcDetailCount,
            ),
            hint: `dari ${summary.qcDetailCount.toLocaleString('id-ID')} baris SPK`,
            icon: qualityIssueIcon,
            tone: 'red' as const,
        },
    ];

    return (
        <>
            <Head title="Laporan Coran" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Laporan Coran</h1>
                        <p className="dashPageSubtitle">
                            Dokumen Serahkan ke PPIC dan Done · {period}
                        </p>
                    </div>
                    <div
                        className="dashHeaderActions finishingReportFilters"
                        role="group"
                        aria-label="Filter laporan coran"
                    >
                        <Button
                            design="Transparent"
                            icon={barChartIcon}
                            onClick={() => router.visit(coranIndex.url())}
                        >
                            Daftar Coran
                        </Button>
                        <Select
                            accessibleName="Pengrajin"
                            className="finishingReportCraftsmanSelect"
                            onChange={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    craftsman:
                                        event.detail.selectedOption.value ?? '',
                                }))
                            }
                        >
                            <Option value="" selected={draft.craftsman === ''}>
                                Semua Pengrajin
                            </Option>
                            {craftsmanOptions.map((option) => (
                                <Option
                                    key={option.value}
                                    value={option.value}
                                    selected={draft.craftsman === option.value}
                                >
                                    {option.label}
                                </Option>
                            ))}
                        </Select>
                        <DatePicker
                            accessibleName="Tanggal dari"
                            value={draft.date_from}
                            valueFormat="yyyy-MM-dd"
                            displayFormat="dd/MM/yyyy"
                            onChange={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    date_from: event.detail.value ?? '',
                                }))
                            }
                        />
                        <DatePicker
                            accessibleName="Tanggal sampai"
                            value={draft.date_to}
                            valueFormat="yyyy-MM-dd"
                            displayFormat="dd/MM/yyyy"
                            valueState={isDraftValid ? 'None' : 'Negative'}
                            onChange={(event) =>
                                setDraft((current) => ({
                                    ...current,
                                    date_to: event.detail.value ?? '',
                                }))
                            }
                        />
                        <Button
                            design="Emphasized"
                            disabled={!hasDraftChanges || !isDraftValid}
                            onClick={applyFilters}
                        >
                            Terapkan
                        </Button>
                    </div>
                </header>

                <section
                    className="dashKpiGrid is-eight"
                    aria-label="Ringkasan laporan coran"
                >
                    {kpiCards.map((card) => (
                        <article
                            key={card.label}
                            className="dashKpiCard is-today finishingReportKpiCard"
                        >
                            <span
                                className={`finishingReportKpiIcon is-${card.tone}`}
                            >
                                <Icon name={card.icon} mode="Decorative" />
                            </span>
                            <span className="dashKpiLabel">{card.label}</span>
                            <div className="dashKpiValueRow">
                                <strong className="dashKpiValue">
                                    {card.value}
                                </strong>
                                {'subvalue' in card && card.subvalue ? (
                                    <span
                                        className={`dashKpiSubvalue finishingReportKpiSubvalue is-${card.tone}`}
                                    >
                                        {card.subvalue}
                                    </span>
                                ) : null}
                            </div>
                            <span className="dashKpiHint">{card.hint}</span>
                        </article>
                    ))}
                </section>

                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <SpkProcessStatusCards
                        counts={spkStatusCounts}
                        variant="alerts"
                        processLabel="Coran"
                        module="coran"
                    />
                </div>

                <article className="dashPanel dashProcessBarPanel finishingReportTrendPanel">
                    <header className="dashPanelHeader finishingReportTrendHeader">
                        <div className="dashPanelHeaderText">
                            <h2 className="dashPanelTitle">
                                Tren Coran &amp; Material
                            </h2>
                            <p className="dashPanelMeta">
                                {activityPeriodLabel} · Hasil · Sisa · Susut +
                                jumlah coran · pilih tampilan grafik · {period}
                            </p>
                        </div>
                        {materialTrendStats ? (
                            <div
                                className="finishingReportTrendStats"
                                aria-label="Ringkasan alokasi bahan coran"
                            >
                                {materialTrendStats.map((stat) => (
                                    <article
                                        key={stat.label}
                                        className={`finishingReportTrendStat is-${stat.tone}`}
                                    >
                                        <span className="finishingReportTrendStatLabel">
                                            {stat.label}
                                        </span>
                                        <strong className="finishingReportTrendStatValue is-allocation-breakdown">
                                            {stat.value}
                                        </strong>
                                        <span className="finishingReportTrendStatHint">
                                            {stat.hint}
                                        </span>
                                    </article>
                                ))}
                            </div>
                        ) : null}
                    </header>
                    <CoranMonthlyActivityTrendChart
                        months={monthlyActivity.months}
                    />
                </article>

                <article className="dashPanel dashProcessBarPanel finishingReportTrendPanel">
                    <header className="dashPanelHeader finishingReportTrendHeader">
                        <div className="dashPanelHeaderText">
                            <h2 className="dashPanelTitle">Tren Total Susut</h2>
                            <p className="dashPanelMeta">{trendPeriodLabel}</p>
                        </div>
                    </header>
                    <MonthlyShrinkTrendChart months={monthlyShrink.months} />
                </article>

                <div className="dashPieRow dashMaterialYieldRow finishingReportChartRow is-three">
                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader is-with-action">
                            <div className="dashPanelHeaderText">
                                <h2 className="dashPanelTitle">
                                    Susut per Pengrajin
                                </h2>
                                <p className="dashPanelMeta">{period}</p>
                            </div>
                            <button
                                type="button"
                                className="dashKpiFileBtn dashPanelDetailBtn"
                                aria-label="Rekap pengrajin"
                                onClick={() => setCraftsmanRecapOpen(true)}
                            >
                                <Table2 aria-hidden="true" />
                            </button>
                        </header>
                        <CraftsmanShrinkProcessChart
                            items={craftsmanShrinkProcessItems}
                            emptyMessage="Belum ada dokumen coran pada filter ini."
                        />
                    </article>

                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Alokasi Bahan (periode)
                            </h2>
                            <p className="dashPanelMeta">
                                Hasil cor, sisa, dan susut
                            </p>
                        </header>
                        <ShrinkSharePieChart
                            items={materialShareItems}
                            recordLabel="dokumen"
                            emptyMessage="Belum ada data material."
                        />
                    </article>

                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Status Dokumen (tanggal coran)
                            </h2>
                            <p className="dashPanelMeta">
                                Semua status dalam rentang filter
                            </p>
                        </header>
                        <ShrinkByProcessBarChart
                            items={statusChartItems}
                            valueSuffix=" dokumen"
                            showRecordCount={false}
                            emptyMessage="Belum ada dokumen pada rentang tanggal."
                        />
                    </article>
                </div>

                <article className="dashPanel">
                    <header className="dashPanelHeader">
                        <h2 className="dashPanelTitle">
                            Dokumen dengan Susut Terbesar
                        </h2>
                        <p className="dashPanelMeta">
                            Top {TOP_SHRINK_LIMIT} · {period}
                        </p>
                    </header>
                    {topShrinkDocuments.length === 0 ? (
                        <p className="dashEmpty">Belum ada data susut.</p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Doc</th>
                                        <th>Tanggal</th>
                                        <th>Pengrajin</th>
                                        <th>Bahan</th>
                                        <th>Susut</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {topShrinkDocuments.map((row, index) => (
                                        <tr key={row.id}>
                                            <td>{index + 1}</td>
                                            <td>
                                                <Link
                                                    href={coranShow.url(row.id)}
                                                    className="spkProduksiLink"
                                                >
                                                    {row.docNo ?? '—'}
                                                </Link>
                                            </td>
                                            <td>{row.transDate ?? '—'}</td>
                                            <td>
                                                {row.craftsmanName ?? '—'}
                                            </td>
                                            <td>
                                                {formatGram(
                                                    row.submitMaterial ?? 0,
                                                )}
                                            </td>
                                            <td>
                                                {formatGram(row.shrink ?? 0)}
                                            </td>
                                            <td>{row.shrinkPercent ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </article>

                <article className="dashPanel">
                    <header className="dashPanelHeader">
                        <h2 className="dashPanelTitle">Detail Dokumen</h2>
                        <p className="dashPanelMeta">
                            Hanya status PPIC &amp; Done · {period}
                        </p>
                    </header>
                    {rows.length === 0 ? (
                        <p className="dashEmpty">
                            Belum ada dokumen coran untuk filter ini.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th>Doc</th>
                                        <th>Tanggal</th>
                                        <th>Pengrajin</th>
                                        <th>SPK</th>
                                        <th>Bahan</th>
                                        <th>Hasil cor</th>
                                        <th>Sisa</th>
                                        <th>Susut</th>
                                        <th>% Susut</th>
                                        <th>QC NOK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                <Link
                                                    href={coranShow.url(row.id)}
                                                    className="spkProduksiLink"
                                                >
                                                    {row.docNo ?? '—'}
                                                </Link>
                                            </td>
                                            <td>{row.transDate ?? '—'}</td>
                                            <td>
                                                {row.craftsmanName ?? '—'}
                                            </td>
                                            <td>
                                                {row.spkNos[0] ? (
                                                    <Link
                                                        href={spkShow.url(
                                                            row.spkNos[0],
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {row.spkCount > 1
                                                            ? `${row.spkNos[0]} +${row.spkCount - 1}`
                                                            : row.spkNos[0]}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                {formatGram(
                                                    row.submitMaterial ?? 0,
                                                )}
                                            </td>
                                            <td>
                                                {formatGram(
                                                    row.spkWeight ?? 0,
                                                )}
                                            </td>
                                            <td>
                                                {formatGram(
                                                    row.resultMaterial ?? 0,
                                                )}
                                            </td>
                                            <td>
                                                {formatGram(row.shrink ?? 0)}
                                            </td>
                                            <td>{row.shrinkPercent ?? '—'}</td>
                                            <td>
                                                {row.qcNotOkCount > 0
                                                    ? row.qcNotOkCount
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </article>

                <Dialog
                    open={craftsmanRecapOpen}
                    onOpenChange={setCraftsmanRecapOpen}
                >
                    <DialogContent className="dashStatusModal sm:max-w-[min(72rem,calc(100vw-2rem))]">
                        <DialogHeader>
                            <DialogTitle>Rekap per Pengrajin</DialogTitle>
                            <DialogDescription>{period}</DialogDescription>
                        </DialogHeader>
                        {byCraftsman.length === 0 ? (
                            <p className="dashEmpty">Belum ada data.</p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Pengrajin</th>
                                            <th>Dokumen</th>
                                            <th>Bahan</th>
                                            <th>Hasil cor</th>
                                            <th>Sisa</th>
                                            <th>Susut</th>
                                            <th>% Susut</th>
                                            <th>QC NOK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {byCraftsman.map((row) => (
                                            <tr key={row.craftsmanName}>
                                                <td>{row.craftsmanName}</td>
                                                <td>
                                                    {row.documentCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatGram(
                                                        row.submitMaterial,
                                                    )}
                                                </td>
                                                <td>
                                                    {formatGram(row.spkWeight)}
                                                </td>
                                                <td>
                                                    {formatGram(
                                                        row.resultMaterial,
                                                    )}
                                                </td>
                                                <td>{formatGram(row.shrink)}</td>
                                                <td>
                                                    {row.shrinkPercent ?? '—'}
                                                </td>
                                                <td>
                                                    {row.qcNotOkCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
