import { useLayoutEffect, useMemo, useRef } from 'react';
import * as am5 from '@amcharts/amcharts5';
import * as am5xy from '@amcharts/amcharts5/xy';
import am5themes_Animated from '@amcharts/amcharts5/themes/Animated';

type ForecastItemTypeRow = {
    item: string;
    total: number;
    values: Record<string, number>;
};

type ForecastClusteredBarChartProps = {
    types: string[];
    rows: ForecastItemTypeRow[];
};

/** Biru Fiori Horizon brand — planning estimasi (line) */
const ESTIMASI_COLOR = 0x0070f2;
const REALISASI_MET_COLOR = 0x16a34a;
const REALISASI_SHORT_COLOR = 0xdc2626;

const LEGEND_ITEMS = [
    {
        label: 'Planning estimasi',
        color: `#${ESTIMASI_COLOR.toString(16).padStart(6, '0')}`,
        variant: 'line' as const,
    },
    {
        label: 'Realisasi capai target',
        color: `#${REALISASI_MET_COLOR.toString(16).padStart(6, '0')}`,
        variant: 'bar' as const,
    },
    {
        label: 'Realisasi belum capai target',
        color: `#${REALISASI_SHORT_COLOR.toString(16).padStart(6, '0')}`,
        variant: 'bar' as const,
    },
] as const;

