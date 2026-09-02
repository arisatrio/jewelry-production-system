import { formatGram } from '@/lib/utils';
import { isKoreksiQcNotOk } from '@/components/spk/spk-qc-status-badge';

export const HIDDEN_COLUMNS = new Set([
    'line_id',
    'row_id',
    'spk_id',
    'file_upload',
    'craftsman_id',
    'craftman_id',
    'process_name',
    'frame_id',
    'materials_out',
    'materials_in',
    'submit_materials',
    'result_materials',
    'coran_breakdown',
    'stone_setting',
    'stone_return',
    'stone_diamonds',
    'stone_mounted',
    'approvals',
    'spk_usage_gold_color',
    'spk_usage_percent',
    'shrink_percent',
    'is_deleted',
    'deleted_date',
    'deleted_by',
    'created_by',
    'modified_by',
    'created_date',
    'modified_date',
]);

export const PROCESS_HIDDEN_COLUMNS: Record<string, Set<string>> = {
    JewelCAD: new Set([
        'material',
        'qty',
        'tanggal',
        'is_from_new_system',
        'notes',
    ]),
    Resin: new Set(['status', 'tanggal']),
    'Pasang Batu': new Set([
        'status',
        'spk_no',
        'setting_id',
        'qc_id',
        'mounting_return',
        'weight_diamond',
        'polish_shrink',
        'trans_date',
    ]),
};

export const COLUMN_LABELS: Record<string, string> = {
    doc_no: 'Doc No',
    craftsman_name: 'Nama Pengrajin',
    tanggal: 'Tanggal',
    trans_date: 'Tanggal',
    date_from: 'Tanggal Serah',
    date_to: 'Tanggal Terima',
    pengrajin: 'Pengrajin',
    operator: 'Operator',
    material: 'Material',
    qty: 'Qty',
    estimation_brj: 'Estimasi BRJ (g)',
    process_name: 'Nama Proses',
    craftsman_id: 'ID Pengrajin',
    start_weight: 'Berat Awal (g)',
    finish_weight: 'Berat Akhir (g)',
    weight: 'Berat Item',
    weight_frame: 'Berat Rangka',
    weight_diamond: 'Berat Batu',
    weight_finish_goods: 'Berat Jadi',
    total_weigth_frame_diamond: 'Berat Rangka+Batu',
    mounting_shrink: 'Susut',
    polish_shrink: 'Susut Poles',
    mounting_return: 'Retur Mounting',
    total_submit_material: 'Total Submit Material (g)',
    total_result_material: 'Total Result Material (g)',
    submit_materialgold: 'Serah Bahan ke Pengrajin (g)',
    result_materialgold: 'Kembali dari Pengrajin (g)',
    shrink: 'Susut',
    shrink_tolerance: 'Toleransi Susut (%)',
    spk_usage_percent: 'Pemakaian item',
    send_craftsman_date: 'Tanggal Setor',
    received_craftsman_date: 'Tanggal Terima',
    item_category: 'Kategori Item',
    status_item: 'QC Status',
    notes: 'Catatan',
    status: 'Status',
    koreksi_qc: 'QC Check',
    keterangan_qc: 'Catatan QC',
    spk_no: 'Produksi No',
    created_date: 'Tanggal Dibuat',
    modified_date: 'Tanggal Diubah',
};

export const COLUMN_ORDER = [
    'craftsman_name',
    'pengrajin',
    'doc_no',
    'tanggal',
    'trans_date',
    'date_from',
    'date_to',
    'process_name',
    'frame_id',
    'start_weight',
    'finish_weight',
    'total_submit_material',
    'total_result_material',
    'submit_materialgold',
    'result_materialgold',
    'shrink',
    'shrink_tolerance',
    'weight',
    'send_craftsman_date',
    'received_craftsman_date',
    'item_category',
    'status_item',
    'notes',
    'status',
    'koreksi_qc',
    'keterangan_qc',
    'created_date',
    'modified_date',
];

export type ProcessCardGroupVariant = 'metrics' | 'fields' | 'notes' | 'meta';

export type ProcessCardGroup = {
    key: string;
    label: string;
    variant: ProcessCardGroupVariant;
    fields: string[];
};

