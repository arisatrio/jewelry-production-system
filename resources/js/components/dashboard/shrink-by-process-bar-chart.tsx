import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type ShrinkProcessItem = {
    process: string;
    totalShrink: string;
    recordCount: number;
};

type ShrinkByProcessBarChartProps = {
    items: ShrinkProcessItem[];
};

export function ShrinkByProcessBarChart({
    items,
}: ShrinkByProcessBarChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || items.length === 0) {
            return;
        }

        const root = am5.Root.new(element);
        root.setThemes([am5themes_Animated.new(root)]);

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

        const yRenderer = am5xy.AxisRendererY.new(root, {
            inversed: true,
            cellStartLocation: 0.15,
            cellEndLocation: 0.85,
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
                tooltip: am5.Tooltip.new(root, {}),
            }),
        );

        const xRenderer = am5xy.AxisRendererX.new(root, {
            minGridDistance: 40,
            strokeOpacity: 0.1,
        });
        xRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        xRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const data = items.map((item) => ({
            label: item.process,
            value: Number(item.totalShrink),
            display: item.totalShrink,
            records: item.recordCount,
        }));

        const maxValue = Math.max(...data.map((item) => item.value), 0.001);

        const xAxis = chart.xAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                max: maxValue * 1.2,
                strictMinMax: true,
                renderer: xRenderer,
            }),
        );

        const AMBER_LIGHT = { r: 253, g: 230, b: 138 };
        const AMBER_DARK = { r: 146, g: 64, b: 14 };

        const colorForValue = (value: number): number => {
            const t = value / maxValue;
            const r = Math.round(
                AMBER_LIGHT.r + (AMBER_DARK.r - AMBER_LIGHT.r) * t,
            );
            const g = Math.round(
                AMBER_LIGHT.g + (AMBER_DARK.g - AMBER_LIGHT.g) * t,
            );
            const b = Math.round(
                AMBER_LIGHT.b + (AMBER_DARK.b - AMBER_LIGHT.b) * t,
            );

            return (r << 16) | (g << 8) | b;
        };

        const series = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: 'Susut',
                xAxis,
                yAxis,
                valueXField: 'value',
                categoryYField: 'label',
                sequencedInterpolation: true,
                fill: am5.color(0xd97706),
                stroke: am5.color(0xd97706),
                tooltip: am5.Tooltip.new(root, {
                    pointerOrientation: 'left',
                    labelText: '{categoryY}: {display} g · {records} record',
                }),
            }),
        );

        series.columns.template.setAll({
            height: am5.percent(70),
            cornerRadiusBR: 3,
            cornerRadiusTR: 3,
            strokeOpacity: 0,
        });

        series.columns.template.adapters.add('fill', (_fill, target) => {
            const context = target.dataItem?.dataContext as
                | { value: number }
                | undefined;

            return am5.color(colorForValue(Number(context?.value ?? 0)));
        });

        series.columns.template.adapters.add('stroke', (_stroke, target) => {
            const context = target.dataItem?.dataContext as
                | { value: number }
                | undefined;

            return am5.color(colorForValue(Number(context?.value ?? 0)));
        });

        series.bullets.push((bulletRoot, _series, dataItem) => {
            const context = dataItem.dataContext as {
                value: number;
                display: string;
            };
            const value = Number(context?.value ?? 0);
            const inside = maxValue > 0 && value / maxValue >= 0.82;

            const label = am5.Label.new(bulletRoot, {
                text: `${context.display} g`,
                centerY: am5.p50,
                centerX: inside ? am5.p100 : am5.p0,
                dx: inside ? -6 : 4,
                fontSize: 10,
                fontWeight: '700',
                fill: am5.color(inside ? 0xffffff : 0x78350f),
            });

            return am5.Bullet.new(bulletRoot, {
                locationX: inside ? 0.98 : 1,
                sprite: label,
            });
        });

        yAxis.data.setAll(data);
        series.data.setAll(data);
        series.appear(600);
        chart.appear(600, 80);

        return () => {
            root.dispose();
        };
    }, [items]);

    if (items.length === 0) {
        return <p className="dashEmpty">Belum ada data susut per proses.</p>;
    }

    return <div ref={chartRef} className="dashProcessBarChart" />;
}
