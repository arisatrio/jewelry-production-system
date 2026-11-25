import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { yearlyKpi } from '@/routes/analytics';

export type YearlyKpiBreakdownRow = {
    label: string;
    count: number;
    percent: string;
};

export type YearlyKpiAnalytics = {
    period: {
        label: string;
        year: number;
        start: string;
        end: string;
    };
    summary: {
        createdSpk: number;
        createdQty: number;
        completedSpk: number;
        completedQty: number;
        doneRangkaSpk: number;
        doneBarangJadiSpk: number;
        pluIssuedSpk: number;
        pluDatedBy: 'send_to_merch_date' | 'production_completion' | null;
        inProgressSpk: number;
        waitingApprovalSpk: number;
        approvedNotProcessedSpk: number;
        pendingProcessSpk: number;
    };
    breakdown: {
        createdByProductionType: YearlyKpiBreakdownRow[];
        pending: YearlyKpiBreakdownRow[];
        inProgressByProcess: YearlyKpiBreakdownRow[];
        completedPlu: YearlyKpiBreakdownRow[];
    };
};

type YearlyKpiDashboardProps = {
    analytics: YearlyKpiAnalytics;
    filters: {
        year: string;
    };
    navigation: {
        previousYear: string;
        nextYear: string | null;
        currentYear: string;
        isCurrentYear: boolean;
    };
};

function formatSharePercent(
    numerator: number,
    denominator: number,
): string {
    if (denominator <= 0) {
        return '—';
    }

    return `${((numerator / denominator) * 100).toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })}%`;
}

function completionRateClassName(
    numerator: number,
    denominator: number,
): string | undefined {
    if (denominator <= 0) {
        return undefined;
    }

    const rate = (numerator / denominator) * 100;

    if (rate >= 80) {
        return 'is-target-good';
    }

    if (rate >= 50) {
        return 'is-target-warning';
    }

    return 'is-target-critical';
}

function formatBreakdownValue(count: number, headerTotal: number): string {
    return `${count.toLocaleString('id-ID')} · ${formatSharePercent(count, headerTotal)}`;
}

function breakdownRowsToNested(
    rows: YearlyKpiBreakdownRow[],
    emptyLabel: string,
    headerTotal: number,
): Array<{
    label: string;
    value: string;
    hint?: string;
}> {
    if (rows.length === 0) {
        return [
            {
                label: emptyLabel,
                value: formatBreakdownValue(0, headerTotal),
                hint: 'Tidak ada data',
            },
        ];
    }

    return rows.map((row) => ({
        label: row.label,
        value: formatBreakdownValue(row.count, headerTotal),
    }));
}

type YearlyKpiNestedCard = {
    label: string;
    value: string;
    hint?: string;
    className?: string;
};

