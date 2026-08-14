import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5percent from '@amcharts/amcharts5/percent';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type DistributionPieChartProps = {
    items: Array<{
        label: string;
        count: number;
        qty: number;
        percent: string;
    }>;
    /**
     * Metric shown in legend before the percentage.
     * - spk: Total SPK (tipe produksi)
     * - qty: Total QTY (distribusi item)
     */
    legendMetric?: 'spk' | 'qty';
};

type PieDatum = {
    category: string;
    value: number;
    qty: number;
    spk: number;
    percent: string;
};

export function DistributionPieChart({
    items,
    legendMetric = 'spk',
}: DistributionPieChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || items.length === 0) {
            return;
        }

        const root = am5.Root.new(element);
        root.setThemes([am5themes_Animated.new(root)]);
        root.container.set('layout', root.horizontalLayout);

        const chart = root.container.children.push(
            am5percent.PieChart.new(root, {
                endAngle: 270,
                innerRadius: am5.percent(42),
                radius: am5.percent(90),
                width: am5.percent(40),
                paddingTop: 2,
                paddingBottom: 2,
                paddingLeft: 0,
                paddingRight: 4,
            }),
        );

        const tooltipText =
            legendMetric === 'qty'
                ? '{category}: {qty} QTY ({valuePercentTotal.formatNumber("#.#")}%)'
                : '{category}: {spk} SPK ({valuePercentTotal.formatNumber("#.#")}%)';

        const series = chart.series.push(
            am5percent.PieSeries.new(root, {
                valueField: 'value',
                categoryField: 'category',
                endAngle: 270,
                alignLabels: false,
                tooltip: am5.Tooltip.new(root, {
                    labelText: tooltipText,
                }),
            }),
        );

        series.states.create('hidden', {
            endAngle: -90,
        });

        series.labels.template.set('forceHidden', true);
        series.ticks.template.set('forceHidden', true);

        series.slices.template.setAll({
            stroke: am5.color(0xffffff),
            strokeWidth: 2,
            cornerRadius: 3,
        });

        series.slices.template.states.create('hover', {
            scale: 1.04,
        });

        const maxLegendRows = 3;
        const legendColumnCount = Math.max(
            1,
            Math.ceil(items.length / maxLegendRows),
        );

        const legendWrap = root.container.children.push(
            am5.Container.new(root, {
                width: am5.percent(60),
                centerY: am5.percent(50),
                y: am5.percent(50),
                layout: root.horizontalLayout,
                paddingLeft: 6,
                paddingRight: 2,
            }),
        );

        const totalForPercent =
            legendMetric === 'qty'
                ? items.reduce((sum, item) => sum + item.qty, 0)
                : items.reduce((sum, item) => sum + item.count, 0);

        series.data.setAll(
            items.map((item): PieDatum => {
                const metricValue =
                    legendMetric === 'qty' ? item.qty : item.count;
                const percent =
                    totalForPercent > 0
                        ? ((metricValue / totalForPercent) * 100).toFixed(1)
                        : '0.0';

                return {
                    category: item.label,
                    value: metricValue,
                    qty: item.qty,
                    spk: item.count,
                    percent,
                };
            }),
        );

        const configureLegend = (legend: am5.Legend): void => {
            legend.valueLabels.template.set('forceHidden', true);

            legend.labels.template.setAll({
                fontSize: 10,
                fontWeight: '600',
                fill: am5.color(0x374151),
                oversizedBehavior: 'wrap',
                maxWidth: 120,
                lineHeight: 1.25,
                paddingLeft: 4,
            });

            legend.labels.template.adapters.add('text', (_text, target) => {
                const dataItem = target.dataItem;

                if (!dataItem) {
                    return '';
                }

                const context = dataItem.dataContext as PieDatum | undefined;

                if (!context) {
                    return '';
                }

                const metric =
                    legendMetric === 'qty'
                        ? `${Number(context.qty ?? 0).toLocaleString('id-ID')} QTY`
                        : `${Number(context.spk ?? 0).toLocaleString('id-ID')} SPK`;

                return `${context.category}\n${metric} · ${context.percent}%`;
            });

            legend.markers.template.setAll({
                width: 10,
                height: 10,
                marginRight: 4,
            });

            legend.markerRectangles.template.setAll({
                cornerRadiusTL: 2,
                cornerRadiusTR: 2,
                cornerRadiusBL: 2,
                cornerRadiusBR: 2,
            });

            legend.itemContainers.template.setAll({
                paddingTop: 3,
                paddingBottom: 3,
                paddingRight: 8,
            });
        };

        for (let column = 0; column < legendColumnCount; column += 1) {
            const start = column * maxLegendRows;
            const chunk = series.dataItems.slice(start, start + maxLegendRows);

            const legend = legendWrap.children.push(
                am5.Legend.new(root, {
                    layout: root.verticalLayout,
                    centerY: am5.percent(50),
                    y: am5.percent(50),
                }),
            );

            configureLegend(legend);
            legend.data.setAll(chunk);
        }

        series.appear(800, 80);

        return () => {
            root.dispose();
        };
    }, [items, legendMetric]);

    if (items.length === 0) {
        return <p className="dashEmpty">Belum ada data.</p>;
    }

    return <div ref={chartRef} className="dashPieChart" />;
}
