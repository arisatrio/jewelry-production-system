import type { SpkProductionControlReport } from '@/components/spk/types';
import { Text } from '@ui5/webcomponents-react/Text';
import { Title } from '@ui5/webcomponents-react/Title';
import { formatGram } from '@/lib/utils';

type SpkProductionControlReportSectionProps = {
    report: SpkProductionControlReport;
};

function formatWeight(value: string | null): string {
    return value === null ? '—' : formatGram(value);
}

function formatPercent(value: string | null): string {
    return value === null ? '—' : `${value}%`;
}

export function SpkProductionControlReportSection({
    report,
}: SpkProductionControlReportSectionProps) {
    const { leadTime, idleTimes, yieldPlanning } = report;

    return (
        <div className="spkShrinkReport spkControlReport">
            <div className="spkShrinkReportHeader">
                <div className="spkShrinkReportHeading">
                    <Title level="H5">Laporan Kontrol Produksi</Title>
                    <Text className="spkShrinkReportMeta">
                        Lead time, idle time, dan yield planning
                    </Text>
                </div>
            </div>

            <section
                className="spkControlSection"
                aria-label="Lead time SPK"
            >
                <h3 className="spkControlSectionTitle">Lead Time SPK</h3>
                <div className="spkShrinkSummaryGrid">
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">Mulai</span>
                        <strong className="spkShrinkSummaryValue">
                            {leadTime.startDate ?? '—'}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">Selesai</span>
                        <strong className="spkShrinkSummaryValue">
                            {leadTime.endDate ?? '—'}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">
                            Durasi Aktual
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {leadTime.durationLabel ?? '—'}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">
                            Estimasi (hari)
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {leadTime.estimatedDays ?? '—'}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem is-lost">
                        <span className="spkShrinkSummaryLabel">
                            Vs Estimasi
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {leadTime.varianceLabel ?? '—'}
                        </strong>
                    </div>
                </div>
            </section>

            <section
                className="spkControlSection"
                aria-label="Idle time antar proses"
            >
                <h3 className="spkControlSectionTitle">
                    Idle Time Antar Proses
                </h3>
                <div className="spkShrinkReportTableWrap">
                    <table className="spkShrinkReportTable">
                        <thead>
                            <tr>
                                <th className="spkShrinkColNo">No</th>
                                <th className="spkShrinkColProcess">Dari</th>
                                <th className="spkShrinkColProcess">Ke</th>
                                <th className="spkShrinkColDate">Selesai</th>
                                <th className="spkShrinkColDate">Mulai</th>
                                <th className="spkShrinkColValue">Idle</th>
                            </tr>
                        </thead>
                        <tbody>
                            {idleTimes.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="spkShrinkReportEmpty"
                                    >
                                        Belum cukup data proses untuk menghitung
                                        idle time.
                                    </td>
                                </tr>
                            ) : (
                                idleTimes.map((row) => (
                                    <tr
                                        key={`${row.no}-${row.fromProcess}-${row.toProcess}`}
                                    >
                                        <td className="spkShrinkColNo">
                                            {row.no}
                                        </td>
                                        <td className="spkShrinkColProcess">
                                            {row.fromProcess}
                                        </td>
                                        <td className="spkShrinkColProcess">
                                            {row.toProcess}
                                        </td>
                                        <td className="spkShrinkColDate">
                                            {row.fromDate ?? '—'}
                                        </td>
                                        <td className="spkShrinkColDate">
                                            {row.toDate ?? '—'}
                                        </td>
                                        <td className="spkShrinkColValue">
                                            {row.idleLabel ?? '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                className="spkControlSection"
                aria-label="Yield planning"
            >
                <h3 className="spkControlSectionTitle">Yield Planning</h3>
                <div className="spkShrinkSummaryGrid">
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">
                            Berat Planning
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatWeight(yieldPlanning.planningWeight)}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">
                            Berat Akhir
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatWeight(yieldPlanning.endWeight)}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem is-shrink">
                        <span className="spkShrinkSummaryLabel">
                            Yield Berat
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatPercent(yieldPlanning.yieldPercent)}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem">
                        <span className="spkShrinkSummaryLabel">
                            Emas Terpakai
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatWeight(yieldPlanning.goldUsed)}
                        </strong>
                    </div>
                    <div className="spkShrinkSummaryItem is-lost">
                        <span className="spkShrinkSummaryLabel">
                            Yield Emas
                        </span>
                        <strong className="spkShrinkSummaryValue">
                            {formatPercent(yieldPlanning.goldYieldPercent)}
                        </strong>
                    </div>
                </div>
            </section>
        </div>
    );
}
