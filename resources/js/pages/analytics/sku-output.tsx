import { Head, router } from '@inertiajs/react';
import navigationLeftIcon from '@ui5/webcomponents-icons/dist/navigation-left-arrow.js';
import navigationRightIcon from '@ui5/webcomponents-icons/dist/navigation-right-arrow.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { DistributionPieChart } from '@/components/dashboard/distribution-pie-chart';
import { skuOutput } from '@/routes/analytics';

export type SkuOutputAnalytics = {
    period: {
        label: string;
        start: string;
        end: string;
    };
    summary: {
        totalSpk: number;
        totalQty: number;
        doneSpk: number;
        doneQty: number;
        uniqueSku: number;
        completionPercent: string | null;
    };
    bySku: Array<{
        sku: string;
        item: string;
        spkCount: number;
        qty: number;
        doneSpk: number;
        doneQty: number;
        completionPercent: string;
    }>;
    byItem: Array<{
        item: string;
        spkCount: number;
        qty: number;
        doneSpk: number;
        doneQty: number;
        completionPercent: string;
    }>;
};

type SkuOutputDashboardProps = {
    analytics: SkuOutputAnalytics;
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

function formatPercent(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })}%`;
}

export default function SkuOutputDashboard({
    analytics,
    navigation,
}: SkuOutputDashboardProps) {
    const { summary, bySku, byItem, period } = analytics;

    const goToMonth = (month: string | null): void => {
        if (!month) {
            return;
        }

        router.get(
            skuOutput.url({
                query: { month },
            }),
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const itemPieItems = byItem.map((row) => ({
        label: row.item,
        count: row.spkCount,
        qty: row.qty,
        percent: row.completionPercent,
    }));

    const kpiCards: Array<{
        label: string;
        value: string;
        hint: string;
        className?: string;
    }> = [
        {
            label: 'SKU / Item Unik',
            value: summary.uniqueSku.toLocaleString('id-ID'),
            hint: `Output · ${period.label}`,
        },
        {
            label: 'Total SPK',
            value: summary.totalSpk.toLocaleString('id-ID'),
            hint: `Qty ${summary.totalQty.toLocaleString('id-ID')}`,
            className: 'is-progress',
        },
        {
            label: 'SPK Selesai',
            value: summary.doneSpk.toLocaleString('id-ID'),
            hint: `Qty selesai ${summary.doneQty.toLocaleString('id-ID')}`,
            className: 'is-done',
        },
        {
            label: 'Completion',
            value: formatPercent(summary.completionPercent),
            hint: 'SPK done / total SPK',
            className:
                Number(summary.completionPercent ?? 0) >= 80
                    ? 'is-target-good'
                    : Number(summary.completionPercent ?? 0) >= 50
                      ? 'is-target-warning'
                      : 'is-target-critical',
        },
        {
            label: 'Qty Outstanding',
            value: Math.max(
                summary.totalQty - summary.doneQty,
                0,
            ).toLocaleString('id-ID'),
            hint: 'Belum selesai',
        },
        {
            label: 'Top SKU Rows',
            value: bySku.length.toLocaleString('id-ID'),
            hint: 'Ditampilkan di tabel output SKU',
        },
    ];

    return (
        <>
            <Head title="Dashboard Output SKU" />

            <div className="dashShell is-scrollable">
                <header className="dashPageHeader">
                    <div>
                        <h1 className="dashPageTitle">Dashboard Output SKU</h1>
                        <p className="dashPageSubtitle">
                            Output produksi per SKU dan item: volume SPK, qty,
                            dan tingkat penyelesaian.
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
                    aria-label="Ringkasan output SKU"
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
                    <article className="dashPanel dashPiePanel is-compact">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Distribusi Item
                            </h2>
                            <p className="dashPanelMeta">
                                SPK per item · {period.label}
                            </p>
                        </header>
                        <DistributionPieChart
                            items={itemPieItems}
                            legendMetric="qty"
                        />
                    </article>

                    <article className="dashPanel">
                        <header className="dashPanelHeader">
                            <h2 className="dashPanelTitle">
                                Output per Item
                            </h2>
                            <p className="dashPanelMeta">
                                Top item berdasarkan qty
                            </p>
                        </header>
                        {byItem.length === 0 ? (
                            <p className="dashEmpty">
                                Belum ada data output item.
                            </p>
                        ) : (
                            <div className="dashStatusTableWrap">
                                <table className="dashStatusTable">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>SPK</th>
                                            <th>Qty</th>
                                            <th>Done</th>
                                            <th>%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {byItem.map((row) => (
                                            <tr key={row.item}>
                                                <td>{row.item}</td>
                                                <td>
                                                    {row.spkCount.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {row.qty.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {row.doneQty.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td>
                                                    {formatPercent(
                                                        row.completionPercent,
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
                        <h2 className="dashPanelTitle">Output per SKU</h2>
                        <p className="dashPanelMeta">
                            Top 20 SKU berdasarkan qty · {period.label}
                        </p>
                    </header>
                    {bySku.length === 0 ? (
                        <p className="dashEmpty">
                            Belum ada SPK bertaut SKU untuk periode ini. Lihat
                            tabel output item di atas.
                        </p>
                    ) : (
                        <div className="dashStatusTableWrap">
                            <table className="dashStatusTable">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Item</th>
                                        <th>SPK</th>
                                        <th>Qty</th>
                                        <th>Done SPK</th>
                                        <th>Done Qty</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bySku.map((row) => (
                                        <tr key={`${row.sku}-${row.item}`}>
                                            <td>{row.sku}</td>
                                            <td>{row.item}</td>
                                            <td>
                                                {row.spkCount.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td>
                                                {row.qty.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td>
                                                {row.doneSpk.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td>
                                                {row.doneQty.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td>
                                                {formatPercent(
                                                    row.completionPercent,
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
        </>
    );
}

SkuOutputDashboard.layout = {
    activeMenu: 'Output SKU',
    pageTitle: 'Output SKU',
};
