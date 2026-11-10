import { useLayoutEffect, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

export type CraftsmanProcessStackedBarItem = {
    label: string;
    nonReparasiCount: number;
    reparasiCount: number;
};

type CraftsmanProcessStackedBarChartProps = {
    items: CraftsmanProcessStackedBarItem[];
};

export function CraftsmanProcessStackedBarChart({
    items,
}: CraftsmanProcessStackedBarChartProps) {
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

        const maxTotal = Math.max(
            ...items.map(
                (item) => item.nonReparasiCount + item.reparasiCount,
            ),
            1,
        );

        const xAxis = chart.xAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                maxPrecision: 0,
                max: Math.ceil(maxTotal * 1.2),
                strictMinMax: true,
                renderer: xRenderer,
            }),
        );

        const createSeries = (
            name: string,
            field: 'nonReparasiCount' | 'reparasiCount',
            color: number,
        ): am5xy.ColumnSeries => {
            const series = chart.series.push(
                am5xy.ColumnSeries.new(root, {
                    name,
                    xAxis,
                    yAxis,
                    valueXField: field,
                    categoryYField: 'label',
                    stacked: true,
                    fill: am5.color(color),
                    stroke: am5.color(color),
                    tooltip: am5.Tooltip.new(root, {
                        pointerOrientation: 'left',
                        labelText: '{name}: {valueX} job ({categoryY})',
                    }),
                }),
            );

            series.columns.template.setAll({
                height: am5.percent(70),
                strokeOpacity: 0,
            });

            return series;
        };

        const nonReparasiSeries = createSeries(
            'Non-reparasi',
            'nonReparasiCount',
            0xdc2626,
        );
        const reparasiSeries = createSeries(
            'Reparasi',
            'reparasiCount',
            0xf59e0b,
        );

        nonReparasiSeries.columns.template.setAll({
            cornerRadiusBL: 3,
            cornerRadiusTL: 3,
        });

        reparasiSeries.columns.template.setAll({
            cornerRadiusBR: 3,
            cornerRadiusTR: 3,
        });

        const createTotalBullet = (
            showWhen: (total: number, reparasiCount: number) => boolean,
        ) =>
            reparasiSeries.bullets.push((bulletRoot, _series, dataItem) => {
                const context = dataItem.dataContext as {
                    totalCount: number;
                    reparasiCount: number;
                };
                const total = Number(context?.totalCount ?? 0);
                const reparasiCount = Number(context?.reparasiCount ?? 0);

                if (!showWhen(total, reparasiCount)) {
                    return undefined;
                }

                const inside = maxTotal > 0 && total / maxTotal >= 0.82;

                const label = am5.Label.new(bulletRoot, {
                    text: String(total),
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

        createTotalBullet((_total, reparasiCount) => reparasiCount > 0);

        nonReparasiSeries.bullets.push((bulletRoot, _series, dataItem) => {
            const context = dataItem.dataContext as {
                totalCount: number;
                reparasiCount: number;
            };
            const total = Number(context?.totalCount ?? 0);
            const reparasiCount = Number(context?.reparasiCount ?? 0);

            if (reparasiCount > 0) {
                return undefined;
            }

            const inside = maxTotal > 0 && total / maxTotal >= 0.82;

            const label = am5.Label.new(bulletRoot, {
                text: String(total),
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

        const legend = chart.children.push(
            am5.Legend.new(root, {
                centerX: am5.percent(50),
                x: am5.percent(50),
                marginTop: 6,
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

        const data = items.map((item) => ({
            label: item.label,
            nonReparasiCount: item.nonReparasiCount,
            reparasiCount: item.reparasiCount,
            totalCount: item.nonReparasiCount + item.reparasiCount,
        }));

        yAxis.data.setAll(data);
        nonReparasiSeries.data.setAll(data);
        reparasiSeries.data.setAll(data);

        nonReparasiSeries.appear(600);
        reparasiSeries.appear(600);
        chart.appear(600, 80);

        return () => {
            root.dispose();
        };
    }, [items]);

    if (items.length === 0) {
        return (
            <p className="dashEmpty">Belum ada data job per proses.</p>
        );
    }

    return <div ref={chartRef} className="dashProcessBarChart" />;
}