function YearlyKpiNestedStack({ items }: { items: YearlyKpiNestedCard[] }) {
    const onePerRow = items.length === 2;

    return (
        <div
            className={`dashKpiNestedStack${onePerRow ? ' is-one-per-row' : ''}`}
        >
            {items.map((item) => (
                <div
                    key={item.label}
                    className={`dashKpiNestedCard${item.className ? ` ${item.className}` : ''}`}
                >
                    <span className="dashKpiLabel">{item.label}</span>
                    <strong className="dashKpiValue">{item.value}</strong>
                    {item.hint ? (
                        <span className="dashKpiHint">{item.hint}</span>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

export default function YearlyKpiDashboard({
    analytics,
    navigation,
}: YearlyKpiDashboardProps) {
    const { summary, period, breakdown } = analytics;

    const goToYear = (year: string | null): void => {
        if (!year) {
            return;
        }

        router.get(
            yearlyKpi.url({
                query: { year },
            }),
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const completedPercent = formatSharePercent(
        summary.completedSpk,
        summary.createdSpk,
    );
    const pendingProcessPercent = formatSharePercent(
        summary.pendingProcessSpk,
        summary.createdSpk,
    );
    const inProgressPercent = formatSharePercent(
        summary.inProgressSpk,
        summary.createdSpk,
    );

    const completedCardClassName = [
        'is-done',
        completionRateClassName(summary.completedSpk, summary.createdSpk),
    ]
        .filter(Boolean)
        .join(' ');

    const pluWithCount =
        breakdown.completedPlu.find((row) => row.label === 'Sudah PLU')
            ?.count ?? 0;

    const kpiCards: Array<{
        key: string;
        label: string;
        value: string;
        hint: string;
        className?: string;
        nested: YearlyKpiNestedCard[];
    }> = [
        {
            key: 'created',
            label: 'SPK Dibuat',
            value: summary.createdSpk.toLocaleString('id-ID'),
            hint: `Tipe produksi · ${period.label}`,
            className: 'is-progress',
            nested: breakdownRowsToNested(
                breakdown.createdByProductionType,
                'Tipe produksi',
                summary.createdSpk,
            ),
        },
        {
            key: 'pending',
            label: 'Belum Diproses / Approved',
            value: `${summary.pendingProcessSpk.toLocaleString('id-ID')} · ${pendingProcessPercent}`,
            hint: 'Terhadap dibuat',
            className: 'is-draft',
            nested: breakdownRowsToNested(
                breakdown.pending,
                'Belum selesai',
                summary.pendingProcessSpk,
            ),
        },
        {
            key: 'in-progress',
            label: 'SPK On Proses',
            value: `${summary.inProgressSpk.toLocaleString('id-ID')} · ${inProgressPercent}`,
            hint: 'Terhadap dibuat',
            className: 'is-progress',
            nested: breakdownRowsToNested(
                breakdown.inProgressByProcess,
                'Proses',
                summary.inProgressSpk,
            ),
        },
        {
            key: 'completed',
            label: 'SPK Selesai',
            value: `${summary.completedSpk.toLocaleString('id-ID')} · ${completedPercent}`,
            hint: `Rangka ${summary.doneRangkaSpk.toLocaleString('id-ID')} · BJ ${summary.doneBarangJadiSpk.toLocaleString('id-ID')} · terhadap dibuat`,
            className: completedCardClassName,
            nested: breakdown.completedPlu.map((row) => ({
                label: row.label,
                value: formatBreakdownValue(
                    row.count,
                    summary.completedSpk,
                ),
                className:
                    row.label === 'Sudah PLU'
                        ? completionRateClassName(
                              pluWithCount,
                              summary.completedSpk,
                          )
                        : undefined,
            })),
        },
    ];

    return (
        <>
            <Head title="Yearly KPI" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Yearly KPI</h1>
                        <p className="dashPageSubtitle">
                            Ringkasan tahunan: SPK dibuat, backlog (belum
                            diproses, on proses), penyelesaian, dan PLU. Backlog
                            mengacu pada status terkini SPK kohort tahun
                            tersebut.
                        </p>
                    </div>
                    <div className="dashPeriodPager" aria-label="Navigasi tahun">
                        <Button
                            design="Transparent"
                            icon={navigationLeftIcon}
                            tooltip="Tahun sebelumnya"
                            onClick={() => goToYear(navigation.previousYear)}
                        />
                        <div className="dashPeriodBadge" aria-label="Periode">
                            {period.label}
                        </div>
                        <Button
                            design="Transparent"
                            icon={navigationRightIcon}
                            tooltip="Tahun berikutnya"
                            disabled={navigation.nextYear === null}
                            onClick={() => goToYear(navigation.nextYear)}
                        />
                        {!navigation.isCurrentYear ? (
                            <Button
                                design="Transparent"
                                className="dashPeriodTodayBtn"
                                onClick={() =>
                                    goToYear(navigation.currentYear)
                                }
                            >
                                Tahun ini
                            </Button>
                        ) : null}
                    </div>
                </header>

                <section
                    className="dashKpiGrid is-yearly-kpi"
                    aria-label="Ringkasan KPI tahunan"
                >
                    {kpiCards.map((card) => (
                        <article
                            key={card.key}
                            className={`dashKpiCard is-today is-yearly-kpi${card.className ? ` ${card.className}` : ''}`}
                        >
                            <div className="dashKpiMain">
                                <span className="dashKpiLabel">
                                    {card.label}
                                </span>
                                <strong className="dashKpiValue">
                                    {card.value}
                                </strong>
                                <span className="dashKpiHint">{card.hint}</span>
                            </div>
                            <YearlyKpiNestedStack items={card.nested} />
                        </article>
                    ))}
                </section>
            </div>
        </>
    );
}