/** Contextual field groups for card layout (order matters). */
export const PROCESS_CARD_GROUPS: ProcessCardGroup[] = [
    {
        key: 'schedule',
        label: 'Jadwal',
        variant: 'fields',
        fields: [
            'date_from',
            'date_to',
            'pengrajin',
        ],
    },
    {
        key: 'identity',
        label: 'Informasi',
        variant: 'fields',
        fields: [
            'craftsman_name',
            'material',
            'qty',
            'estimation_brj',
        ],
    },
    {
        key: 'weight',
        label: 'Berat',
        variant: 'fields',
        fields: [
            'tanggal',
            'trans_date',
            'spk_no',
            'send_craftsman_date',
            'received_craftsman_date',
            'start_weight',
            'finish_weight',
            'weight_frame',
            'total_weigth_frame_diamond',
            'weight_diamond',
            'weight_finish_goods',
            'weight',
            'shrink',
            'mounting_shrink',
            'shrink_tolerance',
            'status',
            'status_item',
            'koreksi_qc',
            'keterangan_qc',
            'notes',
            'total_submit_material',
            'total_result_material',
        ],
    },
    {
        key: 'gold',
        label: 'Material Emas',
        variant: 'fields',
        fields: ['submit_materialgold', 'result_materialgold'],
    },
    {
        key: 'shrink',
        label: 'Susut',
        variant: 'fields',
        fields: ['polish_shrink'],
    },
];

/** Shown in card header / category strip, not repeated in body groups. */
export const CARD_HEADER_ONLY_FIELDS = new Set([
    'doc_no',
    'process_name',
    'craftsman_id',
    'operator',
    'item_category',
    'materials_out',
    'materials_in',
]);

export const METRIC_SHORT_LABELS: Record<string, string> = {
    start_weight: 'Berat Awal',
    finish_weight: 'Berat Akhir',
    weight: 'Berat Item',
    weight_frame: 'Rangka',
    weight_diamond: 'Batu',
    weight_finish_goods: 'Barang Jadi',
    total_submit_material: 'Total Submit',
    total_result_material: 'Total Result',
    submit_materialgold: 'Serah Bahan ke Pengrajin',
    result_materialgold: 'Kembali dari Pengrajin',
    shrink: 'Susut',
    shrink_tolerance: 'Toleransi',
    mounting_shrink: 'Susut Mounting',
    polish_shrink: 'Susut Poles',
    spk_usage_percent: 'Pemakaian item',
    estimation_brj: 'Est. BRJ',
};

export const SHRINK_VALUE_COLUMNS = new Set([
    'shrink',
    'shrink_tolerance',
    'mounting_shrink',
    'polish_shrink',
]);

const GRAM_COLUMNS = new Set([
    'estimation_brj',
    'weight',
    'start_weight',
    'finish_weight',
    'total_submit_material',
    'total_result_material',
    'submit_materialgold',
    'result_materialgold',
    'shrink',
    'weight_frame',
    'weight_diamond',
    'total_weigth_frame_diamond',
    'mounting_shrink',
    'weight_finish_goods',
    'polish_shrink',
]);

function isGramColumn(column: string): boolean {
    if (column === 'shrink_tolerance') {
        return false;
    }

    if (GRAM_COLUMNS.has(column)) {
        return true;
    }

    return (
        column.endsWith('_weight') ||
        column.endsWith('_shrink') ||
        column.includes('materialgold')
    );
}

function formatGramValue(value: unknown): string {
    return formatGram(
        value as number | string | null | undefined,
    );
}

function formatShrinkTolerance(record: Record<string, unknown>): string {
    const tolerance = record.shrink_tolerance;

    if (tolerance === null || tolerance === undefined || tolerance === '') {
        return '—';
    }

    return `${String(tolerance).replace(/%$/u, '').trim()}%`;
}

function formatAuditActor(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value).trim() || '—';
}

export function formatProcessAuditLine(
    column: 'created_date' | 'modified_date',
    dateValue: unknown,
    record?: Record<string, unknown>,
): string {
    const verb = column === 'created_date' ? 'dibuat' : 'diubah';
    const actorKey = column === 'created_date' ? 'created_by' : 'modified_by';
    const actor = formatAuditActor(record?.[actorKey]);
    const date =
        dateValue === null || dateValue === undefined || dateValue === ''
            ? '—'
            : String(dateValue);

    return `${verb} oleh ${actor} pada ${date}`;
}

