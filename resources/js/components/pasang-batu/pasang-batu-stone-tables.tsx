export type PasangBatuStones = {
    setting: Array<{
        batu: string;
        pcs: number | string | null;
        crt: number | string | null;
    }>;
    return: Array<{
        batu: string;
        pcs: number | string | null;
        crt: number | string | null;
    }>;
    diamonds: Array<{
        kode: string;
        diamond: string;
        bentuk: string;
        sertifikat: string;
        crt: number | string | null;
    }>;
    mounted: Array<{
        kode: string;
        shape: string;
        pcs: number | string | null;
        crt: number | string | null;
        size: string;
    }>;
};

function displayCell(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text !== '' ? text : '—';
}

function StoneBatchTable({
    title,
    columns,
    rows,
}: {
    title: string;
    columns: string[];
    rows: string[][];
}) {
    return (
        <div className="spkStoneBatchPanel">
            <div className="spkCoranDetailLabel">{title}</div>
            <table className="spkCoranMaterialTable">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column} scope="col">
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="spkCoranBreakdownEmpty"
                            >
                                Tidak ada data
                            </td>
                        </tr>
                    ) : (
                        rows.map((row, index) => (
                            <tr
                                key={`${title}-${index}`}
                                className="spkCoranLineRow"
                            >
                                {row.map((cell, cellIndex) => (
                                    <td key={`${title}-${index}-${cellIndex}`}>
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

export function PasangBatuStoneTables({
    stones,
}: {
    stones: PasangBatuStones;
}) {
    return (
        <div className="spkProcessInfoItem is-coran-materials">
            <div className="spkCoranDetailLabel">Detail Batch Pasang Batu</div>
            <div className="spkStoneBatchGrid">
                <StoneBatchTable
                    title="Setting Batu"
                    columns={['Batu', 'Pcs', 'Crt']}
                    rows={stones.setting.map((row) => [
                        displayCell(row.batu),
                        displayCell(row.pcs),
                        displayCell(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Retur Batu"
                    columns={['Batu', 'Pcs', 'Crt']}
                    rows={stones.return.map((row) => [
                        displayCell(row.batu),
                        displayCell(row.pcs),
                        displayCell(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Diamond"
                    columns={['Kode', 'Diamond', 'Bentuk', 'Sertifikat', 'Crt']}
                    rows={stones.diamonds.map((row) => [
                        displayCell(row.kode),
                        displayCell(row.diamond),
                        displayCell(row.bentuk),
                        displayCell(row.sertifikat),
                        displayCell(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Batu terpasang"
                    columns={['Kode Diamond', 'Shape', 'Pcs', 'Crt', 'Size']}
                    rows={stones.mounted.map((row) => [
                        displayCell(row.kode),
                        displayCell(row.shape),
                        displayCell(row.pcs),
                        displayCell(row.crt),
                        displayCell(row.size),
                    ])}
                />
            </div>
        </div>
    );
}
