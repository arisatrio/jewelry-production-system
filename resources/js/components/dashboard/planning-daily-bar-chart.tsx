import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type PlanningDailyPoint = {
    date: string;
    label: string;
    done: number;
    pending: number;
};

type PlanningDailyBarChartProps = {
    days: PlanningDailyPoint[];
};

export function PlanningDailyBarChart({ days }: PlanningDailyBarChartProps) {
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
                layout: root.verticalLayout,
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

        const cursor = chart.get('cursor');
        cursor?.lineY.set('visible', false);

        const xRenderer = am5xy.AxisRendererX.new(root, {
            minGridDistance: 18,
            cellStartLocation: 0.15,
            cellEndLocation: 0.85,
        });
        xRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
            paddingTop: 4,
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
        });
        yRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        yRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                maxPrecision: 0,
                renderer: yRenderer,
            }),
        );

        const createSeries = (
            name: string,
            field: 'done' | 'pending',
            color: number,
        ): am5xy.ColumnSeries => {
            const series = chart.series.push(
                am5xy.ColumnSeries.new(root, {
                    name,
                    xAxis,
                    yAxis,
                    valueYField: field,
                    categoryXField: 'label',
                    clustered: true,
                    fill: am5.color(color),
                    stroke: am5.color(color),
                    tooltip: am5.Tooltip.new(root, {
                        labelText: '{name}: {valueY} SPK ({categoryX})',
                    }),
                }),
            );

            series.columns.template.setAll({
                width: am5.percent(70),
                cornerRadiusTL: 2,
                cornerRadiusTR: 2,
                strokeOpacity: 0,
            });

            return series;
        };

        const doneSeries = createSeries('Selesai', 'done', 0x16a34a);
        const pendingSeries = createSeries('Belum selesai', 'pending', 0xf59e0b);

        const legend = chart.children.push(
            am5.Legend.new(root, {
                centerX: am5.percent(50),
                x: am5.percent(50),
                marginTop: 4,
                layout: root.horizontalLayout,
            }),
        );
        legend.labels.template.setAll({
            fontSize: 11,
            fill: am5.color(0x374151),
        });
        legend.markers.template.setAll({
            width: 10,
            height: 10,
        });
        legend.data.setAll(chart.series.values);

        const data = days.map((day) => ({
            label: day.label,
            date: day.date,
            done: day.done,
            pending: day.pending,
        }));

        xAxis.data.setAll(data);
        doneSeries.data.setAll(data);
        pendingSeries.data.setAll(data);

        doneSeries.appear(700);
        pendingSeries.appear(700);
        chart.appear(700, 80);

        return () => {
            root.dispose();
        };
    }, [days]);

    if (days.length === 0) {
        return <p className="dashEmpty">Belum ada data planning.</p>;
    }

    return <div ref={chartRef} className="dashPlanningChart" />;
}
