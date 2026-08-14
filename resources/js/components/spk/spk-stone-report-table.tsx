import type { SpkStoneReport } from '@/components/spk/types';
import { Text } from '@ui5/webcomponents-react/Text';
import { Title } from '@ui5/webcomponents-react/Title';

type SpkStoneReportTableProps = {
    report: SpkStoneReport;
};

function formatCrt(value: string | null): string {
    return value === null ? '—' : `${value} crt`;
}

function formatPcs(value: number | null): string {
    return value === null ? '—' : String(value);
}

export function SpkStoneReportTable({ report }: SpkStoneReportTableProps) {
    const summaryMetrics = [
        {
            key: 'start',
            label: 'Berat Batu Awal',
            value: report.totalStartCrt,
        },
        { key: 'end', label: 'Berat Batu Akhir', value: report.totalEndCrt },
        {
            key: 'diff',
            label: 'Selisih',
            value: report.totalDifference,
        },
    ] as const;

    return (
        <div className="spkShrinkReport">
            <div className="spkShrinkReportHeader">
                <div className="spkShrinkReportHeading">
                    <Title level="H5">Laporan Batu</Title>
                    <Text className="spkShrinkReportMeta">
                        {report.rows.length} jenis batu tercatat
                    </Text>
                </div>
            </div>

            <div className="spkShrinkSummaryGrid" aria-label="Ringkasan batu">
                {summaryMetrics.map((metric) => (
                    <div
                        key={metric.key}
                        className={`spkShrinkSummaryItem is-${metric.key}`}
                    >
                        <span className="spkShrinkSummaryLabel">
                            {metric.label}
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatCrt(metric.value)}
                        </strong>
                    </div>
                ))}
            </div>

            <div className="spkShrinkReportTableWrap">
                <table className="spkShrinkReportTable">
                    <thead>
                        <tr>
                            <th className="spkShrinkColNo">No</th>
                            <th className="spkShrinkColProcess">Batu</th>
                            <th className="spkShrinkColValue">Pcs Awal</th>
                            <th className="spkShrinkColValue">Pcs Akhir</th>
                            <th className="spkShrinkColValue">
                                Berat Awal (crt)
                            </th>
                            <th className="spkShrinkColValue">
                                Berat Akhir (crt)
                            </th>
                            <th className="spkShrinkColValue">Selisih (crt)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="spkShrinkReportEmpty"
                                >
                                    Belum ada data batu awal/akhir untuk SPK
                                    ini.
                                </td>
                            </tr>
                        ) : (
                            report.rows.map((row) => (
                                <tr key={`${row.no}-${row.stone}`}>
                                    <td className="spkShrinkColNo">
                                        {row.no}
                                    </td>
                                    <td className="spkShrinkColProcess">
                                        {row.stone}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatPcs(row.pcsStart)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {formatPcs(row.pcsEnd)}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {row.startCrt ?? '—'}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {row.endCrt ?? '—'}
                                    </td>
                                    <td className="spkShrinkColValue">
                                        {row.difference ?? '—'}
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
