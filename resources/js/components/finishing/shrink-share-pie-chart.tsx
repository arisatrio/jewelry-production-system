import * as am5 from '@amcharts/amcharts5';
import * as am5percent from '@amcharts/amcharts5/percent';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';
import { useLayoutEffect, useRef } from 'react';

export type ShrinkSharePieItem = {
    label: string;
    shrink: number;
    recordCount: number;
};

type ShrinkSharePieChartProps = {
    items: ShrinkSharePieItem[];
    recordLabel?: string;
    emptyMessage?: string;
};

type ShrinkShareDatum = {
    category: string;
    value: number;
    shrinkDisplay: string;
    records: number;
    percent: string;
};

const SLICE_COLORS = [
    0xb91c1c, 0xf97316, 0xeab308, 0x16a34a, 0x0891b2, 0x2563eb, 0x7c3aed,
    0xdb2777, 0x92400e, 0x475569, 0xf87171, 0xfdba74, 0x84cc16, 0x14b8a6,
    0x818cf8, 0xc084fc, 0xf43f5e, 0x94a3b8, 0x0ea5e9,
];

const LEGEND_MAX_ROWS = 10;

export function ShrinkSharePieChart({
    items,
    recordLabel = 'proses finishing',
    emptyMessage = 'Belum ada data susut.',
}: ShrinkSharePieChartProps) {
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
                innerRadius: am5.percent(45),
                radius: am5.percent(88),
                width: am5.percent(42),
                paddingTop: 4,
                paddingBottom: 4,
            }),
        );

        const series = chart.series.push(
            am5percent.PieSeries.new(root, {
                valueField: 'value',
                categoryField: 'category',
                alignLabels: false,
                tooltip: am5.Tooltip.new(root, {
                    labelText: `[bold]{category}[/]\n{shrinkDisplay} g · {percent}% dari total susut\n{records} ${recordLabel}`,
                }),
            }),
        );

        series.set(
            'colors',
            am5.ColorSet.new(root, {
                colors: SLICE_COLORS.map((color) => am5.color(color)),
                reuse: true,
            }),
        );

        series.labels.template.set('forceHidden', true);
        series.ticks.template.set('forceHidden', true);
        series.slices.template.setAll({
            stroke: am5.color(0xffffff),
            strokeWidth: 2,
            cornerRadius: 3,
        });
        series.slices.template.states.create('hover', { scale: 1.04 });

        const totalShrink = items.reduce((sum, item) => sum + item.shrink, 0);

        series.data.setAll(
            items.map((item): ShrinkShareDatum => ({
                category: item.label,
                value: item.shrink,
                shrinkDisplay: item.shrink.toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }),
                records: item.recordCount,
                percent:
                    totalShrink > 0
                        ? ((item.shrink / totalShrink) * 100).toFixed(2)
                        : '0.00',
            })),
        );

        const legendWrap = root.container.children.push(
            am5.Container.new(root, {
                width: am5.percent(58),
                centerY: am5.percent(50),
                y: am5.percent(50),
                layout: root.horizontalLayout,
                paddingLeft: 8,
            }),
        );

        const legendColumnCount = Math.max(
            1,
            Math.ceil(items.length / LEGEND_MAX_ROWS),
        );

        for (let column = 0; column < legendColumnCount; column += 1) {
            const start = column * LEGEND_MAX_ROWS;
            const legend = legendWrap.children.push(
                am5.Legend.new(root, {
                    layout: root.verticalLayout,
                    centerY: am5.percent(50),
                    y: am5.percent(50),
                }),
            );

            legend.valueLabels.template.set('forceHidden', true);
            legend.labels.template.setAll({
                fontSize: 10,
                fontWeight: '600',
                fill: am5.color(0x374151),
                oversizedBehavior: 'wrap',
                maxWidth: 130,
                lineHeight: 1.25,
                paddingLeft: 4,
            });
            legend.labels.template.adapters.add('text', (_text, target) => {
                const context = target.dataItem?.dataContext as
                    ShrinkShareDatum | undefined;

                if (!context) {
                    return '';
                }

                return `${context.category}\n${context.shrinkDisplay} g · ${context.percent}%`;
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
                paddingRight: 10,
            });
            legend.data.setAll(
                series.dataItems.slice(start, start + LEGEND_MAX_ROWS),
            );
        }

        series.appear(800, 80);

        return () => {
            root.dispose();
        };
    }, [items, recordLabel]);

    if (items.length === 0) {
        return <p className="dashEmpty">{emptyMessage}</p>;
    }

    return <div ref={chartRef} className="finishingShrinkSharePieChart" />;
}
