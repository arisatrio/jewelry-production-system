import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type ProcessCountItem = {
    label: string;
    count: number;
};

type InProgressProcessBarChartProps = {
    items: ProcessCountItem[];
};

export function InProgressProcessBarChart({
    items,
}: InProgressProcessBarChartProps) {
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
                paddingRight: 28,
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
            maxWidth: 110,
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

        const maxCount = Math.max(...items.map((item) => item.count), 1);
        const minCount = Math.min(...items.map((item) => item.count), 0);

        const xAxis = chart.xAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                maxPrecision: 0,
                // Headroom agar label angka di ujung bar tidak terpotong.
                max: Math.ceil(maxCount * 1.2),
                strictMinMax: true,
                renderer: xRenderer,
            }),
        );

        // Gradasi merah: bottleneck paling tinggi = merah tua, terendah = merah muda.
        const RED_LIGHT = { r: 254, g: 202, b: 202 }; // #fecaca
        const RED_DARK = { r: 185, g: 28, b: 28 }; // #b91c1c

        const colorForCount = (count: number): number => {
            const span = Math.max(maxCount - minCount, 1);
            const t = (count - minCount) / span;
            const r = Math.round(RED_LIGHT.r + (RED_DARK.r - RED_LIGHT.r) * t);
            const g = Math.round(RED_LIGHT.g + (RED_DARK.g - RED_LIGHT.g) * t);
            const b = Math.round(RED_LIGHT.b + (RED_DARK.b - RED_LIGHT.b) * t);

            return (r << 16) | (g << 8) | b;
        };

        const series = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: 'SPK',
                xAxis,
                yAxis,
                valueXField: 'count',
                categoryYField: 'label',
                sequencedInterpolation: true,
                fill: am5.color(0xdc2626),
                stroke: am5.color(0xdc2626),
                tooltip: am5.Tooltip.new(root, {
                    pointerOrientation: 'left',
                    labelText: '{categoryY}: {valueX} SPK',
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
                | { label: string; count: number }
                | undefined;
            const count = Number(context?.count ?? 0);

            return am5.color(colorForCount(count));
        });

        series.columns.template.adapters.add('stroke', (_stroke, target) => {
            const context = target.dataItem?.dataContext as
                | { label: string; count: number }
                | undefined;
            const count = Number(context?.count ?? 0);

            return am5.color(colorForCount(count));
        });

        // Label di dalam bar bila hampir penuh; di luar bila masih ada ruang.
        series.bullets.push((bulletRoot, _series, dataItem) => {
            const context = dataItem.dataContext as {
                label: string;
                count: number;
            };
            const count = Number(context?.count ?? 0);
            const inside = maxCount > 0 && count / maxCount >= 0.82;

            const label = am5.Label.new(bulletRoot, {
                text: String(count),
                centerY: am5.p50,
                centerX: inside ? am5.p100 : am5.p0,
                dx: inside ? -6 : 4,
                fontSize: 10,
                fontWeight: '700',
                fill: am5.color(inside ? 0xffffff : 0x7f1d1d),
            });

            return am5.Bullet.new(bulletRoot, {
                locationX: inside ? 0.98 : 1,
                sprite: label,
            });
        });

        const data = items.map((item) => ({
            label: item.label,
            count: item.count,
        }));

        yAxis.data.setAll(data);
        series.data.setAll(data);
        series.appear(600);
        chart.appear(600, 80);

        return () => {
            root.dispose();
        };
    }, [items]);

    if (items.length === 0) {
        return (
            <p className="dashEmpty">Belum ada SPK in progress per proses.</p>
        );
    }

    return <div ref={chartRef} className="dashProcessBarChart" />;
}
