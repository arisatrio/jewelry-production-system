import { Head, Link, router } from '@inertiajs/react';
import activityItemsIcon from '@ui5/webcomponents-icons/dist/activity-items.js';
import documentTextIcon from '@ui5/webcomponents-icons/dist/document-text.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
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
import { ShrinkByProcessBarChart } from '@/components/dashboard/shrink-by-process-bar-chart';
import { CraftsmanShrinkProcessChart } from '@/components/finishing/craftsman-shrink-process-chart';
import type { CraftsmanShrinkProcessItem } from '@/components/finishing/craftsman-shrink-process-chart';
import {
    MonthlyShrinkTrendChart,
    monthlyShrinkMonthLabel,
    monthlyShrinkPeriodLabel,
} from '@/components/finishing/monthly-shrink-trend-chart';
import type { MonthlyShrinkPoint } from '@/components/finishing/monthly-shrink-trend-chart';
import { ShrinkSharePieChart } from '@/components/finishing/shrink-share-pie-chart';
import type { ShrinkSharePieItem } from '@/components/finishing/shrink-share-pie-chart';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    exportMethod as exportReport,
    show as finishingShow,
    report,
} from '@/routes/finishing';
import { show as spkShow } from '@/routes/spk';

type ReportAggregate = {
    documentCount: number;
    startWeight: string;
    submitMaterial: string;
    finishWeight: string;
    resultMaterial: string;
    shrink: string;
    shrinkPercent: string | null;
    qcNotOkCount: number;
    workMinutes: number;
    workMinutesCount: number;
};

type ReportRow = {
    id: number;
    docNo: string | null;
    sendCraftsmanDate: string | null;
    receivedCraftsmanDate: string | null;
    craftsmanName: string | null;
    spkNo: string | null;
    item: string | null;
    processName: string | null;
    itemCategory: string | null;
    skuCategory: string | null;
    startWeight: string | null;
    submitMaterial: string | null;
    finishWeight: string | null;
    resultMaterial: string | null;
    shrink: string | null;
    shrinkPercent: string | null;
    qcStatus: string | null;
    qcNotes: string | null;
    workDuration: string | null;
    notes: string | null;
};

type ReportFilters = {
    craftsman: string;
    date_from: string;
    date_to: string;
};

type FinishingReportProps = {
    filters: ReportFilters;
    craftsmanOptions: { value: string; label: string }[];
    summary: ReportAggregate & {
        craftsmanCount: number;
        spkCount: number;
        averageShrinkPerProcess: string | null;
        averageShrinkPercentPerProcess: string | null;
        averageShrinkPerSpk: string | null;
        averageShrinkPercentPerSpk: string | null;
    };
    monthlyShrink: {
        year: number;
        months: MonthlyShrinkPoint[];
    };
    byCraftsman: (ReportAggregate & { craftsmanName: string })[];
    bySkuCategory: (ReportAggregate & { skuCategory: string })[];
    rows: ReportRow[];
};

const CRAFTSMAN_CHART_MIN_HEIGHT_REM = 30;

const CRAFTSMAN_CHART_BAR_HEIGHT_REM = 2.5;

const CRAFTSMAN_CHART_CHROME_HEIGHT_REM = 8;

const TOP_SHRINK_DOCUMENT_LIMIT = 10;

const MONTH_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

function formatDateDisplay(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '—';
    }

    const [datePart, timePart] = trimmed.split(/\s+/u);
    const [year, month, day] = (datePart ?? '').split('-');
    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!year || !day || !monthLabel) {
        return trimmed;
    }

    const dateLabel = `${day}-${monthLabel}-${year}`;

    return timePart ? `${dateLabel} ${timePart.slice(0, 5)}` : dateLabel;
}

