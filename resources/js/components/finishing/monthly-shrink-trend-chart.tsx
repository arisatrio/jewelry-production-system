import * as am5 from '@amcharts/amcharts5';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';
import * as am5xy from '@amcharts/amcharts5/xy';
import { useLayoutEffect, useRef } from 'react';

export type MonthlyShrinkPoint = {
    month: number;
    shrink: string;
    shrinkPercent: string | null;
    processCount: number;
    includeInTrend: boolean;
};

type MonthlyShrinkTrendChartProps = {
    months: MonthlyShrinkPoint[];
    emptyMessage?: string;
};

const MONTH_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
] as const;

const SHRINK_COLOR = 0xea580c;
const TREND_COLOR = 0x1d4ed8;

export function monthlyShrinkMonthLabel(month: number): string {
    return MONTH_LABELS[month - 1] ?? String(month);
}

export function monthlyShrinkPeriodLabel(
    year: number,
    months: MonthlyShrinkPoint[],
): string {
    const first = MONTH_LABELS[(months[0]?.month ?? 1) - 1];
    const last = MONTH_LABELS[(months.at(-1)?.month ?? 1) - 1];

    if (!first || !last || first === last) {
        return `${first ?? 'Jan'} ${year}`;
    }

    return `${first}–${last} ${year}`;
}

function formatShrinkGrams(value: number): string {
    const formatted = Math.abs(value).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    if (value < -0.0005) {
        return `+${formatted} g`;
    }

    return `${formatted} g`;
}

function formatProcessCount(count: number): string {
    return `${count.toLocaleString('id-ID')} proses`;
}

export function MonthlyShrinkTrendChart({
    months,
    emptyMessage = 'Belum ada data susut year to date.',
}: MonthlyShrinkTrendChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || months.length === 0) {
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

        const cursor = chart.set(
            'cursor',
            am5xy.XYCursor.new(root, {
                behavior: 'none',
            }),
        );
        cursor.lineY.set('visible', false);

        const data = months.map((point) => {
            const shrink = Number(point.shrink);
            const shrinkDisplay = formatShrinkGrams(shrink);
            const percentLabel = point.shrinkPercent ?? '';

            const monthLabel =
                MONTH_LABELS[point.month - 1] ?? String(point.month);
            const processLabel = formatProcessCount(point.processCount);
            const valueLabel = point.includeInTrend
                ? [shrinkDisplay, percentLabel, processLabel]
                      .filter((line) => line !== '')
                      .join('\n')
                : '';
            const tooltipParts = [
                shrinkDisplay,
                percentLabel,
                processLabel,
            ].filter((part) => part !== '');

            return {
                label: monthLabel,
                shrink,
                trend: point.includeInTrend ? shrink : null,
                shrinkDisplay,
                percentLabel,
                valueLabel,
                tooltipLabel: point.includeInTrend
                    ? `${monthLabel}: ${tooltipParts.join(' · ')}`
                    : `${monthLabel}: ${shrinkDisplay}`,
            };
        });

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

        const yRenderer = am5xy.AxisRendererY.new(root, {
            minGridDistance: 28,
        });
        yRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        yRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const values = data.map((row) => row.shrink);
        const minValue = Math.min(0, ...values);
        const maxValue = Math.max(0, ...values);
        const padding = Math.max((maxValue - minValue) * 0.36, 0.05);

        const yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                min: minValue === 0 ? 0 : minValue - padding,
                max: maxValue + padding,
                strictMinMax: true,
                renderer: yRenderer,
            }),
        );

        const series = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: 'Total susut',
                xAxis,
                yAxis,
                valueYField: 'shrink',
                categoryXField: 'label',
                sequencedInterpolation: true,
                fill: am5.color(SHRINK_COLOR),
                stroke: am5.color(SHRINK_COLOR),
                tooltip: am5.Tooltip.new(root, {
                    labelText: '{tooltipLabel}',
                }),
            }),
        );

        series.columns.template.setAll({
            width: am5.percent(62),
            cornerRadiusTL: 3,
            cornerRadiusTR: 3,
            strokeOpacity: 0,
            tooltipText: '{tooltipLabel}',
        });
        series.bullets.push((bulletRoot, _series, dataItem) => {
            const shrink = Number(
                (dataItem.dataContext as { shrink?: number } | undefined)
                    ?.shrink ?? 0,
            );
            const isNegative = shrink < -0.0005;

            return am5.Bullet.new(bulletRoot, {
                locationY: 1,
                sprite: am5.Label.new(bulletRoot, {
                    text: '{valueLabel}',
                    populateText: true,
                    textAlign: 'center',
                    centerX: am5.p50,
                    centerY: isNegative ? 0 : am5.p100,
                    dy: isNegative ? 4 : -6,
                    fontSize: 10,
                    fontWeight: '700',
                    fill: am5.color(0x9a3412),
                }),
            });
        });

        const trendSeries = chart.series.push(
            am5xy.LineSeries.new(root, {
                name: 'Tren',
                xAxis,
                yAxis,
                valueYField: 'trend',
                categoryXField: 'label',
                connect: false,
                stroke: am5.color(TREND_COLOR),
                fill: am5.color(TREND_COLOR),
            }),
        );

        trendSeries.strokes.template.setAll({
            strokeWidth: 2.5,
        });
        trendSeries.bullets.push((bulletRoot, _series, dataItem) => {
            const trend = (
                dataItem.dataContext as { trend?: number | null } | undefined
            )?.trend;

            if (trend === null || trend === undefined) {
                return undefined;
            }

            return am5.Bullet.new(bulletRoot, {
                sprite: am5.Circle.new(bulletRoot, {
                    radius: 4,
                    fill: am5.color(TREND_COLOR),
                    stroke: am5.color(0xffffff),
                    strokeWidth: 1.5,
                }),
            });
        });

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
        series.data.setAll(data);
        trendSeries.data.setAll(data);
        series.appear(800);
        trendSeries.appear(800);
        chart.appear(800, 100);

        return () => {
            root.dispose();
        };
    }, [months]);

    if (months.length === 0) {
        return <p className="dashEmpty">{emptyMessage}</p>;
    }

    return <div ref={chartRef} className="finishingReportTrendChart" />;
}
