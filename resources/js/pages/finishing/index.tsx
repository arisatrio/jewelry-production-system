import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Input } from '@ui5/webcomponents-react/Input';
import { Option } from '@ui5/webcomponents-react/Option';
import { Select } from '@ui5/webcomponents-react/Select';
import { create, index as finishingIndex, show } from '@/routes/finishing';
import { show as spkShow } from '@/routes/spk';
import { FinishingSpkStatusCards } from '@/components/finishing/finishing-spk-status-cards';
import { NotesCell } from '@/components/notes-cell';

type FinishingRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    processName: string | null;
    status: string | null;
    statusLabel: string | null;
    spkNo: string | null;
    craftsmanName: string | null;
    sendCraftsmanDate: string | null;
    receivedCraftsmanDate: string | null;
    startWeight: string | null;
    finishWeight: string | null;
    submitMaterial: string | null;
    resultMaterial: string | null;
    shrink: string | null;
    shrinkTolerance: string | null;
    notes: string | null;
};

type DocumentsPaginator = {
    data: FinishingRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FinishingIndexProps = {
    documents: DocumentsPaginator;
    spkStatusCounts: {
        pending: number;
        inProgress: number;
        completed: number;
    };
    filters: {
        search: string;
        per_page: number;
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

function formatStatusLabel(
    statusLabel: string | null,
    status: string | null,
): string {
    const label = statusLabel?.trim() ?? '';

    if (label !== '') {
        return label;
    }

    const code = status?.trim() ?? '';

    return code !== '' ? code : '—';
}

function statusBadgeClass(status: string | null, statusLabel: string | null): string {
    const label = formatStatusLabel(statusLabel, status).toLowerCase();

    if (label.includes('done') || label.includes('completed')) {
        return 'spkTableBadge--done';
    }

    if (label.includes('open') || label.includes('draft')) {
        return 'spkTableBadge--default';
    }

    if (label.includes('serahkan') || label.includes('pengrajin') || label.includes('loket')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

function processBadgeClass(processName: string | null): string {
    const label = processName?.trim().toLowerCase() ?? '';

    if (label.includes('repar')) {
        return 'spkTableBadge--reparasi';
    }

    if (label.includes('handmade')) {
        return 'spkTableBadge--inProgress';
    }

    if (label.includes('finishing')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

function formatWeightValue(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

const MONTH_LABELS = [
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
] as const;

function formatDateDisplay(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '—';
    }

    const [datePart, timePart] = trimmed.split(/\s+/u);
    const [year, month, day] = (datePart ?? '').split('-');

    if (!year || !month || !day) {
        return trimmed;
    }

    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!monthLabel) {
        return trimmed;
    }

    const dateLabel = `${day.padStart(2, '0')}-${monthLabel}-${year}`;

    if (!timePart) {
        return dateLabel;
    }

    return `${dateLabel} ${timePart.slice(0, 5)}`;
}

function parseNumericValue(value: string | null | undefined): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const normalized = value.replace('%', '').trim().replace(',', '.');

    if (normalized === '') {
        return null;
    }

    const parsed = Number.parseFloat(normalized);

    return Number.isFinite(parsed) ? parsed : null;
}

function formatTolerancePercent(value: string | null): string {
    const trimmed = (value?.trim() ?? '').replace(/%$/u, '');

    if (trimmed === '') {
        return '—';
    }

    return `${trimmed}%`;
}

function shrinkPercentTone(
    shrinkPercent: number | null,
    shrinkTolerance: string | null,
): 'ok' | 'nok' | null {
    const tolerance = parseNumericValue(shrinkTolerance);

    if (shrinkPercent === null || tolerance === null) {
        return null;
    }

    return Math.abs(shrinkPercent) <= Math.abs(tolerance) + 0.005 ? 'ok' : 'nok';
}

function formatShrinkCell(
    shrink: string | null,
    startWeight: string | null,
    shrinkTolerance: string | null,
): ReactNode {
    const grams = formatWeightValue(shrink);

    if (grams === '—') {
        return '—';
    }

    const shrinkValue = parseNumericValue(shrink);
    const startValue = parseNumericValue(startWeight);

    if (
        shrinkValue === null ||
        startValue === null ||
        Math.abs(startValue) < 0.0005
    ) {
        return grams;
    }

    const percentValue =
        Math.round(((shrinkValue / startValue) * 100) * 100) / 100;
    const percentLabel = `${percentValue.toFixed(2)}%`;
    const tone = shrinkPercentTone(percentValue, shrinkTolerance);

    return (
        <div className="spkTableShrinkCell">
            <span>{grams}</span>
            <span
                className={[
                    'spkShrinkPercent',
                    tone ? `is-${tone}` : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                ({percentLabel})
            </span>
        </div>
    );
}

const CRAFTSMAN_DATE_ROWS = [
    { key: 'sendCraftsmanDate', label: 'Serah' },
    { key: 'receivedCraftsmanDate', label: 'Terima' },
] as const satisfies ReadonlyArray<{
    key: keyof Pick<FinishingRow, 'sendCraftsmanDate' | 'receivedCraftsmanDate'>;
    label: string;
}>;

const WEIGHT_ROWS = [
    { key: 'startWeight', label: 'Awal' },
    { key: 'finishWeight', label: 'Akhir' },
] as const satisfies ReadonlyArray<{
    key: keyof Pick<FinishingRow, 'startWeight' | 'finishWeight'>;
    label: string;
}>;

const MATERIAL_WEIGHT_ROWS = [
    { key: 'submitMaterial', label: 'Bahan Serah' },
    { key: 'resultMaterial', label: 'Bahan Sisa' },
] as const satisfies ReadonlyArray<{
    key: keyof Pick<FinishingRow, 'submitMaterial' | 'resultMaterial'>;
    label: string;
}>;

export default function FinishingIndex({
    documents,
    spkStatusCounts,
    filters,
}: FinishingIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                finishingIndex.url({
                    query: {
                        search: searchQuery || undefined,
                        per_page: filters.per_page,
                        page: 1,
                    },
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [searchQuery, filters.search, filters.per_page]);

    const totalPages = useMemo(
        () => Math.max(1, documents.last_page),
        [documents.last_page],
    );

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            finishingIndex.url({
                query: {
                    search: params.search || undefined,
                    per_page: params.per_page ?? filters.per_page,
                    page: params.page ?? 1,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Finishing" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableActions">
                        <div className="spkTableTitleBlock">
                            <h1 className="spkTableTitle">Dokumen Finishing</h1>
                        </div>
                        <button
                            type="button"
                            className="spkCreateBtn"
                            aria-label="Tambah dokumen finishing"
                            onClick={() => router.visit(create.url())}
                        >
                            <Icon name={addIcon} mode="Decorative" />
                            <span>Tambah</span>
                        </button>
                    </div>

                    <FinishingSpkStatusCards counts={spkStatusCounts} />

                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <span className="spkTablePageSizeLabel">Show</span>
                            <Select
                                accessibleName="Jumlah baris per halaman"
                                onChange={(event) =>
                                    visit({
                                        page: 1,
                                        per_page: Number(
                                            event.detail.selectedOption.value,
                                        ),
                                        search: searchQuery,
                                    })
                                }
                            >
                                {PAGE_SIZE_OPTIONS.map((size) => (
                                    <Option
                                        key={size}
                                        value={String(size)}
                                        selected={filters.per_page === size}
                                    >
                                        {size}
                                    </Option>
                                ))}
                            </Select>
                            <span className="spkTablePageSizeLabel">
                                entries
                            </span>
                        </div>
                        <div className="spkTableToolbarRight">
                            <Input
                                accessibleName="Cari dokumen finishing"
                                placeholder="Cari nomor dokumen, SPK..."
                                value={searchQuery}
                                icon={<Icon name={searchIcon} />}
                                onInput={(event) =>
                                    setSearchQuery(event.target.value ?? '')
                                }
                            />
                        </div>
                    </div>

                    <div className="spkTableScroll">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tanggal</th>
                                    <th className="spkTableColCenter">Proses</th>
                                    <th>SPK</th>
                                    <th>Pengrajin</th>
                                    <th className="spkTableColCraftsmanDate">
                                        Tanggal Serah Terima
                                    </th>
                                    <th className="spkTableColWeight">Berat (g)</th>
                                    <th className="spkTableColWeight">
                                        Berat Bahan (g)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Toleransi Susut (%)
                                    </th>
                                    <th className="spkTableColCenter">Susut (g)</th>
                                    <th className="spkTableColNotes spkTableColCenter">
                                        Catatan
                                    </th>
                                    <th className="spkTableColStatus spkTableColCenter">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {documents.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data dokumen finishing.
                                        </td>
                                    </tr>
                                ) : (
                                    documents.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="spkProduksiLink"
                                                    onClick={() =>
                                                        router.visit(
                                                            show.url(item.id),
                                                        )
                                                    }
                                                >
                                                    {item.docNo ?? '—'}
                                                </button>
                                            </td>
                                            <td>{formatDateDisplay(item.transDate)}</td>
                                            <td className="spkTableColCenter">
                                                {item.processName ? (
                                                    <span
                                                        className={`spkTableBadge ${processBadgeClass(item.processName)}`}
                                                    >
                                                        {item.processName}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                {item.spkNo ? (
                                                    <button
                                                        type="button"
                                                        className="spkProduksiLink"
                                                        onClick={() =>
                                                            router.visit(
                                                                spkShow.url(
                                                                    item.spkNo!,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {item.spkNo}
                                                    </button>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{item.craftsmanName ?? '—'}</td>
                                            <td className="spkTableColCraftsmanDate">
                                                <dl className="spkTableWeightStack">
                                                    {CRAFTSMAN_DATE_ROWS.map(
                                                        (row) => (
                                                            <div
                                                                key={row.key}
                                                                className="spkTableWeightRow spkTableWeightRow--date"
                                                            >
                                                                <dt>
                                                                    {row.label}
                                                                </dt>
                                                                <dd>
                                                                    {formatDateDisplay(
                                                                        item[row.key],
                                                                    )}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    {WEIGHT_ROWS.map((row) => (
                                                        <div
                                                            key={row.key}
                                                            className="spkTableWeightRow"
                                                        >
                                                            <dt>{row.label}</dt>
                                                            <dd>
                                                                {formatWeightValue(
                                                                    item[row.key],
                                                                )}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            </td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    {MATERIAL_WEIGHT_ROWS.map(
                                                        (row) => (
                                                            <div
                                                                key={row.key}
                                                                className="spkTableWeightRow spkTableWeightRow--material"
                                                            >
                                                                <dt>
                                                                    {row.label}
                                                                </dt>
                                                                <dd>
                                                                    {formatWeightValue(
                                                                        item[row.key],
                                                                    )}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </td>
                                            <td className="spkTableColCenter">
                                                {formatTolerancePercent(
                                                    item.shrinkTolerance,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {formatShrinkCell(
                                                    item.shrink,
                                                    item.startWeight,
                                                    item.shrinkTolerance,
                                                )}
                                            </td>
                                            <td className="spkTableColNotes spkTableColCenter">
                                                <NotesCell
                                                    notes={item.notes}
                                                    docNo={item.docNo}
                                                    spkNo={item.spkNo}
                                                />
                                            </td>
                                            <td className="spkTableColStatus spkTableColCenter">
                                                {item.status ||
                                                item.statusLabel ? (
                                                    <div className="spkTableStatus">
                                                        <span
                                                            className={`spkTableBadge ${statusBadgeClass(item.status, item.statusLabel)}`}
                                                        >
                                                            {formatStatusLabel(
                                                                item.statusLabel,
                                                                item.status,
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {documents.total} dokumen finishing
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={documents.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: documents.current_page - 1,
                                        search: searchQuery,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {documents.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    documents.current_page >= totalPages
                                }
                                onClick={() =>
                                    visit({
                                        page: documents.current_page + 1,
                                        search: searchQuery,
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

FinishingIndex.layout = {
    activeMenu: 'Finishing',
    pageTitle: 'Finishing',
};
