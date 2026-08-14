import { formatGram } from '@/lib/utils';
import { Fragment } from 'react';
import { ArrowLeft, Check } from 'lucide-react';
import {
    formatProcessCellValue,
    hasProcessValue,
    isKoreksiQcActive,
    processColumnLabel,
    processMaterialLines,
    processRecordCategory,
    processRecordSubtitle,
    processRecordTitle,
    processRecordWorkDate,
    resolveProcessCardGroups,
    shouldShowProcessField,
    SHRINK_VALUE_COLUMNS,
    type ProcessCardGroup,
    type ProcessMaterialLine,
} from '@/components/spk/spk-process-display';
import {
    resolveProcessQcStatus,
    SpkQcStatusBadge,
} from '@/components/spk/spk-qc-status-badge';

type SpkProcessCardBodyProps = {
    table: string;
    processLabel: string;
    columns: string[];
    records: Array<Record<string, unknown>>;
};

const MATERIAL_TOTAL_COLUMNS = new Set([
    'submit_materialgold',
    'result_materialgold',
]);

const CORAN_MATERIAL_COLUMNS = new Set([
    'total_submit_material',
    'total_result_material',
]);

const DETAIL_SECTION_KEYS = new Set([
    'weight',
    'gold',
    'shrink',
    'identity',
    'schedule',
    'other',
]);

const HEADER_CRAFTSMAN_FIELDS = new Set(['pengrajin', 'craftsman_name']);

const FINISHING_BODY_HIDDEN_FIELDS = new Set([
    'tanggal',
    'pengrajin',
    'craftsman_name',
]);

const CORAN_MATERIAL_ORDER = [
    'Rose Gold',
    'White Gold',
    'Yellow Gold',
] as const;

function resolveHeaderCraftsman(
    record: Record<string, unknown>,
): string | null {
    if (hasProcessValue(record.pengrajin)) {
        return String(record.pengrajin);
    }

    if (hasProcessValue(record.craftsman_name)) {
        return String(record.craftsman_name);
    }

    if (hasProcessValue(record.craftsman_id)) {
        return `Pengrajin ${String(record.craftsman_id)}`;
    }

    return null;
}

function formatOptionalGram(weight: number | null): string {
    return formatGram(weight);
}

function resolveMaterialShrink(
    submit: number | null,
    result: number | null,
): number | null {
    if (submit === null && result === null) {
        return null;
    }

    return roundGram((submit ?? 0) - (result ?? 0));
}

function roundGram(value: number): number {
    return Math.round(value * 1000) / 1000;
}

type CoranMaterialPair = {
    name: string;
    submit: number | null;
    result: number | null;
};

function resolveCoranMaterialPairs(
    record: Record<string, unknown>,
): CoranMaterialPair[] {
    const submitLines = processMaterialLines(record.submit_materials);
    const resultLines = processMaterialLines(record.result_materials);
    const weights = new Map<string, CoranMaterialPair>();

    for (const line of submitLines) {
        weights.set(line.name, {
            name: line.name,
            submit: line.weight,
            result: null,
        });
    }

    for (const line of resultLines) {
        const existing = weights.get(line.name);

        if (existing) {
            existing.result = line.weight;
        } else {
            weights.set(line.name, {
                name: line.name,
                submit: null,
                result: line.weight,
            });
        }
    }

    const ordered: CoranMaterialPair[] = [];

    for (const name of CORAN_MATERIAL_ORDER) {
        const pair = weights.get(name);

        if (pair) {
            ordered.push(pair);
            weights.delete(name);
        }
    }

    return [...ordered, ...weights.values()];
}

type CoranBreakdownLine = {
    name: string;
    weight: number;
};

type CoranBreakdownSection = {
    color: string;
    colorKey: string;
    bahan: CoranBreakdownLine[];
    sisa: CoranBreakdownLine[];
};

function resolveCoranBreakdown(
    value: unknown,
): CoranBreakdownSection[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.flatMap((section) => {
        if (!section || typeof section !== 'object') {
            return [];
        }

        const row = section as Record<string, unknown>;
        const color = typeof row.color === 'string' ? row.color : '';
        const colorKey = typeof row.colorKey === 'string' ? row.colorKey : '';

        if (color === '' || colorKey === '') {
            return [];
        }

        return [
            {
                color,
                colorKey,
                bahan: processMaterialLines(row.bahan).map((line) => ({
                    name: line.name,
                    weight: line.weight,
                })),
                sisa: processMaterialLines(row.sisa).map((line) => ({
                    name: line.name,
                    weight: line.weight,
                })),
            },
        ];
    });
}