function formatSignedGram(value: number): string {
    const formatted = Math.abs(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    if (value < -0.0005) {
        return `+${formatted} g`;
    }

    return `${formatted} g`;
}

function formatGram(value: number | string | null): string {
    if (value === null) {
        return '—';
    }

    const text = String(value);
    const isGain = text.startsWith('+');
    const formatted = Math.abs(Number(text)).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return `${isGain ? '+' : ''}${formatted} g`;
}

function formatMinutesDuration(totalMinutes: number): string {
    const roundedMinutes = Math.round(totalMinutes);

    if (roundedMinutes <= 0) {
        return '< 1 menit';
    }

    const days = Math.floor(roundedMinutes / 1440);
    const hours = Math.floor((roundedMinutes % 1440) / 60);
    const minutes = roundedMinutes % 60;

    return [
        days > 0 ? `${days} hari` : null,
        hours > 0 ? `${hours} jam` : null,
        minutes > 0 ? `${minutes} menit` : null,
    ]
        .filter((part) => part !== null)
        .join(' ');
}

function formatQcRejectPercent(aggregate: ReportAggregate): string {
    if (aggregate.documentCount === 0) {
        return '—';
    }

    return `${((aggregate.qcNotOkCount / aggregate.documentCount) * 100).toFixed(2)}%`;
}

function FinishingDocLink({ row }: { row: ReportRow }) {
    return (
        <Link href={finishingShow.url(row.id)} className="spkProduksiLink">
            {row.docNo ?? '—'}
        </Link>
    );
}

function SpkLink({ spkNo }: { spkNo: string | null }) {
    if (!spkNo) {
        return <>—</>;
    }

    return (
        <Link href={spkShow.url(spkNo)} className="spkProduksiLink">
            {spkNo}
        </Link>
    );
}

type SpkShrinkSummary = {
    spkNo: string;
    item: string | null;
    skuCategory: string | null;
    itemCategory: string | null;
    rows: ReportRow[];
    goldIn: number;
    goldOut: number;
    shrink: number;
    shrinkPercent: number | null;
    qcRejectCount: number;
    craftsmanNames: string[];
};

function parseSignedShrink(value: string | null): number {
    if (value === null) {
        return 0;
    }

    return value.startsWith('+') ? -Number(value.slice(1)) : Number(value);
}

function findTopShrinkSpk(rows: ReportRow[]): SpkShrinkSummary | null {
    const rowsBySpk = new Map<string, ReportRow[]>();

    for (const row of rows) {
        if (row.spkNo) {
            rowsBySpk.set(row.spkNo, [
                ...(rowsBySpk.get(row.spkNo) ?? []),
                row,
            ]);
        }
    }

    let top: SpkShrinkSummary | null = null;

    for (const [spkNo, spkRows] of rowsBySpk) {
        const shrink = spkRows.reduce(
            (sum, row) => sum + parseSignedShrink(row.shrink),
            0,
        );

        if (shrink <= 0 || (top !== null && shrink <= top.shrink)) {
            continue;
        }

        const goldIn = spkRows.reduce(
            (sum, row) =>
                sum +
                Number(row.startWeight ?? 0) +
                Number(row.submitMaterial ?? 0),
            0,
        );
        const goldOut = spkRows.reduce(
            (sum, row) =>
                sum +
                Number(row.finishWeight ?? 0) +
                Number(row.resultMaterial ?? 0),
            0,
        );
        const firstRow = spkRows[0];

        top = {
            spkNo,
            item: firstRow?.item ?? null,
            skuCategory: firstRow?.skuCategory ?? null,
            itemCategory: firstRow?.itemCategory ?? null,
            rows: spkRows,
            goldIn,
            goldOut,
            shrink,
            shrinkPercent: goldIn > 0 ? (shrink / goldIn) * 100 : null,
            qcRejectCount: spkRows.filter((row) => row.qcStatus === 'NOT OK')
                .length,
            craftsmanNames: [
                ...new Set(
                    spkRows
                        .map((row) => row.craftsmanName)
                        .filter((name): name is string => name !== null),
                ),
            ],
        };
    }

    return top;
}

function TopShrinkSpkCard({
    summary,
    periodLabel,
}: {
    summary: SpkShrinkSummary | null;
    periodLabel: string;
}) {
    return (
        <article className="dashPanel finishingTopSpkCard">
            <header className="dashPanelHeader">
                <h2 className="dashPanelTitle">SPK dengan Susut Terbesar</h2>
                <p className="dashPanelMeta">
                    Total susut semua proses finishing dalam satu SPK ·{' '}
                    {periodLabel}
                </p>
            </header>

            {summary === null ? (
                <p className="dashEmpty">Belum ada SPK dengan susut.</p>
            ) : (
                <>
                    <div className="finishingTopSpkSummary">
                        <div className="finishingTopSpkIdentity">
                            <Link
                                href={spkShow.url(summary.spkNo)}
                                className="finishingTopSpkNo"
                            >
                                {summary.spkNo}
                            </Link>
                            <span className="finishingTopSpkItem">
                                {summary.item ?? '—'}
                            </span>
                            <div className="finishingTopSpkTags">
                                {summary.skuCategory ? (
                                    <span className="finishingTopSpkTag">
                                        {summary.skuCategory}
                                    </span>
                                ) : null}
                                {summary.itemCategory ? (
                                    <span className="finishingTopSpkTag is-muted">
                                        {summary.itemCategory}
                                    </span>
                                ) : null}
                            </div>
                        </div>

                        <div className="dashMaterialStatGrid finishingTopSpkStats">
                            <div className="dashMaterialStat is-shrink">
                                <span className="dashMaterialStatLabel">
                                    Total Susut
                                </span>
                                <span className="dashMaterialStatValue">
                                    {formatGram(summary.shrink)}
                                    {summary.shrinkPercent !== null
                                        ? ` · ${summary.shrinkPercent.toFixed(2)}%`
                                        : ''}
                                </span>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Berat Masuk
                                </span>
                                <span className="dashMaterialStatValue">
                                    {formatGram(summary.goldIn)}
                                </span>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Berat Keluar
                                </span>
                                <span className="dashMaterialStatValue">
                                    {formatGram(summary.goldOut)}
                                </span>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Proses Finishing
                                </span>
                                <span className="dashMaterialStatValue">
                                    {summary.rows.length.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    Pengrajin
                                </span>
                                <span className="dashMaterialStatValue">
                                    {summary.craftsmanNames.length > 0
                                        ? summary.craftsmanNames.join(', ')
                                        : '—'}
                                </span>
                            </div>
                            <div className="dashMaterialStat">
                                <span className="dashMaterialStatLabel">
                                    QC Reject
                                </span>
                                <span className="dashMaterialStatValue">
                                    {summary.qcRejectCount.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="dashStatusTableWrap">
                        <table className="dashStatusTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pengrajin</th>
                                    <th>Tanggal Serah</th>
                                    <th>Tanggal Terima</th>
                                    <th>Waktu Pengerjaan</th>
                                    <th>Berat Awal</th>
                                    <th>Bahan</th>
                                    <th>Berat Akhir</th>
                                    <th>Bahan Sisa</th>
                                    <th>Susut</th>
                                    <th>% Susut</th>
                                    <th>QC</th>
                                    <th>Catatan QC</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {summary.rows.map((row, index) => (
                                    <tr key={`${row.docNo}-${index}`}>
                                        <td>
                                            <FinishingDocLink row={row} />
                                        </td>
                                        <td>{row.craftsmanName ?? '—'}</td>
                                        <td>
                                            {formatDateDisplay(
                                                row.sendCraftsmanDate,
                                            )}
                                        </td>
                                        <td>
                                            {formatDateDisplay(
                                                row.receivedCraftsmanDate,
                                            )}
                                        </td>
                                        <td>{row.workDuration ?? '—'}</td>
                                        <td>{formatGram(row.startWeight)}</td>
                                        <td>
                                            {formatGram(row.submitMaterial)}
                                        </td>
                                        <td>{formatGram(row.finishWeight)}</td>
                                        <td>
                                            {formatGram(row.resultMaterial)}
                                        </td>
                                        <td>{formatGram(row.shrink)}</td>
                                        <td>{row.shrinkPercent ?? '—'}</td>
                                        <td>{row.qcStatus ?? '—'}</td>
                                        <td className="finishingReportWrapCell">
                                            {row.qcNotes ?? '—'}
                                        </td>
                                        <td className="finishingReportWrapCell">
                                            {row.notes ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </article>
    );
}

function buildReportQuery(filters: ReportFilters) {
    return {
        craftsman: filters.craftsman || undefined,
        date_from: filters.date_from,
        date_to: filters.date_to,
    };
}

export default function FinishingReport({
    filters,
    craftsmanOptions,
    summary,
    monthlyShrink,
    byCraftsman,
    bySkuCategory,
    rows,
}: FinishingReportProps) {
    const [draft, setDraft] = useState<ReportFilters>(filters);
    const [prevFilters, setPrevFilters] = useState(filters);
    const [craftsmanRecapOpen, setCraftsmanRecapOpen] = useState(false);

    if (prevFilters !== filters) {
        setPrevFilters(filters);
        setDraft(filters);
    }

    const hasDraftChanges =
        draft.craftsman !== filters.craftsman ||
        draft.date_from !== filters.date_from ||
        draft.date_to !== filters.date_to;
    const isDraftValid =
        draft.date_from !== '' &&
        draft.date_to !== '' &&
        draft.date_to >= draft.date_from;

    const applyFilters = () => {
        router.get(
            report.url({ query: buildReportQuery(draft) }),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const downloadExcel = () => {
        window.location.href = exportReport.url({
            query: buildReportQuery(filters),
        });
    };

    const periodLabel = `${formatDateDisplay(filters.date_from)} s/d ${formatDateDisplay(filters.date_to)}`;
    const trendCraftsmanLabel =
        filters.craftsman === ''
            ? 'Semua pengrajin'
            : (craftsmanOptions.find(
                  (option) => option.value === filters.craftsman,
              )?.label ?? 'Pengrajin');
    const trendPeriodLabel = monthlyShrinkPeriodLabel(
        monthlyShrink.year,
        monthlyShrink.months,
    );
    const trendHighlights = useMemo(() => {
        const elapsed = monthlyShrink.months
            .filter((month) => month.includeInTrend)
            .map((month) => ({
                ...month,
                shrinkValue: Number(month.shrink),
            }))
            .filter((month) => Math.abs(month.shrinkValue) >= 0.0005);

        if (elapsed.length === 0) {
            return null;
        }

        const highest = elapsed.reduce((best, month) =>
            month.shrinkValue >= best.shrinkValue ? month : best,
        );
        const lowest = elapsed.reduce((best, month) =>
            month.shrinkValue <= best.shrinkValue ? month : best,
        );
        const average =
            elapsed.reduce((sum, month) => sum + month.shrinkValue, 0) /
            elapsed.length;
        const firstLabel = monthlyShrinkMonthLabel(elapsed[0].month);
        const lastLabel = monthlyShrinkMonthLabel(
            elapsed[elapsed.length - 1].month,
        );

        const averageProcessCount =
            elapsed.reduce((sum, month) => sum + month.processCount, 0) /
            elapsed.length;
        const averageProcessLabel = `${averageProcessCount.toLocaleString('id-ID', { maximumFractionDigits: 1 })} proses`;

        const detail = (month: (typeof elapsed)[number]): string => {
            const grams = formatSignedGram(month.shrinkValue);
            const processes = `${month.processCount.toLocaleString('id-ID')} proses`;

            return month.shrinkPercent
                ? `${grams} · ${month.shrinkPercent} · ${processes}`
                : `${grams} · ${processes}`;
        };

        return [
            {
                label: 'Bulan tertinggi',
                value: monthlyShrinkMonthLabel(highest.month),
                hint: detail(highest),
                tone: 'high',
            },
            {
                label: 'Bulan terendah',
                value: monthlyShrinkMonthLabel(lowest.month),
                hint: detail(lowest),
                tone: 'low',
            },
            {
                label: 'Rata-rata / bulan',
                value: formatSignedGram(average),
                hint:
                    firstLabel === lastLabel
                        ? `${firstLabel} · ${averageProcessLabel}`
                        : `${firstLabel}–${lastLabel} · ${averageProcessLabel}`,
                tone: 'avg',
            },
        ] as const;
    }, [monthlyShrink.months]);

    const craftsmanShrinkProcessItems = useMemo<CraftsmanShrinkProcessItem[]>(
        () =>
            byCraftsman
                .filter(
                    (row) =>
                        !row.shrink.startsWith('+') && Number(row.shrink) > 0,
                )
                .sort(
                    (left, right) => Number(right.shrink) - Number(left.shrink),
                )
                .map((row) => ({
                    craftsmanName: row.craftsmanName,
                    shrink: row.shrink,
                    shrinkPercent: row.shrinkPercent,
                    processCount: row.documentCount,
                })),
        [byCraftsman],
    );

    const craftsmanWorkDurations = useMemo(
        () =>
            [...byCraftsman].sort(
                (left, right) => right.workMinutes - left.workMinutes,
            ),
        [byCraftsman],
    );

    const craftsmanChartRowHeight = `${Math.max(
        CRAFTSMAN_CHART_MIN_HEIGHT_REM,
        craftsmanShrinkProcessItems.length * CRAFTSMAN_CHART_BAR_HEIGHT_REM +
            CRAFTSMAN_CHART_CHROME_HEIGHT_REM,
    )}rem`;

    const categoryShrinkShareItems = useMemo<ShrinkSharePieItem[]>(
        () =>
            bySkuCategory
                .filter(
                    (row) =>
                        !row.shrink.startsWith('+') && Number(row.shrink) > 0,
                )
                .map((row) => ({
                    label: row.skuCategory,
                    shrink: Number(row.shrink),
                    recordCount: row.documentCount,
                }))
                .sort((left, right) => right.shrink - left.shrink),
        [bySkuCategory],
    );

    const categoryShrinkPercentChartItems = useMemo(
        () =>
            bySkuCategory
                .filter(
                    (row) =>
                        row.shrinkPercent !== null &&
                        !row.shrinkPercent.startsWith('+'),
                )
                .map((row) => ({
                    process: row.skuCategory,
                    totalShrink: String(row.shrinkPercent).replace('%', ''),
                    recordCount: row.documentCount,
                    tooltipExtra: formatGram(row.shrink),
                }))
                .sort(
                    (left, right) =>
                        Number(right.totalShrink) - Number(left.totalShrink),
                ),
        [bySkuCategory],
    );

    const qcNoteChartItems = useMemo(() => {
        const groups = new Map<
            string,
            { label: string; count: number; rejectCount: number }
        >();

        rows.forEach((row) => {
            const note = row.qcNotes?.trim();

            if (!note) {
                return;
            }

            const key = note.toLowerCase();
            const group = groups.get(key) ?? {
                label: key.replace(/\b\w/g, (letter) => letter.toUpperCase()),
                count: 0,
                rejectCount: 0,
            };

            group.count += 1;

            if (row.qcStatus === 'NOT OK') {
                group.rejectCount += 1;
            }

            groups.set(key, group);
        });

        const totalNotes = [...groups.values()].reduce(
            (total, group) => total + group.count,
            0,
        );

        return [...groups.values()]
            .sort((left, right) => right.count - left.count)
            .map((group) => {
                const percent = `${((group.count / totalNotes) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;

                return {
                    process: group.label,
                    totalShrink: String(group.count),
                    recordCount: group.count,
                    labelExtra: percent,
                    tooltipExtra: `${percent} dari total catatan · ${group.rejectCount} QC Reject`,
                };
            });
    }, [rows]);

    const topShrinkSpk = useMemo(() => findTopShrinkSpk(rows), [rows]);

    const topShrinkDocuments = useMemo(
        () =>
            rows
                .filter(
                    (row) =>
                        row.shrink !== null &&
                        !row.shrink.startsWith('+') &&
                        Number(row.shrink) > 0,
                )
                .sort(
                    (left, right) => Number(right.shrink) - Number(left.shrink),
                )
                .slice(0, TOP_SHRINK_DOCUMENT_LIMIT),
        [rows],
    );

    const kpiCards: Array<{
        label: string;
        value: string;
        subvalue?: string;
        hint: string;
        icon: string;
        tone: 'blue' | 'amber' | 'green' | 'orange' | 'red';
    }> = [
        {
            label: 'Total Proses Finishing',
            value: summary.documentCount.toLocaleString('id-ID'),
            hint: `${summary.craftsmanCount.toLocaleString('id-ID')} pengrajin`,
            icon: activityItemsIcon,
            tone: 'blue',
        },
        {
            label: 'Total SPK',
            value: summary.spkCount.toLocaleString('id-ID'),
            hint: 'SPK unik pada periode ini',
            icon: documentTextIcon,
            tone: 'blue',
        },
        {
            label: 'Berat Masuk',
            value: formatGram(
                Number(summary.startWeight) + Number(summary.submitMaterial),
            ),
            hint: 'Berat awal + bahan',
            icon: inboxIcon,
            tone: 'amber',
        },
        {
            label: 'Berat Keluar',
            value: formatGram(
                Number(summary.finishWeight) + Number(summary.resultMaterial),
            ),
            hint: 'Berat akhir + bahan sisa',
            icon: outboxIcon,
            tone: 'green',
        },
        {
            label: 'Total Susut',
            value: formatGram(summary.shrink),
            subvalue: summary.shrinkPercent ?? undefined,
            hint: 'Persentase = susut / berat masuk',
            icon: trendDownIcon,
            tone: 'orange',
        },
        {
            label: 'Rata-rata Susut per Proses',
            value: formatGram(summary.averageShrinkPerProcess),
            subvalue: summary.averageShrinkPercentPerProcess ?? undefined,
            hint: 'Rata-rata gram dan persentase tiap proses',
            icon: trendDownIcon,
            tone: 'orange',
        },
        {
            label: 'Rata-rata Susut per SPK',
            value: formatGram(summary.averageShrinkPerSpk),
            subvalue: summary.averageShrinkPercentPerSpk ?? undefined,
            hint: 'Susut tiap SPK dijumlahkan, lalu dirata-rata',
            icon: trendDownIcon,
            tone: 'orange',
        },
        {
            label: 'QC Reject',
            value: summary.qcNotOkCount.toLocaleString('id-ID'),
            subvalue: formatQcRejectPercent(summary),
            hint: `dari ${summary.documentCount.toLocaleString('id-ID')} proses finishing`,
            icon: qualityIssueIcon,
            tone: 'red',
        },
    ];

    return (
        <>
            <Head title="Laporan Finishing" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Laporan Finishing</h1>
                        <p className="dashPageSubtitle">
                            Proses Finishing yang sudah di-approve (Serahkan ke
                            PPIC dan Completed)
                        </p>
                    </div>
                    <div
                        className="dashHeaderActions finishingReportFilters"
                        role="group"
                        aria-label="Filter laporan finishing"
                    >
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
                    aria-label="Ringkasan laporan finishing"
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
                                {card.subvalue ? (
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

                <article className="dashPanel dashProcessBarPanel finishingReportTrendPanel">
                    <header className="dashPanelHeader finishingReportTrendHeader">
                        <div className="dashPanelHeaderText">
                            <h2 className="dashPanelTitle">Tren Total Susut</h2>
                            <p className="dashPanelMeta">
                                {trendCraftsmanLabel} · {trendPeriodLabel}
                            </p>
                        </div>
                        {trendHighlights ? (
                            <div
                                className="finishingReportTrendStats"
                                aria-label="Ringkasan tren susut bulanan"
                            >
                                {trendHighlights.map((stat) => (
                                    <article
                                        key={stat.label}
                                        className={`finishingReportTrendStat is-${stat.tone}`}
                                    >
                                        <span className="finishingReportTrendStatLabel">
                                            {stat.label}
                                        </span>
                                        <strong className="finishingReportTrendStatValue">
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
                    <MonthlyShrinkTrendChart months={monthlyShrink.months} />
                </article>

                <div
                    className="dashPieRow dashMaterialYieldRow finishingReportChartRow is-chart-table"
                    style={{ minHeight: craftsmanChartRowHeight }}
                >
                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader is-with-action">
                            <div className="dashPanelHeaderText">
                                <h2 className="dashPanelTitle">
                                    Susut &amp; Total Proses per Pengrajin
                                </h2>
                                <p className="dashPanelMeta">
                                    Semua pengrajin · urut susut terbesar ·{' '}
                                    {periodLabel}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="dashKpiFileBtn dashPanelDetailBtn"
                                aria-label="Lihat rekap per pengrajin"
                                title="Lihat rekap per pengrajin"
                                onClick={() => setCraftsmanRecapOpen(true)}
                            >
                                <Table2 aria-hidden="true" />
                            </button>
                        </header>
                        <CraftsmanShrinkProcessChart
                            items={craftsmanShrinkProcessItems}
                        />
                    </article>

                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Waktu Pengerjaan per Pengrajin
                            </h2>
                            <p className="dashPanelMeta">
                                Total waktu serah s/d terima · urut terlama
                            </p>
                        </header>
                        {craftsmanWorkDurations.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada proses finishing.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap finishingReportWorkTable">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Pengrajin</th>
                                            <th>Proses</th>
                                            <th>Total Waktu</th>
                                            <th>Rata-rata / Proses</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {craftsmanWorkDurations.map((row) => (
                                            <tr key={row.craftsmanName}>
                                                <td>{row.craftsmanName}</td>
                                                <td>
                                                    {row.documentCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {row.workMinutesCount > 0
                                                        ? formatMinutesDuration(
                                                              row.workMinutes,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td>
                                                    {row.workMinutesCount > 0
                                                        ? formatMinutesDuration(
                                                              row.workMinutes /
                                                                  row.workMinutesCount,
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

                <Dialog
                    open={craftsmanRecapOpen}
                    onOpenChange={setCraftsmanRecapOpen}
                >
                    <DialogContent className="dashStatusModal sm:max-w-[min(72rem,calc(100vw-2rem))]">
                        <DialogHeader>
                            <DialogTitle>Rekap per Pengrajin</DialogTitle>
                            <DialogDescription>
                                Berat, susut, dan QC per pengrajin ·{' '}
                                {periodLabel}
                            </DialogDescription>
                        </DialogHeader>
                        {byCraftsman.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada proses finishing untuk filter ini.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Pengrajin</th>
                                            <th>Proses Finishing</th>
                                            <th>Berat Masuk</th>
                                            <th>Berat Keluar</th>
                                            <th>Susut</th>
                                            <th>% Susut</th>
                                            <th>QC Reject</th>
                                            <th>% QC Reject</th>
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
                                                        Number(
                                                            row.startWeight,
                                                        ) +
                                                            Number(
                                                                row.submitMaterial,
                                                            ),
                                                    )}
                                                </td>
                                                <td>
                                                    {formatGram(
                                                        Number(
                                                            row.finishWeight,
                                                        ) +
                                                            Number(
                                                                row.resultMaterial,
                                                            ),
                                                    )}
                                                </td>
                                                <td>
                                                    {formatGram(row.shrink)}
                                                </td>
                                                <td>
                                                    {row.shrinkPercent ?? '—'}
                                                </td>
                                                <td>
                                                    {row.qcNotOkCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatQcRejectPercent(row)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                <div className="dashPieRow dashMaterialYieldRow finishingReportChartRow is-three">
                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Persentase Susut per Kategori SKU (%)
                            </h2>
                            <p className="dashPanelMeta">
                                Urut persentase terbesar · susut / berat masuk
                            </p>
                        </header>
                        <ShrinkByProcessBarChart
                            items={categoryShrinkPercentChartItems}
                            recordLabel="proses finishing"
                            valueSuffix="%"
                            emptyMessage="Belum ada data persentase susut per kategori SKU."
                        />
                    </article>

                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Distribusi Susut per Kategori SKU
                            </h2>
                            <p className="dashPanelMeta">
                                Porsi susut tiap kategori SKU dari total susut ·{' '}
                                {periodLabel}
                            </p>
                        </header>
                        <ShrinkSharePieChart
                            items={categoryShrinkShareItems}
                            emptyMessage="Belum ada data susut per kategori SKU."
                        />
                    </article>

                    <article className="dashPanel dashProcessBarPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">Catatan QC</h2>
                            <p className="dashPanelMeta">
                                Jumlah proses &amp; persentase per catatan QC ·{' '}
                                {periodLabel}
                            </p>
                        </header>
                        <ShrinkByProcessBarChart
                            items={qcNoteChartItems}
                            valueSuffix=" proses"
                            showRecordCount={false}
                            emptyMessage="Belum ada catatan QC."
                        />
                    </article>
                </div>

                <TopShrinkSpkCard
                    summary={topShrinkSpk}
                    periodLabel={periodLabel}
                />

                <div className="finishingReportTableRow">
                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Proses Finishing dengan Susut Terbesar
                            </h2>
                            <p className="dashPanelMeta">
                                Top {TOP_SHRINK_DOCUMENT_LIMIT} · urut susut (g)
                                terbesar · {periodLabel}
                            </p>
                        </header>
                        {topShrinkDocuments.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada proses finishing dengan susut.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ID</th>
                                            <th>Pengrajin</th>
                                            <th>No SPK</th>
                                            <th>Berat Masuk</th>
                                            <th>Susut</th>
                                            <th>% Susut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topShrinkDocuments.map(
                                            (row, index) => (
                                                <tr
                                                    key={`${row.docNo}-${index}`}
                                                >
                                                    <td>{index + 1}</td>
                                                    <td>
                                                        <FinishingDocLink
                                                            row={row}
                                                        />
                                                    </td>
                                                    <td>
                                                        {row.craftsmanName ??
                                                            '—'}
                                                    </td>
                                                    <td>
                                                        <SpkLink
                                                            spkNo={row.spkNo}
                                                        />
                                                    </td>
                                                    <td>
                                                        {formatGram(
                                                            Number(
                                                                row.startWeight ??
                                                                    0,
                                                            ) +
                                                                Number(
                                                                    row.submitMaterial ??
                                                                        0,
                                                                ),
                                                        )}
                                                    </td>
                                                    <td>
                                                        {formatGram(row.shrink)}
                                                    </td>
                                                    <td>
                                                        {row.shrinkPercent ??
                                                            '—'}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </article>
                </div>

                <article className="dashPanel">
                    <header className="dashPanelHeader finishingReportPanelHeaderRow">
                        <div className="dashPanelHeaderText">
                            <h2 className="dashPanelTitle">
                                Detail Proses Finishing
                            </h2>
                            <p className="dashPanelMeta">
                                {rows.length.toLocaleString('id-ID')} proses
                                finishing · urut nama pengrajin
                            </p>
                        </div>
                        <Button
                            design="Default"
                            icon={excelAttachmentIcon}
                            tooltip="Export Excel sesuai filter"
                            onClick={downloadExcel}
                        >
                            Export
                        </Button>
                    </header>
                    {rows.length === 0 ? (
                        <p className="dashEmpty">
                            Belum ada proses finishing untuk filter ini.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tanggal Serah</th>
                                        <th>Tanggal Terima</th>
                                        <th>Pengrajin</th>
                                        <th>No SPK</th>
                                        <th>Item</th>
                                        <th>Proses</th>
                                        <th>Kategori</th>
                                        <th>Berat Awal</th>
                                        <th>Bahan</th>
                                        <th>Berat Akhir</th>
                                        <th>Bahan Sisa</th>
                                        <th>Susut</th>
                                        <th>% Susut</th>
                                        <th>QC</th>
                                        <th>Catatan QC</th>
                                        <th>Waktu Pengerjaan</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, index) => (
                                        <tr key={`${row.docNo}-${index}`}>
                                            <td>
                                                <FinishingDocLink row={row} />
                                            </td>
                                            <td>
                                                {formatDateDisplay(
                                                    row.sendCraftsmanDate,
                                                )}
                                            </td>
                                            <td>
                                                {formatDateDisplay(
                                                    row.receivedCraftsmanDate,
                                                )}
                                            </td>
                                            <td>{row.craftsmanName ?? '—'}</td>
                                            <td>
                                                <SpkLink spkNo={row.spkNo} />
                                            </td>
                                            <td className="finishingReportWrapCell">
                                                {row.item ?? '—'}
                                            </td>
                                            <td>{row.processName ?? '—'}</td>
                                            <td>{row.itemCategory ?? '—'}</td>
                                            <td>
                                                {formatGram(row.startWeight)}
                                            </td>
                                            <td>
                                                {formatGram(row.submitMaterial)}
                                            </td>
                                            <td>
                                                {formatGram(row.finishWeight)}
                                            </td>
                                            <td>
                                                {formatGram(row.resultMaterial)}
                                            </td>
                                            <td>{formatGram(row.shrink)}</td>
                                            <td>{row.shrinkPercent ?? '—'}</td>
                                            <td>{row.qcStatus ?? '—'}</td>
                                            <td className="finishingReportWrapCell">
                                                {row.qcNotes ?? '—'}
                                            </td>
                                            <td>{row.workDuration ?? '—'}</td>
                                            <td className="finishingReportWrapCell">
                                                {row.notes ?? '—'}
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

FinishingReport.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Laporan Finishing',
};
