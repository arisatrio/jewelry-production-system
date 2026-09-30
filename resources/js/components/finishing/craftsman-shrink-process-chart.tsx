import * as am5 from '@amcharts/amcharts5';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';
import * as am5xy from '@amcharts/amcharts5/xy';
import { useLayoutEffect, useRef } from 'react';

export type CraftsmanShrinkProcessItem = {
    craftsmanName: string;
    shrink: string;
    shrinkPercent: string | null;
    processCount: number;
};

type CraftsmanShrinkProcessChartProps = {
    items: CraftsmanShrinkProcessItem[];
    emptyMessage?: string;
};

const SHRINK_COLOR = 0xdc2626;
const SHRINK_LABEL_COLOR = 0x7f1d1d;
const PROCESS_COLOR = 0x60a5fa;
const PROCESS_LABEL_COLOR = 0x1e3a8a;

export function CraftsmanShrinkProcessChart({
    items,
    emptyMessage = 'Belum ada data susut pengrajin.',
}: CraftsmanShrinkProcessChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || items.length === 0) {
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
                paddingTop: 4,
                paddingBottom: 0,
                paddingLeft: 0,
                paddingRight: 36,
            }),
        );

        chart.set(
            'cursor',
            am5xy.XYCursor.new(root, {
                behavior: 'none',
            }),
        );
        chart.get('cursor')?.lineY.set('visible', false);
        chart.get('cursor')?.lineX.set('visible', false);

        const data = items.map((item) => ({
            label: item.craftsmanName,
            shrink: Number(item.shrink),
            shrinkDisplay: item.shrink,
            shrinkPercent: item.shrinkPercent ? ` (${item.shrinkPercent})` : '',
            processCount: item.processCount,
        }));

        const yRenderer = am5xy.AxisRendererY.new(root, {
            inversed: true,
            cellStartLocation: 0.12,
            cellEndLocation: 0.88,
            minGridDistance: 18,
        });
        yRenderer.grid.template.set('visible', false);
        yRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x374151),
            fontWeight: '600',
            oversizedBehavior: 'truncate',
            maxWidth: 120,
        });

        const yAxis = chart.yAxes.push(
            am5xy.CategoryAxis.new(root, {
                categoryField: 'label',
                renderer: yRenderer,
            }),
        );

        const createValueAxis = (
            maxValue: number,
            opposite: boolean,
            labelColor: number,
        ): am5xy.ValueAxis<am5xy.AxisRendererX> => {
            const renderer = am5xy.AxisRendererX.new(root, {
                minGridDistance: 40,
                strokeOpacity: 0.1,
                opposite,
            });
            renderer.labels.template.setAll({
                fontSize: 10,
                fill: am5.color(labelColor),
            });
            renderer.grid.template.setAll({
                stroke: am5.color(0xe5e7eb),
                strokeOpacity: 1,
                visible: !opposite,
            });

            return chart.xAxes.push(
                am5xy.ValueAxis.new(root, {
                    min: 0,
                    max: Math.max(maxValue, 0.001) * 1.2,
                    strictMinMax: true,
                    renderer,
                }),
            );
        };

        const shrinkAxis = createValueAxis(
            Math.max(...data.map((item) => item.shrink), 0),
            false,
            SHRINK_LABEL_COLOR,
        );
        const processAxis = createValueAxis(
            Math.max(...data.map((item) => item.processCount), 0),
            true,
            PROCESS_LABEL_COLOR,
        );

        const tooltipText =
            '[bold]{categoryY}[/]\nSusut: {shrinkDisplay} g{shrinkPercent}\nProses finishing: {processCount}';

        const createSeries = (
            name: string,
            xAxis: am5xy.ValueAxis<am5xy.AxisRendererX>,
            valueXField: 'shrink' | 'processCount',
            color: number,
            labelColor: number,
            formatLabel: (context: (typeof data)[number]) => string,
        ): am5xy.ColumnSeries => {
            const series = chart.series.push(
                am5xy.ColumnSeries.new(root, {
                    name,
                    xAxis,
                    yAxis,
                    valueXField,
                    categoryYField: 'label',
                    sequencedInterpolation: true,
                    fill: am5.color(color),
                    stroke: am5.color(color),
                    tooltip: am5.Tooltip.new(root, {
                        pointerOrientation: 'left',
                        labelText: tooltipText,
                    }),
                }),
            );

            series.columns.template.setAll({
                height: am5.percent(90),
                cornerRadiusBR: 3,
                cornerRadiusTR: 3,
                strokeOpacity: 0,
            });

            series.bullets.push((bulletRoot, _series, dataItem) => {
                const context = dataItem.dataContext as (typeof data)[number];

                return am5.Bullet.new(bulletRoot, {
                    locationX: 1,
                    sprite: am5.Label.new(bulletRoot, {
                        text: formatLabel(context),
                        centerY: am5.p50,
                        centerX: am5.p0,
                        dx: 4,
                        fontSize: 9,
                        fontWeight: '700',
                        fill: am5.color(labelColor),
                        populateText: false,
                    }),
                });
            });

            series.data.setAll(data);
            series.appear(600);

            return series;
        };

        createSeries(
            'Susut (g)',
            shrinkAxis,
            'shrink',
            SHRINK_COLOR,
            SHRINK_LABEL_COLOR,
            (context) => `${context.shrinkDisplay} g`,
        );
        createSeries(
            'Jumlah proses',
            processAxis,
            'processCount',
            PROCESS_COLOR,
            PROCESS_LABEL_COLOR,
            (context) => `${context.processCount} proses`,
        );

        const legend = chart.children.unshift(
            am5.Legend.new(root, {
                centerX: am5.p50,
                x: am5.p50,
                marginBottom: 4,
            }),
        );
        legend.labels.template.setAll({ fontSize: 11, fontWeight: '600' });
        legend.markers.template.setAll({ width: 12, height: 12 });
        legend.data.setAll(chart.series.values);

        yAxis.data.setAll(data);
        chart.appear(600, 80);

        return () => {
            root.dispose();
        };
    }, [items]);

    if (items.length === 0) {
        return <p className="dashEmpty">{emptyMessage}</p>;
    }

    return <div ref={chartRef} className="dashProcessBarChart" />;
}
