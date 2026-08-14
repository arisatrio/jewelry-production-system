import type { SpkShrinkReport } from '@/components/spk/types';
import { Text } from '@ui5/webcomponents-react/Text';
import { Title } from '@ui5/webcomponents-react/Title';
import { formatGram } from '@/lib/utils';

type SpkShrinkReportTableProps = {
    report: SpkShrinkReport;
};

function formatMetric(
    value: string | null,
    percent: string | null = null,
): string {
    if (value === null) {
        return '—';
    }

    const gram = formatGram(value);

    if (percent === null) {
        return gram;
    }

    return `${gram} (${percent}%)`;
}

function formatPercent(value: string | null): string {
    return value === null ? '—' : `${value}%`;
}

export function SpkShrinkReportTable({ report }: SpkShrinkReportTableProps) {
    const summaryMetrics = [
        {
            key: 'planning',
            label: 'Berat Planning',
            value: report.planningWeight,
            percent: null,
        },
        {
            key: 'start',
            label: 'Berat Awal Proses',
            value: report.startWeight,
            percent: null,
        },
        {
            key: 'end',
            label: 'Berat Akhir Proses',
            value: report.endWeight,
            percent: null,
        },
        {
            key: 'shrink',
            label: 'Total Susut Proses',
            value: report.totalShrink,
            percent: report.totalShrinkPercent,
        },
        {
            key: 'lost',
            label: 'Selisih Planning & Akhir Proses',
            value: report.totalLost,
            percent: report.totalLostPercent,
        },
    ] as const;

    return (
        <div className="spkShrinkReport">
            <div className="spkShrinkReportHeader">
                <div className="spkShrinkReportHeading">
                    <Title level="H5">Laporan Susut</Title>
                    <Text className="spkShrinkReportMeta">
                        {report.rows.length} proses tercatat
                    </Text>
                </div>
            </div>

            <div className="spkShrinkSummaryGrid" aria-label="Ringkasan berat">
                {summaryMetrics.map((metric) => (
                    <div
                        key={metric.key}
                        className={`spkShrinkSummaryItem is-${metric.key}`}
                    >
                        <span className="spkShrinkSummaryLabel">
                            {metric.label}
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatMetric(metric.value, metric.percent)}
                        </strong>
                    </div>
                ))}
            </div>

            <div className="spkShrinkReportTableWrap">
                <table className="spkShrinkReportTable">
                    <thead>
                        <tr>
                            <th className="spkShrinkColNo">No</th>
                            <th className="spkShrinkColProcess">Proses</th>
                            <th className="spkShrinkColDate">Tanggal Setor</th>
                            <th className="spkShrinkColValue">Berat Awal (g)</th>
                            <th className="spkShrinkColValue">
                                Berat Akhir (g)
                            </th>
                            <th className="spkShrinkColValue">Susut (g)</th>
                            <th className="spkShrinkColValue">% Susut</th>
                            <th className="spkShrinkColValue">Toleransi</th>
                            <th className="spkShrinkColStatus">
                                Status vs Toleransi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={9}
                                    className="spkShrinkReportEmpty"
                                >
                                    Belum ada data susut untuk SPK ini.
                                </td>
                            </tr>
                        ) : (
                            report.rows.map((row) => (
                                <tr
                                    key={`${row.no}-${row.process}-${row.setorDate}`}
                                >
                                    <td className="spkShrinkColNo">
                                        {row.no}
                                    </td>
                                    <td className="spkShrinkColProcess">
                                        {row.process}
                                    </td>
                                    <td className="spkShrinkColDate">
                                        {row.setorDate}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {row.startWeight === null
                                            ? '—'
                                            : formatGram(row.startWeight)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {row.endWeight === null
                                            ? '—'
                                            : formatGram(row.endWeight)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatGram(row.shrink)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatPercent(row.shrinkPercent)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatPercent(row.tolerance)}
                                    </td>
                                    <td className="spkShrinkColStatus">
                                        {row.toleranceStatus === null ? (
                                            '—'
                                        ) : (
                                            <span
                                                className={`spkToleranceStatus is-${row.toleranceStatus.toLowerCase()}`}
                                            >
                                                {row.toleranceStatus}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