export function ForecastClusteredBarChart({
    rows,
}: ForecastClusteredBarChartProps) {
    const chartRef = useRef<HTMLDivElement | null>(null);

    const data = useMemo(
        () =>
            rows.map((row) => {
                const estimasi = Number(row.values.Estimasi ?? row.total ?? 0);
                const realisasi = Number(row.values.Realisasi ?? 0);
                const percent =
                    estimasi > 0
                        ? Math.round((realisasi / estimasi) * 100)
                        : 0;

                return {
                    category: row.item,
                    total: row.total,
                    Estimasi: estimasi,
                    Realisasi: realisasi,
                    percent,
                };
            }),
        [rows],
    );

    useLayoutEffect(() => {
        const element = chartRef.current;

        if (!element || data.length === 0) {
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
                paddingTop: 20,
                paddingBottom: 4,
                paddingLeft: 0,
                paddingRight: 10,
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

        // Combo: bar realisasi + line planning estimasi
        const xRenderer = am5xy.AxisRendererX.new(root, {
            cellStartLocation: 0.02,
            cellEndLocation: 0.98,
            minGridDistance: 20,
        });
        xRenderer.grid.template.set('visible', false);
        xRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x374151),
            fontWeight: '600',
            rotation: -35,
            centerY: am5.p50,
            centerX: am5.p100,
            paddingRight: 4,
            oversizedBehavior: 'truncate',
            maxWidth: 90,
        });

        const xAxis = chart.xAxes.push(
            am5xy.CategoryAxis.new(root, {
                categoryField: 'category',
                renderer: xRenderer,
                tooltip: am5.Tooltip.new(root, {}),
            }),
        );

        const yRenderer = am5xy.AxisRendererY.new(root, {
            strokeOpacity: 0.1,
            minGridDistance: 30,
        });
        yRenderer.labels.template.setAll({
            fontSize: 10,
            fill: am5.color(0x6b7280),
        });
        yRenderer.grid.template.setAll({
            stroke: am5.color(0xe5e7eb),
            strokeOpacity: 1,
        });

        const maxValue = data.reduce(
            (max, row) => Math.max(max, row.Estimasi, row.Realisasi),
            0,
        );

        const yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                min: 0,
                maxPrecision: 0,
                max: maxValue > 0 ? Math.ceil(maxValue * 1.22) : undefined,
                strictMinMax: maxValue > 0,
                renderer: yRenderer,
            }),
        );

        // Realisasi — bar hijau/merah menurut capaian
        const realisasiSeries = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: 'Realisasi',
                xAxis,
                yAxis,
                valueYField: 'Realisasi',
                categoryXField: 'category',
                sequencedInterpolation: true,
                clustered: false,
                fill: am5.color(REALISASI_MET_COLOR),
                stroke: am5.color(REALISASI_MET_COLOR),
                tooltip: am5.Tooltip.new(root, {
                    pointerOrientation: 'horizontal',
                }),
            }),
        );

        realisasiSeries.columns.template.setAll({
            width: am5.percent(85),
            cornerRadiusTL: 2,
            cornerRadiusTR: 2,
            strokeOpacity: 0,
            tooltipText:
                '{category}\nTotal SPK: {Estimasi}\nRealisasi: {Realisasi} ({percent}%)',
        });

        realisasiSeries.columns.template.adapters.add(
            'visible',
            (visible, target) => {
                const context = target.dataItem?.dataContext as
                    | (typeof data)[number]
                    | undefined;

                return Number(context?.Realisasi ?? 0) > 0 ? visible : false;
            },
        );

        const realisasiFill = (
            _fill: unknown,
            target: am5.RoundedRectangle,
        ): am5.Color => {
            const context = target.dataItem?.dataContext as
                | (typeof data)[number]
                | undefined;
            const estimasi = Number(context?.Estimasi ?? 0);
            const realisasi = Number(context?.Realisasi ?? 0);
            const met = estimasi > 0 && realisasi >= estimasi;

            return am5.color(met ? REALISASI_MET_COLOR : REALISASI_SHORT_COLOR);
        };

        realisasiSeries.columns.template.adapters.add('fill', realisasiFill);
        realisasiSeries.columns.template.adapters.add('stroke', realisasiFill);

        realisasiSeries.columns.template.adapters.add(
            'tooltipText',
            (_text, target) => {
                const context = target.dataItem?.dataContext as
                    | (typeof data)[number]
                    | undefined;
                const category = String(context?.category ?? '');
                const estimasi = Number(context?.Estimasi ?? 0);
                const realisasi = Number(context?.Realisasi ?? 0);
                const percent = Number(context?.percent ?? 0);
                const met = estimasi > 0 && realisasi >= estimasi;
                const status = met ? 'capai target' : 'belum capai target';

                return `${category}\nTotal SPK: ${estimasi}\nRealisasi: ${realisasi} (${percent}% · ${status})`;
            },
        );

        // Label %: di dalam bar bila cukup tinggi; di atas bar bila pendek.
        realisasiSeries.bullets.push((bulletRoot, _series, dataItem) => {
            const context = dataItem.dataContext as (typeof data)[number];
            const realisasi = Number(context?.Realisasi ?? 0);
            const inside = maxValue > 0 && realisasi / maxValue >= 0.14;
            const met =
                Number(context?.Estimasi ?? 0) > 0 &&
                realisasi >= Number(context?.Estimasi ?? 0);

            const label = am5.Label.new(bulletRoot, {
                text: realisasi > 0 ? `${context.percent}%` : '',
                centerX: am5.p50,
                centerY: inside ? am5.p50 : am5.p100,
                dy: inside ? 0 : -4,
                fontSize: 9,
                fontWeight: '700',
                fill: am5.color(
                    inside
                        ? 0xffffff
                        : met
                          ? REALISASI_MET_COLOR
                          : REALISASI_SHORT_COLOR,
                ),
            });

            return am5.Bullet.new(bulletRoot, {
                locationY: inside ? 0.82 : 1,
                sprite: label,
            });
        });

        realisasiSeries.data.setAll(data);
        realisasiSeries.appear();

        // Planning estimasi — line biru SAP
        const estimasiSeries = chart.series.push(
            am5xy.LineSeries.new(root, {
                name: 'Estimasi',
                xAxis,
                yAxis,
                valueYField: 'Estimasi',
                categoryXField: 'category',
                stroke: am5.color(ESTIMASI_COLOR),
                fill: am5.color(ESTIMASI_COLOR),
                tooltip: am5.Tooltip.new(root, {
                    labelText: '{categoryX} · Planning estimasi: {valueY} SPK',
                }),
            }),
        );

        estimasiSeries.strokes.template.setAll({
            strokeWidth: 2.5,
            stroke: am5.color(ESTIMASI_COLOR),
        });

        estimasiSeries.bullets.push(() =>
            am5.Bullet.new(root, {
                sprite: am5.Circle.new(root, {
                    radius: 4,
                    fill: am5.color(ESTIMASI_COLOR),
                    stroke: am5.color(0xffffff),
                    strokeWidth: 1.5,
                    tooltipText:
                        '{categoryX} · Planning estimasi: {valueY} SPK',
                }),
            }),
        );

        estimasiSeries.data.setAll(data);
        estimasiSeries.appear(800);

        xAxis.data.setAll(data);
        chart.appear(800, 100);

        return () => {
            root.dispose();
        };
    }, [data]);

    if (data.length === 0) {
        return (
            <p className="dashEmpty">
                Belum ada planning estimasi untuk periode ini.
            </p>
        );
    }

    return (
        <div className="dashForecastChartWrap">
            <div ref={chartRef} className="dashForecastChart" />
            <ul className="dashForecastLegend" aria-label="Legenda planning">
                {LEGEND_ITEMS.map((item) => (
                    <li key={item.label} className="dashForecastLegendItem">
                        <span
                            className={`dashForecastLegendSwatch is-${item.variant}`}
                            style={{ backgroundColor: item.color }}
                            aria-hidden="true"
                        />
                        <span className="dashForecastLegendLabel">
                            {item.label}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
