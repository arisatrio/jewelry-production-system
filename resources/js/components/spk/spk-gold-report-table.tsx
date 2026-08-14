import type { SpkGoldReport } from '@/components/spk/types';
import { Text } from '@ui5/webcomponents-react/Text';
import { Title } from '@ui5/webcomponents-react/Title';
import { formatGram } from '@/lib/utils';

type SpkGoldReportTableProps = {
    report: SpkGoldReport;
};

function formatMetric(value: string | null): string {
    return value === null ? '—' : formatGram(value);
}

export function SpkGoldReportTable({ report }: SpkGoldReportTableProps) {
    const summaryMetrics = [
        { key: 'issued', label: 'Emas Serah', value: report.issued },
        { key: 'returned', label: 'Emas Kembali', value: report.returned },
        { key: 'used', label: 'Emas Terpakai', value: report.used },
        { key: 'diff', label: 'Selisih Emas', value: report.difference },
    ] as const;

    return (
        <div className="spkShrinkReport">
            <div className="spkShrinkReportHeader">
                <div className="spkShrinkReportHeading">
                    <Title level="H5">Laporan Emas</Title>
                    <Text className="spkShrinkReportMeta">
                        {report.materials.length} transaksi bahan
                    </Text>
                </div>
            </div>

            <div className="spkShrinkSummaryGrid" aria-label="Ringkasan emas">
                {summaryMetrics.map((metric) => (
                    <div
                        key={metric.key}
                        className={`spkShrinkSummaryItem is-${metric.key}`}
                    >
                        <span className="spkShrinkSummaryLabel">
                            {metric.label}
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatMetric(metric.value)}
                        </strong>
                    </div>
                ))}
            </div>

            <div className="spkShrinkReportTableWrap">
                <table className="spkShrinkReportTable">
                    <thead>
                        <tr>
                            <th className="spkShrinkColNo">No</th>
                            <th className="spkShrinkColProcess">Bahan</th>
                            <th className="spkShrinkColDate">Tipe</th>
                            <th className="spkShrinkColValue">Berat (g)</th>
                            <th className="spkShrinkColProcess">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.materials.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="spkShrinkReportEmpty"
                                >
                                    Belum ada data emas serah/kembali untuk SPK
                                    ini.
                                </td>
                            </tr>
                        ) : (
                            report.materials.map((row) => (
                                <tr key={`${row.no}-${row.name}-${row.type}`}>
                                    <td className="spkShrinkColNo">
                                        {row.no}
                                    </td>
                                    <td className="spkShrinkColProcess">
                                        {row.name}
                                    </td>
                                    <td className="spkShrinkColDate">
                                        <span
                                            className={`spkGoldTypeBadge is-${row.type.toLowerCase()}`}
                                        >
                                            {row.type}
                                        </span>
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatGram(row.weight)}
                                    </td>
                                    <td className="spkShrinkColProcess">
                                        {row.notes ?? '—'}
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
