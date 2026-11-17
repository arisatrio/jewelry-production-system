import * as am5 from '@amcharts/amcharts5';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';
import * as am5xy from '@amcharts/amcharts5/xy';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { useLayoutEffect, useMemo, useRef, useState } from 'react';
import { monthlyShrinkMonthLabel } from '@/components/finishing/monthly-shrink-trend-chart';

export type CoranMonthlyActivityPoint = {
    month: number;
    documentCount: number;
    submitMaterial: string;
    spkWeight: string;
    resultMaterial: string;
    shrink: string;
    includeInTrend: boolean;
};

export type CoranActivityChartVariant =
    | 'line-percent'
    | 'clustered-percent'
    | 'clustered-gram'
    | 'susut-highlight';

type CoranMonthlyActivityTrendChartProps = {
    months: CoranMonthlyActivityPoint[];
    emptyMessage?: string;
};

const HASIL_COLOR = 0x16a34a;
const SISA_COLOR = 0x7c3aed;
const SUSUT_COLOR = 0xea580c;
const COUNT_COLOR = 0x1d4ed8;

const VARIANT_LABELS: Record<CoranActivityChartVariant, string> = {
    'line-percent': 'Garis % alokasi',
    'clustered-percent': 'Batang % berdampingan',
    'clustered-gram': 'Batang gram berdampingan',
    'susut-highlight': 'Sorot % susut (sumbu diperbesar)',
};

function formatGrams(value: number): string {
    return `${value.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} g`;
}

function formatCount(value: number): string {
    return `${value.toLocaleString('id-ID')} coran`;
}

