import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type CreatedDailyPoint = {
    date: string;
    label: string;
    dateLabel: string;
    total: number;
};

type CreatedDailyBarChartProps = {
    days: CreatedDailyPoint[];
};

export function CreatedDailyBarChart({ days }: CreatedDailyBarChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || days.length === 0) {
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
                paddingTop: 8,
                paddingBottom: 0,
                paddingLeft: 0,
                paddingRight: 8,
            }),
        );

        chart.set(
            'cursor',
            am5xy.XYCursor.new(root, {
                behavior: 'none',
            }),
        );
        chart.get('cursor')?.lineY.set('visible', false);

        const xRenderer = am5xy.AxisRendererX.new(root, {
            minGridDistance: 12,
            cellStartLocation: 0.2,
            cellEndLocation: 0.8,
        });
        xRenderer.labels.template.setAll({
            fontSize: 9,
            fill: am5.color(0x6b7280),
            rotation: -45,
            centerY: am5.p50,
            centerX: am5.p100,
            paddingTop: 6,
        });
        xRenderer.grid.template.set('visible', false);

        const xAxis = chart.xAxes.push(
            am5xy.CategoryAxis.new(root, {
                categoryField: 'label',
                renderer: xRenderer,
                tooltip: am5.Tooltip.new(root, {}),
            }),
        );

        const yRenderer = am5xy.AxisRendererY.new(root, {
            strokeOpacity: 0.08,
            minGridDistance: 24,
        });
        yRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        yRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const maxTotal = Math.max(...days.map((day) => day.total), 0);

        const yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                max: Math.max(1, Math.ceil(maxTotal * 1.15)),
                maxPrecision: 0,
                strictMinMax: true,
                renderer: yRenderer,
            }),
        );

        yAxis.children.unshift(
            am5.Label.new(root, {
                rotation: -90,
                text: 'Total',
                y: am5.p50,
                centerX: am5.p50,
                fontSize: 10,
                fontWeight: '600',
                fill: am5.color(0x6b7280),
            }),
        );

        const series = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: 'SPK dibuat',
                xAxis,
                yAxis,
                valueYField: 'total',
                categoryXField: 'label',
                fill: am5.color(0x0070f2),
                stroke: am5.color(0x0070f2),
                tooltip: am5.Tooltip.new(root, {
                    labelText: '{dateLabel}: {valueY} SPK',
                }),
            }),
        );

        series.columns.template.setAll({
            width: am5.percent(72),
            cornerRadiusTL: 2,
            cornerRadiusTR: 2,
            strokeOpacity: 0,
        });

        const data = days.map((day) => ({
            label: day.label,
            date: day.date,
            dateLabel: day.dateLabel,
            total: day.total,
        }));

        xAxis.data.setAll(data);
        series.data.setAll(data);
        series.appear(600);
        chart.appear(600, 80);

        return () => {
            root.dispose();
        };
    }, [days]);

    if (days.length === 0) {
        return <p className="dashEmpty">Belum ada SPK dibuat bulan ini.</p>;
    }

    return (
        <div
            ref={chartRef}
            className="dashCreatedDailyChart"
            role="img"
            aria-label="Permintaan SPK per hari"
        />
    );
}
