import {
    formatProcessCellValue,
    processColumnLabel,
} from '@/components/spk/spk-process-display';

type SpkProcessTableBodyProps = {
    table: string;
    columns: string[];
    records: Array<Record<string, unknown>>;
};

/**
 * Table (column-header) layout for process records.
 * Kept available so the process panel can switch back from card view.
 */
export function SpkProcessTableBody({
    table,
    columns,
    records,
}: SpkProcessTableBodyProps) {
    return (
        <div className="spkProcessTableWrap">
            <table className="spkProcessTable">
                <thead>
                    <tr>
                        <th className="spkProcessColNo">#</th>
                        {columns.map((column) => (
                            <th key={column}>{processColumnLabel(column)}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {records.map((record, index) => (
                        <tr key={`${table}-${index}`}>
                            <td className="spkProcessColNo">{index + 1}</td>
                            {columns.map((column) => (
                                <td key={column}>
                                    {formatProcessCellValue(
                                        record[column],
                                        column,
                                        record,
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