function formatPercent(value: number): string {
    return `${value.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}

type ChartRow = {
    label: string;
    documentCount: number;
    submitMaterial: number;
    spkWeight: number;
    resultMaterial: number;
    shrink: number;
    shrinkActual: number;
    hasilOfBahanPct: number;
    sisaOfBahanPct: number;
    susutOfBahanPct: number;
};

function toPercent(part: number, whole: number): number {
    if (Math.abs(whole) < 0.0005) {
        return 0;
    }

    return (part / whole) * 100;
}

function buildChartData(months: CoranMonthlyActivityPoint[]): ChartRow[] {
    return months.map((point) => {
        const monthLabel = monthlyShrinkMonthLabel(point.month);
        const include = point.includeInTrend;

        const submitMaterial = include ? Number(point.submitMaterial) : 0;
        const spkWeight = include ? Number(point.spkWeight) : 0;
        const resultMaterial = include ? Number(point.resultMaterial) : 0;
        const shrink = include ? Math.max(Number(point.shrink), 0) : 0;

        return {
            label: monthLabel,
            documentCount: include ? point.documentCount : 0,
            submitMaterial,
            spkWeight,
            resultMaterial,
            shrink,
            shrinkActual: include ? Number(point.shrink) : 0,
            hasilOfBahanPct: toPercent(spkWeight, submitMaterial),
            sisaOfBahanPct: toPercent(resultMaterial, submitMaterial),
            susutOfBahanPct: toPercent(shrink, submitMaterial),
        };
    });
}

export function CoranMonthlyActivityTrendChart({
    months,
    emptyMessage = 'Belum ada data coran year to date.',
}: CoranMonthlyActivityTrendChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);
    const [variant, setVariant] =
        useState<CoranActivityChartVariant>('line-percent');

    const hasData = months.some(
        (point) =>
            point.includeInTrend &&
            (point.documentCount > 0 ||
                Math.abs(Number(point.submitMaterial)) >= 0.0005),
    );

    const chartData = useMemo(() => buildChartData(months), [months]);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || chartData.length === 0 || !hasData) {
            return;
        }

        const root = am5.Root.new(element);
        root.setThemes([am5themes_Animated.new(root)]);
        root.numberFormatter.set('numberFormat', '#,###.##');

        const chart = root.container.children.push(
            am5xy.XYChart.new(root, {
                panX: false,
                panY: false,
                wheelX: 'none',
                wheelY: 'none',
                layout: root.verticalLayout,
                paddingTop: 0,
                paddingBottom: 0,
                paddingLeft: 0,
                paddingRight: 12,
            }),
        );

        chart.set(
            'cursor',
            am5xy.XYCursor.new(root, {
                behavior: 'none',
            }),
        );
        chart.get('cursor')?.lineY.set('visible', false);

        const data = chartData;

        const xRenderer = am5xy.AxisRendererX.new(root, {
            minGridDistance: 24,
        });
        xRenderer.labels.template.setAll({
            fontSize: 11,
            fill: am5.color(0x374151),
            fontWeight: '600',
        });
        xRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const xAxis = chart.xAxes.push(
            am5xy.CategoryAxis.new(root, {
                categoryField: 'label',
                renderer: xRenderer,
            }),
        );

        const countRenderer = am5xy.AxisRendererY.new(root, {
            opposite: true,
            minGridDistance: 28,
        });
        countRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        countRenderer.grid.template.set('visible', false);

        const countMax = Math.max(1, ...data.map((row) => row.documentCount));

        const countAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                max: Math.ceil(countMax * 1.25),
                strictMinMax: true,
                maxPrecision: 0,
                renderer: countRenderer,
            }),
        );

        const countSeries = chart.series.push(
            am5xy.LineSeries.new(root, {
                name: 'Jumlah coran',
                xAxis,
                yAxis: countAxis,
                valueYField: 'documentCount',
                categoryXField: 'label',
                stroke: am5.color(COUNT_COLOR),
                fill: am5.color(COUNT_COLOR),
            }),
        );

        countSeries.strokes.template.setAll({
            strokeWidth: 2.5,
        });
        countSeries.bullets.push(() =>
            am5.Bullet.new(root, {
                sprite: am5.Circle.new(root, {
                    radius: 4,
                    fill: am5.color(COUNT_COLOR),
                    stroke: am5.color(0xffffff),
                    strokeWidth: 1.5,
                }),
            }),
        );
        countSeries.adapters.add('tooltipText', (_text, target) => {
            const context = target.dataItem?.dataContext as
                | { documentCount?: number; label?: string }
                | undefined;
            const count = Number(context?.documentCount ?? 0);
            const label = context?.label ?? '';

            return `${label}: ${formatCount(count)}`;
        });

        const materialSeries: am5xy.XYSeries[] = [];

        if (variant === 'line-percent' || variant === 'clustered-percent') {
            const percentCeiling = Math.min(
                100,
                Math.max(
                    20,
                    ...data.flatMap((row) => [
                        row.hasilOfBahanPct,
                        row.sisaOfBahanPct,
                        row.susutOfBahanPct,
                    ]),
                ) * 1.15,
            );

            const percentRenderer = am5xy.AxisRendererY.new(root, {
                minGridDistance: 28,
            });
            percentRenderer.labels.template.setAll({
                fontSize: 10,
                fill: am5.color(0x6b7280),
            });
            percentRenderer.labels.template.adapters.add('text', (text) => {
                if (text === undefined || text === null || text === '') {
                    return text;
                }

                return `${text}%`;
            });
            percentRenderer.grid.template.setAll({
                stroke: am5.color(0xe5e7eb),
                strokeOpacity: 1,
            });

            const percentAxis = chart.yAxes.push(
                am5xy.ValueAxis.new(root, {
                    min: 0,
                    max: percentCeiling,
                    strictMinMax: true,
                    maxPrecision: 0,
                    renderer: percentRenderer,
                }),
            );

            const percentFields = [
                {
                    name: 'Hasil',
                    field: 'hasilOfBahanPct' as const,
                    gramField: 'spkWeight' as const,
                    color: HASIL_COLOR,
                },
                {
                    name: 'Sisa',
                    field: 'sisaOfBahanPct' as const,
                    gramField: 'resultMaterial' as const,
                    color: SISA_COLOR,
                },
                {
                    name: 'Susut',
                    field: 'susutOfBahanPct' as const,
                    gramField: 'shrink' as const,
                    color: SUSUT_COLOR,
                    useShrinkActual: true,
                },
            ];

            for (const item of percentFields) {
                if (variant === 'line-percent') {
                    const series = chart.series.push(
                        am5xy.LineSeries.new(root, {
                            name: item.name,
                            xAxis,
                            yAxis: percentAxis,
                            valueYField: item.field,
                            categoryXField: 'label',
                            stroke: am5.color(item.color),
                            fill: am5.color(item.color),
                        }),
                    );
                    series.strokes.template.setAll({ strokeWidth: 2.5 });
                    series.bullets.push(() =>
                        am5.Bullet.new(root, {
                            sprite: am5.Circle.new(root, {
                                radius: 3.5,
                                fill: am5.color(item.color),
                                stroke: am5.color(0xffffff),
                                strokeWidth: 1.5,
                            }),
                        }),
                    );
                    series.adapters.add('tooltipText', (_text, target) => {
                        const context = target.dataItem?.dataContext as
                            | ChartRow
                            | undefined;
                        const percent = Number(context?.[item.field] ?? 0);
                        const grams = item.useShrinkActual
                            ? Number(
                                  context?.shrinkActual ??
                                      context?.shrink ??
                                      0,
                              )
                            : Number(context?.[item.gramField] ?? 0);

                        return `${item.name}: ${formatPercent(percent)} dari bahan · ${formatGrams(grams)}`;
                    });
                    materialSeries.push(series);
                } else {
                    const series = chart.series.push(
                        am5xy.ColumnSeries.new(root, {
                            name: item.name,
                            xAxis,
                            yAxis: percentAxis,
                            valueYField: item.field,
                            categoryXField: 'label',
                            clustered: true,
                            fill: am5.color(item.color),
                            stroke: am5.color(item.color),
                        }),
                    );
                    series.columns.template.setAll({
                        width: am5.percent(90),
                        strokeOpacity: 0,
                        cornerRadiusTR: 2,
                        cornerRadiusTL: 2,
                    });
                    series.adapters.add('tooltipText', (_text, target) => {
                        const context = target.dataItem?.dataContext as
                            | ChartRow
                            | undefined;
                        const percent = Number(context?.[item.field] ?? 0);
                        const grams = item.useShrinkActual
                            ? Number(
                                  context?.shrinkActual ??
                                      context?.shrink ??
                                      0,
                              )
                            : Number(context?.[item.gramField] ?? 0);

                        return `${item.name}: ${formatPercent(percent)} dari bahan · ${formatGrams(grams)}`;
                    });
                    materialSeries.push(series);
                }
            }
        }

        if (variant === 'clustered-gram') {
            const gramValues = data.flatMap((row) => [
                row.spkWeight,
                row.resultMaterial,
                row.shrink,
                row.submitMaterial,
            ]);
            const gramMax = Math.max(0, ...gramValues);
            const gramPadding = Math.max(gramMax * 0.15, 0.05);

            const gramRenderer = am5xy.AxisRendererY.new(root, {
                minGridDistance: 28,
            });
            gramRenderer.labels.template.setAll({
                fontSize: 10,
                fill: am5.color(0x6b7280),
            });
            gramRenderer.grid.template.setAll({
                stroke: am5.color(0xe5e7eb),
                strokeOpacity: 1,
            });

            const gramAxis = chart.yAxes.push(
                am5xy.ValueAxis.new(root, {
                    min: 0,
                    max: gramMax + gramPadding,
                    strictMinMax: true,
                    renderer: gramRenderer,
                }),
            );

            const gramFields = [
                {
                    name: 'Hasil',
                    field: 'spkWeight' as const,
                    color: HASIL_COLOR,
                },
                {
                    name: 'Sisa',
                    field: 'resultMaterial' as const,
                    color: SISA_COLOR,
                },
                {
                    name: 'Susut',
                    field: 'shrink' as const,
                    color: SUSUT_COLOR,
                    useShrinkActual: true,
                },
            ];

            for (const item of gramFields) {
                const series = chart.series.push(
                    am5xy.ColumnSeries.new(root, {
                        name: item.name,
                        xAxis,
                        yAxis: gramAxis,
                        valueYField: item.field,
                        categoryXField: 'label',
                        clustered: true,
                        fill: am5.color(item.color),
                        stroke: am5.color(item.color),
                    }),
                );
                series.columns.template.setAll({
                    width: am5.percent(90),
                    strokeOpacity: 0,
                    cornerRadiusTR: 2,
                    cornerRadiusTL: 2,
                });
                series.adapters.add('tooltipText', (_text, target) => {
                    const context = target.dataItem?.dataContext as
                        | ChartRow
                        | undefined;
                    const grams = item.useShrinkActual
                        ? Number(
                              context?.shrinkActual ?? context?.shrink ?? 0,
                          )
                        : Number(context?.[item.field] ?? 0);
                    const bahan = Number(context?.submitMaterial ?? 0);
                    const pct = toPercent(grams, bahan);

                    return `${item.name}: ${formatGrams(grams)} · ${formatPercent(pct)} bahan`;
                });
                materialSeries.push(series);
            }
        }

        if (variant === 'susut-highlight') {
            const maxSusutPct = Math.max(
                0,
                ...data.map((row) => row.susutOfBahanPct),
            );
            const susutCeiling = Math.min(
                100,
                Math.max(6, maxSusutPct * 1.35 + 0.5),
            );

            const susutRenderer = am5xy.AxisRendererY.new(root, {
                minGridDistance: 28,
            });
            susutRenderer.labels.template.setAll({
                fontSize: 10,
                fill: am5.color(0x9a3412),
                fontWeight: '600',
            });
            susutRenderer.labels.template.adapters.add('text', (text) => {
                if (text === undefined || text === null || text === '') {
                    return text;
                }

                return `${text}% susut`;
            });
            susutRenderer.grid.template.setAll({
                stroke: am5.color(0xfed7aa),
                strokeOpacity: 1,
            });

            const susutAxis = chart.yAxes.push(
                am5xy.ValueAxis.new(root, {
                    min: 0,
                    max: susutCeiling,
                    strictMinMax: true,
                    maxPrecision: 1,
                    renderer: susutRenderer,
                }),
            );

            const susutColumnSeries = chart.series.push(
                am5xy.ColumnSeries.new(root, {
                    name: 'Susut (% bahan)',
                    xAxis,
                    yAxis: susutAxis,
                    valueYField: 'susutOfBahanPct',
                    categoryXField: 'label',
                    fill: am5.color(SUSUT_COLOR),
                    stroke: am5.color(SUSUT_COLOR),
                    tooltip: am5.Tooltip.new(root, {}),
                }),
            );

            susutColumnSeries.columns.template.setAll({
                width: am5.percent(50),
                strokeOpacity: 0,
                cornerRadiusTL: 4,
                cornerRadiusTR: 4,
                fillOpacity: 0.45,
            });

            const susutLineSeries = chart.series.push(
                am5xy.LineSeries.new(root, {
                    name: 'Tren % susut',
                    xAxis,
                    yAxis: susutAxis,
                    valueYField: 'susutOfBahanPct',
                    categoryXField: 'label',
                    stroke: am5.color(SUSUT_COLOR),
                    fill: am5.color(SUSUT_COLOR),
                }),
            );

            susutLineSeries.strokes.template.setAll({
                strokeWidth: 3.5,
            });

            susutLineSeries.bullets.push(() =>
                am5.Bullet.new(root, {
                    sprite: am5.Circle.new(root, {
                        radius: 5,
                        fill: am5.color(SUSUT_COLOR),
                        stroke: am5.color(0xffffff),
                        strokeWidth: 2,
                    }),
                }),
            );

            susutLineSeries.bullets.push((bulletRoot, _series, dataItem) => {
                const context = dataItem.dataContext as ChartRow | undefined;
                const pct = Number(context?.susutOfBahanPct ?? 0);

                if (pct < 0.05) {
                    return undefined;
                }

                return am5.Bullet.new(bulletRoot, {
                    locationY: 1,
                    sprite: am5.Label.new(bulletRoot, {
                        text: formatPercent(pct),
                        populateText: true,
                        textAlign: 'center',
                        centerX: am5.p50,
                        centerY: am5.p100,
                        dy: -8,
                        fontSize: 10,
                        fontWeight: '700',
                        fill: am5.color(0x9a3412),
                    }),
                });
            });

            const susutTooltip = (
                context: ChartRow | undefined,
            ): string => {
                const pct = Number(context?.susutOfBahanPct ?? 0);
                const grams = Number(
                    context?.shrinkActual ?? context?.shrink ?? 0,
                );

                return [
                    `Susut: ${formatPercent(pct)} dari bahan`,
                    formatGrams(grams),
                    `Hasil ${formatPercent(context?.hasilOfBahanPct ?? 0)} · Sisa ${formatPercent(context?.sisaOfBahanPct ?? 0)}`,
                    `Bahan ${formatGrams(context?.submitMaterial ?? 0)}`,
                ].join(' · ');
            };

            susutColumnSeries.adapters.add('tooltipText', (_text, target) => {
                const context = target.dataItem?.dataContext as
                    | ChartRow
                    | undefined;

                return susutTooltip(context);
            });
            susutLineSeries.adapters.add('tooltipText', (_text, target) => {
                const context = target.dataItem?.dataContext as
                    | ChartRow
                    | undefined;

                return susutTooltip(context);
            });

            materialSeries.push(susutColumnSeries, susutLineSeries);
        }

        const legend = chart.children.unshift(
            am5.Legend.new(root, {
                centerX: am5.p0,
                x: 0,
                layout: root.horizontalLayout,
                marginBottom: 44,
            }),
        );
        legend.markers.template.setAll({
            width: 12,
            height: 12,
        });
        legend.labels.template.setAll({
            fontSize: 11,
            fill: am5.color(0x374151),
        });
        legend.data.setAll(chart.series.values);

        xAxis.data.setAll(data);
        [...materialSeries, countSeries].forEach((series) => {
            series.data.setAll(data);
            series.appear(800);
        });
        chart.appear(800, 100);

        return () => {
            root.dispose();
        };
    }, [chartData, hasData, variant]);

    if (!hasData) {
        return <p className="dashEmpty">{emptyMessage}</p>;
    }

    return (
        <div className="coranActivityChartWrap">
            <div className="coranActivityChartToolbar">
                <label className="coranActivityChartToolbarLabel">
                    Tampilan grafik
                    <Select
                        className="coranActivityChartVariantSelect"
                        onChange={(event) => {
                            const value = event.detail.selectedOption?.value;

                            if (
                                value === 'line-percent' ||
                                value === 'clustered-percent' ||
                                value === 'clustered-gram' ||
                                value === 'susut-highlight'
                            ) {
                                setVariant(value);
                            } else {
                                setVariant('line-percent');
                            }
                        }}
                    >
                        <Option
                            value="line-percent"
                            selected={variant === 'line-percent'}
                        >
                            {VARIANT_LABELS['line-percent']}
                        </Option>
                        <Option
                            value="clustered-percent"
                            selected={variant === 'clustered-percent'}
                        >
                            {VARIANT_LABELS['clustered-percent']}
                        </Option>
                        <Option
                            value="clustered-gram"
                            selected={variant === 'clustered-gram'}
                        >
                            {VARIANT_LABELS['clustered-gram']}
                        </Option>
                        <Option
                            value="susut-highlight"
                            selected={variant === 'susut-highlight'}
                        >
                            {VARIANT_LABELS['susut-highlight']}
                        </Option>
                    </Select>
                </label>
                <span className="coranActivityChartToolbarHint">
                    Untuk susut kecil, pakai <strong>Sorot % susut</strong>{' '}
                    atau batang berdampingan. Tooltip tetap menampilkan hasil &
                    sisa.
                </span>
            </div>
            <div ref={chartRef} className="finishingReportTrendChart" />
        </div>
    );
}
