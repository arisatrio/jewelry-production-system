import { formatGram } from '@/lib/utils';

export type FinishingMaterialLine = {
    name: string;
    weight: number;
    notes: string | null;
};

export type FinishingMaterials = {
    bahan: FinishingMaterialLine[];
    sisa: FinishingMaterialLine[];
};

function FinishingSideTable({
    title,
    lines,
    total,
}: {
    title: string;
    lines: FinishingMaterialLine[];
    total: number | null;
}) {
    return (
        <table className="spkCoranMaterialTable">
            <thead>
                <tr>
                    <th scope="col">{title}</th>
                    <th scope="col">Berat</th>
                </tr>
            </thead>
            <tbody>
                {lines.length === 0 ? (
                    <tr>
                        <td colSpan={2} className="spkCoranBreakdownEmpty">
                            Tidak ada data
                        </td>
                    </tr>
                ) : (
                    lines.map((line, index) => (
                        <tr
                            key={`${line.name}-${index}`}
                            className="spkCoranLineRow"
                        >
                            <td>
                                {line.name}
                                {line.notes ? (
                                    <span className="spkProcessMaterialNotes">
                                        {' '}
                                        · {line.notes}
                                    </span>
                                ) : null}
                            </td>
                            <td>{formatGram(line.weight)}</td>
                        </tr>
                    ))
                )}
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Total</th>
                    <td>
                        {total === null || Number.isNaN(total)
                            ? '—'
                            : formatGram(total)}
                    </td>
                </tr>
            </tfoot>
        </table>
    );
}

function toNumber(value: string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = Number(value);

    return Number.isNaN(numeric) ? null : numeric;
}

export function FinishingMaterialTables({
    materials,
    submitMaterial,
    resultMaterial,
}: {
    materials: FinishingMaterials;
    submitMaterial: string | null;
    resultMaterial: string | null;
}) {
    return (
        <div className="spkProcessInfoItem is-coran-materials">
            <div className="spkCoranDetailLabel">Detail Batch Finishing</div>
            <div className="spkCoranMaterialSplit">
                <FinishingSideTable
                    title="Bahan"
                    lines={materials.bahan}
                    total={toNumber(submitMaterial)}
                />
                <FinishingSideTable
                    title="Sisa"
                    lines={materials.sisa}
                    total={toNumber(resultMaterial)}
                />
            </div>
        </div>
    );
}