export function formatProcessCellValue(
    value: unknown,
    column: string,
    record?: Record<string, unknown>,
): string {
    if (record && !shouldShowProcessField(column, record)) {
        return '—';
    }

    if (column === 'koreksi_qc') {
        return isKoreksiQcActive(record ?? { koreksi_qc: value })
            ? 'NOT OK'
            : 'OK';
    }

    if (column === 'shrink_tolerance' && record) {
        return formatShrinkTolerance(record);
    }

    if (column === 'created_date' || column === 'modified_date') {
        return formatProcessAuditLine(column, value, record);
    }

    if (column === 'shrink' && record) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const gram = formatGramValue(value);
        const percent = record.shrink_percent;

        if (percent === null || percent === undefined || percent === '') {
            return gram;
        }

        return `${gram} (${String(percent).replace(/%$/u, '').trim()}%)`;
    }

    if (column === 'weight' && record) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const gram = formatGramValue(value);
        const goldColor =
            typeof record.spk_usage_gold_color === 'string'
                ? record.spk_usage_gold_color.trim()
                : '';

        return goldColor !== '' ? `${gram} (${goldColor})` : gram;
    }

    if (column === 'status' && record && 'coran_breakdown' in record) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return String(value);
    }

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    if (isGramColumn(column)) {
        return formatGramValue(value);
    }

    return String(value);
}

export function hiddenColumnsFor(processKey: string): Set<string> {
    const processHidden = PROCESS_HIDDEN_COLUMNS[processKey];

    if (!processHidden) {
        return HIDDEN_COLUMNS;
    }

    return new Set([...HIDDEN_COLUMNS, ...processHidden]);
}

export function resolveProcessColumns(
    records: Array<Record<string, unknown>>,
    processKey: string,
): string[] {
    if (records.length === 0) {
        return [];
    }

    const hidden = hiddenColumnsFor(processKey);
    const columns = Object.keys(records[0] ?? {}).filter(
        (column) => !hidden.has(column),
    );

    const ordered = COLUMN_ORDER.filter((column) => columns.includes(column));
    const rest = columns.filter((column) => !COLUMN_ORDER.includes(column));
    const resolved = [...ordered, ...rest];

    const hasKeteranganQc = records.some((record) =>
        shouldShowProcessField('keterangan_qc', record),
    );

    if (!hasKeteranganQc) {
        return resolved.filter((column) => column !== 'keterangan_qc');
    }

    return resolved;
}

