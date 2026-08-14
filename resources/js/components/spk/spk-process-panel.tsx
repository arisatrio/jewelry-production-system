import { Text } from '@ui5/webcomponents-react/Text';
import { SpkProcessCardBody } from '@/components/spk/spk-process-card-body';
import { resolveProcessColumns } from '@/components/spk/spk-process-display';
import type { SpkProcessTab } from '@/components/spk/types';

type SpkProcessPanelProps = {
    process: SpkProcessTab;
};

export function SpkProcessPanel({ process }: SpkProcessPanelProps) {
    const sourcesWithRecords = process.sources.filter(
        (source) => source.recordCount > 0,
    );

    if (process.tables.length === 0) {
        return (
            <div className="spkProcessTabContent">
                <div className="spkProcessEmpty">
                    <Text>
                        Belum ada tabel yang dipetakan untuk proses ini.
                    </Text>
                </div>
            </div>
        );
    }

    if (sourcesWithRecords.length === 0) {
        return (
            <div className="spkProcessTabContent">
                <div className="spkProcessEmpty">
                    <Text>Tidak ada data untuk proses ini.</Text>
                </div>
            </div>
        );
    }

    return (
        <div className="spkProcessTabContent">
            {sourcesWithRecords.map((source) => {
                const columns = resolveProcessColumns(
                    source.records,
                    process.key,
                );

                return (
                    <div key={source.table} className="spkProcessSource">
                        <SpkProcessCardBody
                            table={source.table}
                            processLabel={process.label}
                            columns={columns}
                            records={source.records}
                        />
                    </div>
                );
            })}
        </div>
    );
}