function CoranSideTable({
    title,
    sections,
    totalsByColor,
    total,
    footerLabel = 'Total',
}: {
    title: string;
    sections: Array<{
        color: string;
        colorKey: string;
        lines: CoranBreakdownLine[];
    }>;
    totalsByColor: Map<string, number | null>;
    total: number | null;
    footerLabel?: string;
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
                                    <td>
                                        {formatOptionalGram(categoryTotal)}
                                    </td>
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
                    <th scope="row">{footerLabel}</th>
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

function CoranMaterialCompare({
    record,
}: {
    record: Record<string, unknown>;
}) {
    const pairs = resolveCoranMaterialPairs(record);
    const breakdown = resolveCoranBreakdown(record.coran_breakdown);
    const submitByColor = new Map(
        pairs.map((pair) => [pair.name, pair.submit] as const),
    );
    const resultByColor = new Map(
        pairs.map((pair) => [pair.name, pair.result] as const),
    );
    const submitTotal =
        record.total_submit_material === null ||
        record.total_submit_material === undefined ||
        record.total_submit_material === ''
            ? null
            : Number(record.total_submit_material);
    const resultTotal =
        record.total_result_material === null ||
        record.total_result_material === undefined ||
        record.total_result_material === ''
            ? null
            : Number(record.total_result_material);

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
            <div className="spkCoranDetailLabel">Detail Batch Coran</div>
            <div className="spkCoranMaterialSplit">
                <CoranSideTable
                    title="Bahan"
                    sections={sections.map((section) => ({
                        color: section.color,
                        colorKey: section.colorKey,
                        lines: section.bahan,
                    }))}
                    totalsByColor={submitByColor}
                    total={
                        submitTotal === null || Number.isNaN(submitTotal)
                            ? null
                            : submitTotal
                    }
                />
                <CoranSideTable
                    title="Sisa"
                    sections={sections.map((section) => ({
                        color: section.color,
                        colorKey: section.colorKey,
                        lines: section.sisa,
                    }))}
                    totalsByColor={resultByColor}
                    total={
                        resultTotal === null || Number.isNaN(resultTotal)
                            ? null
                            : resultTotal
                    }
                />
            </div>
        </div>
    );
}

function DiamondMountingBatch({
    record,
}: {
    record: Record<string, unknown>;
}) {
    const setting = Array.isArray(record.stone_setting)
        ? (record.stone_setting as Array<Record<string, unknown>>)
        : [];
    const stoneReturn = Array.isArray(record.stone_return)
        ? (record.stone_return as Array<Record<string, unknown>>)
        : [];
    const diamonds = Array.isArray(record.stone_diamonds)
        ? (record.stone_diamonds as Array<Record<string, unknown>>)
        : [];
    const mounted = Array.isArray(record.stone_mounted)
        ? (record.stone_mounted as Array<Record<string, unknown>>)
        : [];

    return (
        <div className="spkProcessInfoItem is-coran-materials">
            <div className="spkCoranDetailLabel">Detail Batch Pasang Batu</div>
            <div className="spkStoneBatchGrid">
                <StoneBatchTable
                    title="Setting Batu"
                    columns={['Batu', 'Pcs', 'Crt']}
                    rows={setting.map((row) => [
                        String(row.batu ?? '—'),
                        row.pcs === null || row.pcs === undefined
                            ? '—'
                            : String(row.pcs),
                        row.crt === null || row.crt === undefined
                            ? '—'
                            : String(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Retur Batu"
                    columns={['Batu', 'Pcs', 'Crt']}
                    rows={stoneReturn.map((row) => [
                        String(row.batu ?? '—'),
                        row.pcs === null || row.pcs === undefined
                            ? '—'
                            : String(row.pcs),
                        row.crt === null || row.crt === undefined
                            ? '—'
                            : String(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Diamond"
                    columns={['Kode', 'Diamond', 'Bentuk', 'Sertifikat', 'Crt']}
                    rows={diamonds.map((row) => [
                        String(row.kode ?? '—'),
                        String(row.diamond ?? '—'),
                        String(row.bentuk ?? '—'),
                        String(row.sertifikat ?? '—'),
                        row.crt === null || row.crt === undefined
                            ? '—'
                            : String(row.crt),
                    ])}
                />
                <StoneBatchTable
                    title="Batu terpasang"
                    columns={['Kode Diamond', 'Shape', 'Pcs', 'Crt', 'Size']}
                    rows={mounted.map((row) => [
                        String(row.kode ?? '—'),
                        String(row.shape ?? '—'),
                        row.pcs === null || row.pcs === undefined
                            ? '—'
                            : String(row.pcs),
                        row.crt === null || row.crt === undefined
                            ? '—'
                            : String(row.crt),
                        String(row.size ?? '—'),
                    ])}
                />
            </div>
        </div>
    );
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

function FinishingSideTable({
    title,
    lines,
    total,
}: {
    title: string;
    lines: ProcessMaterialLine[];
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

function FinishingMaterialCompare({
    record,
}: {
    record: Record<string, unknown>;
}) {
    const materialsOut = processMaterialLines(record.materials_out);
    const materialsIn = processMaterialLines(record.materials_in);
    const submitTotal =
        record.submit_materialgold === null ||
        record.submit_materialgold === undefined ||
        record.submit_materialgold === ''
            ? null
            : Number(record.submit_materialgold);
    const resultTotal =
        record.result_materialgold === null ||
        record.result_materialgold === undefined ||
        record.result_materialgold === ''
            ? null
            : Number(record.result_materialgold);

    return (
        <div className="spkProcessInfoItem is-coran-materials">
            <div className="spkCoranDetailLabel">Detail Batch Finishing</div>
            <div className="spkCoranMaterialSplit">
                <FinishingSideTable
                    title="Bahan"
                    lines={materialsOut}
                    total={
                        submitTotal === null || Number.isNaN(submitTotal)
                            ? null
                            : submitTotal
                    }
                />
                <FinishingSideTable
                    title="Sisa"
                    lines={materialsIn}
                    total={
                        resultTotal === null || Number.isNaN(resultTotal)
                            ? null
                            : resultTotal
                    }
                />
            </div>
        </div>
    );
}

function MaterialLines({ lines }: { lines: ProcessMaterialLine[] }) {
    if (lines.length === 0) {
        return null;
    }

    return (
        <ul className="spkProcessMaterialLines">
            {lines.map((line, index) => (
                <li
                    key={`${line.name}-${index}`}
                    className="spkProcessMaterialLine"
                >
                    <span className="spkProcessMaterialName">
                        {line.name}
                        {line.notes ? (
                            <span className="spkProcessMaterialNotes">
                                {' '}
                                · {line.notes}
                            </span>
                        ) : null}
                    </span>
                    <span className="spkProcessMaterialWeight">
                        {formatGram(line.weight)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

const STACKED_COLUMN_LIMIT = 5;

function chunkItems<T>(items: T[], size: number): T[][] {
    if (items.length === 0) {
        return [];
    }

    const chunks: T[][] = [];

    for (let index = 0; index < items.length; index += size) {
        chunks.push(items.slice(index, index + size));
    }

    return chunks;
}

function isMaterialBlockColumn(
    column: string,
    fields: string[],
    hasCoranMaterials: boolean,
    hasFinishingMaterials: boolean,
): boolean {
    if (hasCoranMaterials && column === 'total_submit_material') {
        return true;
    }

    if (
        hasCoranMaterials &&
        column === 'total_result_material' &&
        !fields.includes('total_submit_material')
    ) {
        return true;
    }

    if (hasFinishingMaterials && column === 'submit_materialgold') {
        return true;
    }

    if (
        hasFinishingMaterials &&
        column === 'result_materialgold' &&
        !fields.includes('submit_materialgold')
    ) {
        return true;
    }

    return false;
}

function shouldOmitColumn(
    column: string,
    fields: string[],
    hasCoranMaterials: boolean,
    hasFinishingMaterials: boolean,
): boolean {
    if (
        hasCoranMaterials &&
        column === 'total_result_material' &&
        fields.includes('total_submit_material')
    ) {
        return true;
    }

    if (
        hasFinishingMaterials &&
        column === 'result_materialgold' &&
        fields.includes('submit_materialgold')
    ) {
        return true;
    }

    return false;
}

function FieldRows({
    fields,
    record,
    stacked = false,
    category = null,
}: {
    fields: string[];
    record: Record<string, unknown>;
    stacked?: boolean;
    category?: string | null;
}) {
    const hasCoranMaterials =
        fields.includes('total_submit_material') ||
        fields.includes('total_result_material');
    const hasFinishingMaterials =
        'materials_out' in record || 'materials_in' in record;

    const renderFieldItem = (column: string) => {
        if (CORAN_MATERIAL_COLUMNS.has(column)) {
            if (column === 'total_result_material' && hasCoranMaterials) {
                return null;
            }

            if (column === 'total_submit_material' && hasCoranMaterials) {
                return (
                    <CoranMaterialCompare
                        key="coran-materials"
                        record={record}
                    />
                );
            }

            if (
                column === 'total_result_material' &&
                !fields.includes('total_submit_material')
            ) {
                return (
                    <CoranMaterialCompare
                        key="coran-materials"
                        record={record}
                    />
                );
            }

            return null;
        }

        if (hasFinishingMaterials && MATERIAL_TOTAL_COLUMNS.has(column)) {
            if (
                column === 'result_materialgold' &&
                fields.includes('submit_materialgold')
            ) {
                return null;
            }

            if (column === 'submit_materialgold') {
                return (
                    <FinishingMaterialCompare
                        key="finishing-materials"
                        record={record}
                    />
                );
            }

            if (
                column === 'result_materialgold' &&
                !fields.includes('submit_materialgold')
            ) {
                return (
                    <FinishingMaterialCompare
                        key="finishing-materials"
                        record={record}
                    />
                );
            }
        }

        if (column === 'created_date' || column === 'modified_date') {
            return (
                <div key={column} className="spkProcessInfoItem is-audit">
                    <p className="spkProcessAuditLine">
                        {formatProcessCellValue(
                            record[column],
                            column,
                            record,
                        )}
                    </p>
                </div>
            );
        }

        if (
            column === 'status' ||
            column === 'status_item' ||
            column === 'koreksi_qc'
        ) {
            const qcStatus = resolveProcessQcStatus(record, column);

            return (
                <div key={column} className="spkProcessInfoItem">
                    <dt>{processColumnLabel(column, record)}</dt>
                    <dd className="spkProcessQcStatusValue">
                        {qcStatus ? (
                            <SpkQcStatusBadge status={qcStatus} />
                        ) : (
                            '—'
                        )}
                    </dd>
                </div>
            );
        }

        const materialLines =
            column === 'submit_materialgold'
                ? processMaterialLines(record.materials_out)
                : column === 'result_materialgold'
                  ? processMaterialLines(record.materials_in)
                  : [];

        return (
            <div
                key={column}
                className={
                    materialLines.length > 0
                        ? 'spkProcessInfoItem is-with-breakdown'
                        : 'spkProcessInfoItem'
                }
            >
                <dt>{processColumnLabel(column, record)}</dt>
                <dd
                    className={
                        SHRINK_VALUE_COLUMNS.has(column)
                            ? 'is-shrink'
                            : undefined
                    }
                >
                    {formatProcessCellValue(record[column], column, record)}
                    {MATERIAL_TOTAL_COLUMNS.has(column) ? (
                        <MaterialLines lines={materialLines} />
                    ) : null}
                </dd>
            </div>
        );
    };

    if (!stacked) {
        return (
            <dl className="spkProcessInfoGrid">
                {fields.map((column) => renderFieldItem(column))}
            </dl>
        );
    }

    const summaryFields = fields.filter(
        (column) =>
            !shouldOmitColumn(
                column,
                fields,
                hasCoranMaterials,
                hasFinishingMaterials,
            ) &&
            !isMaterialBlockColumn(
                column,
                fields,
                hasCoranMaterials,
                hasFinishingMaterials,
            ),
    );
    const blockFields = fields.filter((column) =>
        isMaterialBlockColumn(
            column,
            fields,
            hasCoranMaterials,
            hasFinishingMaterials,
        ),
    );

    const summaryItems = [
        ...(category
            ? [
                  <div key="category" className="spkProcessInfoItem">
                      <dt>Kategori</dt>
                      <dd>{category}</dd>
                  </div>,
              ]
            : []),
        ...summaryFields
            .map((column) => renderFieldItem(column))
            .filter((item) => item !== null),
    ];

    const columns = chunkItems(summaryItems, STACKED_COLUMN_LIMIT);

    return (
        <div className="spkProcessStackedLayout">
            {columns.length > 0 ? (
                <div className="spkProcessDetailColumns">
                    {columns.map((columnItems, columnIndex) => (
                        <dl
                            key={`detail-column-${columnIndex}`}
                            className="spkProcessDetailRows"
                        >
                            {columnItems}
                        </dl>
                    ))}
                </div>
            ) : null}

            {blockFields.map((column) => renderFieldItem(column))}

            {'stone_setting' in record || 'stone_mounted' in record ? (
                <DiamondMountingBatch record={record} />
            ) : null}
        </div>
    );
}

function DetailSection({
    group,
    record,
}: {
    group: ProcessCardGroup;
    record: Record<string, unknown>;
}) {
    const visibleFields = group.fields.filter((field) => {
        if (!shouldShowProcessField(field, record)) {
            return false;
        }

        if (group.variant === 'notes' || group.variant === 'meta') {
            return hasProcessValue(record[field]);
        }

        return true;
    });

    if (visibleFields.length === 0) {
        return null;
    }

    if (group.variant === 'notes') {
        return (
            <div className="spkProcessNotesBlock" aria-label={group.label}>
                {visibleFields.map((column) => (
                    <p
                        key={column}
                        className={
                            column === 'koreksi_qc'
                                ? `spkProcessNoteItem is-qc-${
                                      isKoreksiQcActive(record)
                                          ? 'not-ok'
                                          : 'ok'
                                  }`
                                : 'spkProcessNoteItem'
                        }
                    >
                        <span className="spkProcessNoteLabel">
                            {processColumnLabel(column)} :
                        </span>{' '}
                        <span className="spkProcessNoteValue">
                            {formatProcessCellValue(
                                record[column],
                                column,
                                record,
                            )}
                        </span>
                    </p>
                ))}
            </div>
        );
    }

    return (
        <section
            className={`spkProcessCardSection is-${group.variant} is-${group.key}`}
            aria-label={group.label}
        >
            <FieldRows
                fields={visibleFields}
                record={record}
                stacked={DETAIL_SECTION_KEYS.has(group.key)}
            />
        </section>
    );
}

function ProcessApprovalTimeline({
    approvals,
}: {
    approvals: Array<Record<string, unknown>>;
}) {
    if (approvals.length === 0) {
        return null;
    }

    return (
        <section
            className="spkProcessCardSection is-approval"
            aria-label="Approval"
        >
            <div className="spkCoranDetailLabel">Approval</div>
            <ol className="spkApprovalTimeline">
                {approvals.map((row, index) => {
                    const approve = String(row.approve ?? '').toUpperCase();
                    const isOk =
                        approve === 'OK' || approve === 'APPROVED';
                    const isNotOk =
                        approve === 'NOK' ||
                        approve === 'NOT OK' ||
                        approve === 'NOTOK' ||
                        approve === 'REJECTED';
                    const isLast = index === approvals.length - 1;
                    const notes =
                        row.notes === null ||
                        row.notes === undefined ||
                        row.notes === ''
                            ? null
                            : String(row.notes);

                    return (
                        <li
                            key={`${String(row.status ?? 'approval')}-${index}`}
                            className={[
                                'spkApprovalTimelineItem',
                                isOk ? 'is-ok' : '',
                                isNotOk ? 'is-not-ok' : '',
                                isLast ? 'is-last' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            <div
                                className="spkApprovalTimelineMarker"
                                aria-hidden="true"
                            >
                                <span
                                    className="spkApprovalTimelineDot"
                                    title={approve || '—'}
                                >
                                    {isOk ? (
                                        <Check
                                            className="spkApprovalTimelineIcon"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                    {isNotOk ? (
                                        <ArrowLeft
                                            className="spkApprovalTimelineIcon"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                </span>
                            </div>
                            <div className="spkApprovalTimelineContent">
                                <div className="spkApprovalTimelineHeader">
                                    <p className="spkApprovalTimelineStatus">
                                        {String(
                                            row.statusLabel ??
                                                row.status ??
                                                '—',
                                        )}
                                    </p>
                                </div>
                                <p className="spkApprovalTimelineMeta">
                                    {`${String(row.createdBy ?? '—')} · ${String(row.createdAt ?? '—')}`}
                                </p>
                                {notes ? (
                                    <p className="spkApprovalTimelineNotes">
                                        {notes}
                                    </p>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/**
 * Shared process card layout used by all production process tabs.
 * Each card shows the full report inline (header + body).
 */
export function SpkProcessCardBody({
    table,
    processLabel,
    columns,
    records,
}: SpkProcessCardBodyProps) {
    const groups = resolveProcessCardGroups(columns);
    const primaryGroups = groups.filter((group) =>
        DETAIL_SECTION_KEYS.has(group.key),
    );
    const otherGroups = groups.filter(
        (group) => !DETAIL_SECTION_KEYS.has(group.key),
    );
    const title = processRecordTitle(processLabel);
    const isFinishingTable = table === 'finishinghandmade';

    return (
        <div className="spkProcessCardList">
            {records.map((record, index) => {
                const subtitle = processRecordSubtitle(record);
                const headerCraftsman = resolveHeaderCraftsman(record);
                const workDate = processRecordWorkDate(record);

                return (
                    <article
                        key={`${table}-${index}`}
                        className="spkProcessCard"
                        aria-label={title}
                    >
                        <div className="spkProcessCardHeader">
                            <div className="spkProcessCardHeading">
                                <span className="spkProcessCardIndex">
                                    #{index + 1}
                                </span>
                                <div className="spkProcessCardHeadingText">
                                    <span className="spkProcessCardTitle">
                                        {title}
                                    </span>
                                    {subtitle ? (
                                        <span className="spkProcessCardSubtitle">
                                            {subtitle}
                                        </span>
                                    ) : null}
                                </div>
                            </div>

                            <div className="spkProcessCardHeaderMeta">
                                <div className="spkProcessCardHeaderMetaItem">
                                    <span className="spkProcessCardHeaderMetaLabel">
                                        Tanggal
                                    </span>
                                    <span className="spkProcessCardHeaderMetaValue">
                                        {workDate ?? '—'}
                                    </span>
                                </div>
                                <div className="spkProcessCardHeaderMetaItem">
                                    <span className="spkProcessCardHeaderMetaLabel">
                                        Pengrajin
                                    </span>
                                    <span className="spkProcessCardHeaderMetaValue">
                                        {headerCraftsman ?? '—'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="spkProcessCardBody">
                            <ProcessReportCardDetail
                                record={record}
                                primaryGroups={primaryGroups}
                                otherGroups={otherGroups}
                                isFinishingTable={isFinishingTable}
                            />
                        </div>
                    </article>
                );
            })}
        </div>
    );
}

function ProcessReportCardDetail({
    record,
    primaryGroups,
    otherGroups,
    isFinishingTable,
}: {
    record: Record<string, unknown>;
    primaryGroups: ProcessCardGroup[];
    otherGroups: ProcessCardGroup[];
    isFinishingTable: boolean;
}) {
    const category = processRecordCategory(record);
    const approvals = Array.isArray(record.approvals)
        ? (record.approvals as Array<Record<string, unknown>>)
        : [];
    const primaryFields = primaryGroups
        .flatMap((group) =>
            group.fields.filter((field) =>
                shouldShowProcessField(field, record),
            ),
        )
        .filter((field) => !HEADER_CRAFTSMAN_FIELDS.has(field))
        .filter(
            (field) =>
                !isFinishingTable ||
                !FINISHING_BODY_HIDDEN_FIELDS.has(field),
        );

    return (
        <>
            {primaryFields.length > 0 || category ? (
                <section
                    className="spkProcessCardSection is-fields is-primary"
                    aria-label="Rincian proses"
                >
                    <FieldRows
                        fields={primaryFields}
                        record={record}
                        stacked
                        category={category}
                    />
                </section>
            ) : null}

            {otherGroups.map((group) => {
                const groupFields = group.fields.filter(
                    (field) =>
                        !HEADER_CRAFTSMAN_FIELDS.has(field) &&
                        (!isFinishingTable ||
                            !FINISHING_BODY_HIDDEN_FIELDS.has(field)),
                );

                return (
                    <DetailSection
                        key={group.key}
                        group={{ ...group, fields: groupFields }}
                        record={record}
                    />
                );
            })}

            <ProcessApprovalTimeline approvals={approvals} />
        </>
    );
}