function humanizeColumn(column: string): string {
    return column
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function processColumnLabel(
    column: string,
    record?: Record<string, unknown>,
): string {
    if (
        column === 'tanggal' &&
        record &&
        ('stone_setting' in record || 'stone_mounted' in record)
    ) {
        return 'Tanggal Kerja';
    }

    if (
        column === 'notes' &&
        record &&
        ('stone_setting' in record || 'stone_mounted' in record)
    ) {
        return 'Keterangan';
    }

    if (
        column === 'weight' &&
        record &&
        typeof record.spk_no === 'string' &&
        record.spk_no.trim() !== ''
    ) {
        return `Berat Item Hasil Coran (${record.spk_no.trim()})`;
    }

    if (column === 'shrink' && record && 'coran_breakdown' in record) {
        return 'Total Susut Batch';
    }

    if (
        column === 'shrink' &&
        record &&
        ('materials_out' in record || 'materials_in' in record)
    ) {
        return 'Total Susut';
    }

    if (column === 'status' && record && 'coran_breakdown' in record) {
        return 'QC Status';
    }

    if (column === 'status_item') {
        return 'QC Status';
    }

    if (column === 'koreksi_qc') {
        return 'QC Status';
    }

    return COLUMN_LABELS[column] ?? humanizeColumn(column);
}

export function processMetricLabel(column: string): string {
    return METRIC_SHORT_LABELS[column] ?? processColumnLabel(column);
}

export function processRecordTitle(processLabel: string): string {
    return `Laporan ${processLabel}`;
}

export function processRecordSubtitle(record: Record<string, unknown>): string {
    if (!hasProcessValue(record.doc_no)) {
        return '';
    }

    return String(record.doc_no);
}

/**
 * Resolve display date for report card headers.
 * Prefer work dates, then fall back to created timestamps.
 */
export function processRecordWorkDate(
    record: Record<string, unknown>,
): string | null {
    const candidates = [
        record.tanggal,
        record.date_from,
        record.send_craftsman_date,
        record.received_craftsman_date,
        record.trans_date,
        record.created_date,
        record.created_at,
    ];

    for (const candidate of candidates) {
        if (!hasProcessValue(candidate)) {
            continue;
        }

        const raw = String(candidate).trim();

        if (raw === '' || raw === '-' || raw.toLowerCase() === 'null') {
            continue;
        }

        // Already formatted display date (e.g. 19-May-2025 or 19-May-2025 12:01:35).
        if (/^\d{1,2}-[A-Za-z]{3}-\d{4}/u.test(raw)) {
            return raw.slice(0, 11);
        }

        if (/[A-Za-z]/.test(raw) && !raw.includes('T') && !/^\d{4}-/u.test(raw)) {
            return raw.includes(' ') ? (raw.split(/\s+/u)[0] ?? raw) : raw;
        }

        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
        const parsed = new Date(normalized);

        if (!Number.isNaN(parsed.getTime())) {
            const day = String(parsed.getDate()).padStart(2, '0');
            const months = [
                'Jan',
                'Feb',
                'Mar',
                'Apr',
                'May',
                'Jun',
                'Jul',
                'Aug',
                'Sep',
                'Oct',
                'Nov',
                'Dec',
            ];
            const month = months[parsed.getMonth()] ?? '';
            const year = parsed.getFullYear();

            return `${day}-${month}-${year}`;
        }

        return raw.includes(' ') ? (raw.split(/\s+/u)[0] ?? raw) : raw;
    }

    return null;
}

export function processRecordCategory(
    record: Record<string, unknown>,
): string | null {
    if (!hasProcessValue(record.item_category)) {
        return null;
    }

    return String(record.item_category);
}

export function resolveProcessCardGroups(
    columns: string[],
): Array<ProcessCardGroup & { fields: string[] }> {
    const available = new Set(
        columns.filter((column) => !CARD_HEADER_ONLY_FIELDS.has(column)),
    );
    const grouped = new Set<string>();

    const groups = PROCESS_CARD_GROUPS.map((group) => {
        const fields = group.fields.filter((field) => available.has(field));

        fields.forEach((field) => grouped.add(field));

        return { ...group, fields };
    }).filter((group) => group.fields.length > 0);

    const leftover = columns.filter(
        (column) =>
            !CARD_HEADER_ONLY_FIELDS.has(column) && !grouped.has(column),
    );

    if (leftover.length > 0) {
        groups.push({
            key: 'other',
            label: 'Lainnya',
            variant: 'fields',
            fields: leftover,
        });
    }

    return groups;
}

export function hasProcessValue(value: unknown): boolean {
    return value !== null && value !== undefined && value !== '';
}

export type ProcessMaterialLine = {
    name: string;
    weight: number;
    notes: string | null;
};

export function processMaterialLines(
    value: unknown,
): ProcessMaterialLine[] {
    if (!Array.isArray(value)) {
        return [];
    }

    const lines: ProcessMaterialLine[] = [];

    for (const line of value) {
        if (!line || typeof line !== 'object') {
            continue;
        }

        const row = line as Record<string, unknown>;
        const name = row.name;
        const weight = Number(row.weight);

        if (!hasProcessValue(name) || Number.isNaN(weight)) {
            continue;
        }

        lines.push({
            name: String(name),
            weight,
            notes: hasProcessValue(row.notes) ? String(row.notes) : null,
        });
    }

    return lines;
}

export function isKoreksiQcActive(record: Record<string, unknown>): boolean {
    return isKoreksiQcNotOk(record.koreksi_qc);
}

export function shouldShowProcessField(
    column: string,
    record: Record<string, unknown>,
): boolean {
    if (column === 'keterangan_qc') {
        return isKoreksiQcActive(record) && hasProcessValue(record.keterangan_qc);
    }

    if (column === 'notes') {
        return hasProcessValue(record.notes);
    }

    if (column === 'status') {
        return 'coran_breakdown' in record;
    }

    if (column === 'spk_no') {
        return 'stone_setting' in record || 'stone_mounted' in record;
    }

    if (column === 'status_item') {
        return 'status_item' in record;
    }

    if (column === 'koreksi_qc') {
        return 'koreksi_qc' in record;
    }

    if (
        column === 'send_craftsman_date' ||
        column === 'received_craftsman_date'
    ) {
        return (
            !hasProcessValue(record.date_from) &&
            !hasProcessValue(record.date_to)
        );
    }

    return true;
}
