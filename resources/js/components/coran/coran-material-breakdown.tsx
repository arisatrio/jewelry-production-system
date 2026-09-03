import { Fragment } from 'react';
import { formatGram } from '@/lib/utils';

export type CoranBreakdownLine = {
    name: string;
    weight: number;
};

export type CoranBreakdownSection = {
    color: string;
    colorKey: string;
    bahan: CoranBreakdownLine[];
    sisa: CoranBreakdownLine[];
};

export type CoranMaterialLine = {
    name: string;
    weight: number | string;
};

const CORAN_MATERIAL_ORDER = [
    'Rose Gold',
    'White Gold',
    'Yellow Gold',
] as const;

function toNumber(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isNaN(numeric) ? null : numeric;
}

function CoranSideTable({
    title,
    sections,
    totalsByColor,
    total,
}: {
    title: string;
    sections: Array<{
        color: string;
        colorKey: string;
        lines: CoranBreakdownLine[];
    }>;
    totalsByColor: Map<string, number | null>;
    total: number | null;
}) {
    const visibleSections = sections.filter(
        (section) =>
            section.lines.length > 0 ||
            (totalsByColor.get(section.color) ?? null) !== null,
    );

    return (
        <table className="spkCoranMaterialTable">
            <thead>
                <tr>
                    <th scope="col">{title}</th>
                    <th scope="col">Berat</th>
                </tr>
            </thead>
            <tbody>
                {visibleSections.length === 0 ? (
                    <tr>
                        <td colSpan={2} className="spkCoranBreakdownEmpty">
                            Tidak ada data
                        </td>
                    </tr>
                ) : (
                    visibleSections.map((section) => {
                        const categoryTotal =
                            totalsByColor.get(section.color) ?? null;

                        return (
                            <Fragment key={section.colorKey}>
                                <tr className="spkCoranCategoryRow">
                                    <th scope="row">{section.color}</th>
                                    <td>{formatGram(categoryTotal)}</td>
                                </tr>
                                {section.lines.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={2}
                                            className="spkCoranBreakdownEmpty"
                                        >
                                            Tidak ada data
                                        </td>
                                    </tr>
                                ) : (
                                    section.lines.map((line, index) => (
                                        <tr
                                            key={`${section.colorKey}-${line.name}-${index}`}
                                            className="spkCoranLineRow"
                                        >
                                            <td>{line.name}</td>
                                            <td>{formatGram(line.weight)}</td>
                                        </tr>
                                    ))
                                )}
                            </Fragment>
                        );
                    })
                )}
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Total</th>
                    <td>{formatGram(total)}</td>
                </tr>
            </tfoot>
        </table>
    );
}

export function CoranMaterialBreakdownTables({
    label = 'Detail Batch Coran',
    breakdown,
    submitMaterials,
    resultMaterials,
    totalSubmit,
    totalResult,
}: {
    label?: string;
    breakdown: CoranBreakdownSection[];
    submitMaterials: CoranMaterialLine[];
    resultMaterials: CoranMaterialLine[];
    totalSubmit: number | string | null;
    totalResult: number | string | null;
}) {
    const submitByColor = new Map<string, number | null>();
    const resultByColor = new Map<string, number | null>();

    for (const name of CORAN_MATERIAL_ORDER) {
        submitByColor.set(name, null);
        resultByColor.set(name, null);
    }

    for (const line of submitMaterials) {
        submitByColor.set(line.name, toNumber(line.weight));
    }

    for (const line of resultMaterials) {
        resultByColor.set(line.name, toNumber(line.weight));
    }

    const sections =
        breakdown.length > 0
            ? breakdown
            : CORAN_MATERIAL_ORDER.map((color) => ({
                  color,
                  colorKey: color.toLowerCase().replace(' ', ''),
                  bahan: [] as CoranBreakdownLine[],
                  sisa: [] as CoranBreakdownLine[],
              }));

    return (
        <div className="spkProcessInfoItem is-coran-materials">
            {label ? (
                <div className="spkCoranDetailLabel">{label}</div>
            ) : null}
            <div className="spkCoranMaterialSplit">
                <CoranSideTable
                    title="Bahan"
                    sections={sections.map((section) => ({
                        color: section.color,
                        colorKey: section.colorKey,
                        lines: section.bahan,
                    }))}
                    totalsByColor={submitByColor}
                    total={toNumber(totalSubmit)}
                />
                <CoranSideTable
                    title="Sisa"
                    sections={sections.map((section) => ({
                        color: section.color,
                        colorKey: section.colorKey,
                        lines: section.sisa,
                    }))}
                    totalsByColor={resultByColor}
                    total={toNumber(totalResult)}
                />
            </div>
        </div>
    );
}
