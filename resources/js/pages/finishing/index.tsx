import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Button } from '@ui5/webcomponents-react/Button';
import { create, index as finishingIndex, show, bulkStatus } from '@/routes/finishing';
import { show as spkShow } from '@/routes/spk';
import { NotesCell } from '@/components/notes-cell';
import { FinishingSpkStatusCards } from '@/components/finishing/finishing-spk-status-cards';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

type FilterOption = {
    value: string;
    label: string;
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
        sort: string;
        direction: string;
        process: string[];
        status: string[];
        date_from: string | null;
        date_to: string | null;
        craftsman: number | null;
        per_page: number;
    };
    filterOptions: {
        process: FilterOption[];
        status: FilterOption[];
        craftsman: FilterOption[];
        per_page: FilterOption[];
        sort: FilterOption[];
        direction: FilterOption[];
    };
    bulkActions: {
        canSubmit: boolean;
        canManagerApprove: boolean;
        canComplete: boolean;
        canDelete: boolean;
    };
};

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
    filterOptions,
    bulkActions,
}: FinishingIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    const visitIndex = (params: {
        search?: string;
        sort?: string;
        direction?: string;
        process?: string[];
        status?: string[];
        date_from?: string | null;
        date_to?: string | null;
        craftsman?: number | null;
        per_page?: number;
        page?: number;
    }) => {
        const nextSearch = params.search ?? searchQuery;
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;
        const nextProcess = params.process ?? filters.process;
        const nextStatus = params.status ?? filters.status;
        const nextDateFrom =
            params.date_from !== undefined
                ? params.date_from
                : filters.date_from;
        const nextDateTo =
            params.date_to !== undefined ? params.date_to : filters.date_to;
        const nextCraftsman =
            params.craftsman !== undefined
                ? params.craftsman
                : filters.craftsman;
        const nextPerPage = params.per_page ?? filters.per_page;

        router.get(
            finishingIndex.url({
                query: {
                    search: nextSearch || undefined,
                    sort: nextSort !== 'id' ? nextSort : undefined,
                    direction:
                        nextDirection !== 'desc' ? nextDirection : undefined,
                    process: nextProcess.length > 0 ? nextProcess : undefined,
                    status: nextStatus.length > 0 ? nextStatus : undefined,
                    date_from: nextDateFrom || undefined,
                    date_to: nextDateTo || undefined,
                    craftsman: nextCraftsman ?? undefined,
                    per_page: nextPerPage !== 50 ? nextPerPage : undefined,
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

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                finishingIndex.url({
                    query: {
                        search: searchQuery || undefined,
                        sort:
                            filters.sort !== 'id' ? filters.sort : undefined,
                        direction:
                            filters.direction !== 'desc'
                                ? filters.direction
                                : undefined,
                        process:
                            filters.process.length > 0
                                ? filters.process
                                : undefined,
                        status:
                            filters.status.length > 0
                                ? filters.status
                                : undefined,
                        date_from: filters.date_from || undefined,
                        date_to: filters.date_to || undefined,
                        craftsman: filters.craftsman ?? undefined,
                        per_page:
                            filters.per_page !== 50
                                ? filters.per_page
                                : undefined,
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
    }, [
        searchQuery,
        filters.search,
        filters.sort,
        filters.direction,
        filters.process,
        filters.status,
        filters.date_from,
        filters.date_to,
        filters.craftsman,
        filters.per_page,
    ]);

    const totalPages = useMemo(
        () => Math.max(1, documents.last_page),
        [documents.last_page],
    );

    const visitPage = (page: number) => {
        visitIndex({ page });
    };

    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [filterDraft, setFilterDraft] = useState({
        process: filters.process[0] ?? '',
        status: filters.status[0] ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        craftsman:
            filters.craftsman !== null ? String(filters.craftsman) : '',
    });
    const [entriesDraft, setEntriesDraft] = useState(
        String(filters.per_page),
    );
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        setFilterDraft({
            process: filters.process[0] ?? '',
            status: filters.status[0] ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            craftsman:
                filters.craftsman !== null ? String(filters.craftsman) : '',
        });
    }, [
        filters.process,
        filters.status,
        filters.date_from,
        filters.date_to,
        filters.craftsman,
    ]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setSelectedIds([]);
    }, [documents.data]);

    const pageIds = useMemo(
        () => documents.data.map((item) => item.id),
        [documents.data],
    );
    const allPageSelected =
        pageIds.length > 0 &&
        pageIds.every((id) => selectedIds.includes(id));
    const somePageSelected = pageIds.some((id) =>
        selectedIds.includes(id),
    );

    const toggleSelectAll = (checked: boolean | 'indeterminate') => {
        if (checked === true) {
            setSelectedIds(pageIds);

            return;
        }

        setSelectedIds([]);
    };

    const toggleSelectRow = (id: number, checked: boolean | 'indeterminate') => {
        setSelectedIds((current) => {
            if (checked === true) {
                return current.includes(id) ? current : [...current, id];
            }

            return current.filter((itemId) => itemId !== id);
        });
    };

    const runBulkStatus = (
        action: 'submit' | 'manager_approve' | 'complete' | 'delete',
    ) => {
        if (selectedIds.length === 0 || bulkSubmitting) {
            return;
        }

        if (
            action === 'delete' &&
            !window.confirm(
                `Hapus ${selectedIds.length} dokumen finishing terpilih?`,
            )
        ) {
            return;
        }

        setBulkSubmitting(true);
        router.post(
            bulkStatus.url(),
            {
                ids: selectedIds,
                action,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBulkSubmitting(false);
                    setSelectedIds([]);
                },
            },
        );
    };

    const applySort = () => {
        if (
            sortDraft.sort === filters.sort &&
            sortDraft.direction === filters.direction
        ) {
            setSortMenuOpen(false);

            return;
        }

        visitIndex({
            sort: sortDraft.sort,
            direction: sortDraft.direction,
            page: 1,
        });
        setSortMenuOpen(false);
    };

    const clearSort = () => {
        setSortDraft({ sort: 'id', direction: 'desc' });

        if (filters.sort === 'id' && filters.direction === 'desc') {
            setSortMenuOpen(false);

            return;
        }

        visitIndex({ sort: 'id', direction: 'desc', page: 1 });
        setSortMenuOpen(false);
    };

    const applyFilters = () => {
        const nextProcess =
            filterDraft.process === '' ? [] : [filterDraft.process];
        const nextStatus =
            filterDraft.status === '' ? [] : [filterDraft.status];
        const nextDateFrom =
            filterDraft.date_from === '' ? null : filterDraft.date_from;
        const nextDateTo =
            filterDraft.date_to === '' ? null : filterDraft.date_to;
        const nextCraftsman =
            filterDraft.craftsman === ''
                ? null
                : Number(filterDraft.craftsman);

        const unchanged =
            nextProcess[0] === filters.process[0] &&
            nextProcess.length === filters.process.length &&
            nextStatus[0] === filters.status[0] &&
            nextStatus.length === filters.status.length &&
            nextDateFrom === filters.date_from &&
            nextDateTo === filters.date_to &&
            nextCraftsman === filters.craftsman;

        if (unchanged) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            process: nextProcess,
            status: nextStatus,
            date_from: nextDateFrom,
            date_to: nextDateTo,
            craftsman: nextCraftsman,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({
            process: '',
            status: '',
            date_from: '',
            date_to: '',
            craftsman: '',
        });

        if (
            filters.process.length === 0 &&
            filters.status.length === 0 &&
            filters.date_from === null &&
            filters.date_to === null &&
            filters.craftsman === null
        ) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            process: [],
            status: [],
            date_from: null,
            date_to: null,
            craftsman: null,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const applyEntries = () => {
        const nextPerPage = Number(entriesDraft);

        if (nextPerPage === filters.per_page) {
            setEntriesMenuOpen(false);

            return;
        }

        visitIndex({ per_page: nextPerPage, page: 1 });
        setEntriesMenuOpen(false);
    };

    const clearEntries = () => {
        setEntriesDraft('50');

        if (filters.per_page === 50) {
            setEntriesMenuOpen(false);

            return;
        }

        visitIndex({ per_page: 50, page: 1 });
        setEntriesMenuOpen(false);
    };

    const hasActiveFilters =
        filters.process.length > 0 ||
        filters.status.length > 0 ||
        filters.date_from !== null ||
        filters.date_to !== null ||
        filters.craftsman !== null;
    const hasCustomSort =
        filters.sort !== 'id' || filters.direction !== 'desc';
    const hasCustomEntries = filters.per_page !== 50;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasFilterDraftChanges =
        (filterDraft.process || '') !== (filters.process[0] ?? '') ||
        (filterDraft.status || '') !== (filters.status[0] ?? '') ||
        (filterDraft.date_from || '') !== (filters.date_from ?? '') ||
        (filterDraft.date_to || '') !== (filters.date_to ?? '') ||
        (filterDraft.craftsman || '') !==
            (filters.craftsman !== null ? String(filters.craftsman) : '');
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasSortDraftCustom =
        sortDraft.sort !== 'id' || sortDraft.direction !== 'desc';
    const hasFilterDraftActive =
        filterDraft.process !== '' ||
        filterDraft.status !== '' ||
        filterDraft.date_from !== '' ||
        filterDraft.date_to !== '' ||
        filterDraft.craftsman !== '';
    const hasEntriesDraftCustom = entriesDraft !== '50';

    return (
        <>
            <Head title="Finishing" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Finishing
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Data pengerjaan proses finishing
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <FinishingSpkStatusCards
                                counts={spkStatusCounts}
                                variant="alerts"
                            />
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <div
                                className="spkTableHeaderSearch--finishing"
                                role="search"
                            >
                                <input
                                    className="spkTableHeaderSearchInput--finishing"
                                    type="search"
                                    placeholder="Cari"
                                    value={searchQuery}
                                    autoComplete="off"
                                    aria-label="Cari dokumen finishing"
                                    onChange={(event) =>
                                        setSearchQuery(event.target.value)
                                    }
                                />
                                <Icon
                                    className="spkTableHeaderSearchIcon--finishing"
                                    name={searchIcon}
                                />
                            </div>
                            <div
                                className="spkTableHeaderActions--finishing"
                                role="group"
                                aria-label="Aksi tabel"
                            >
                                <DropdownMenu
                                    open={sortMenuOpen}
                                    onOpenChange={(open) => {
                                        setSortMenuOpen(open);

                                        if (open) {
                                            setSortDraft({
                                                sort: filters.sort,
                                                direction: filters.direction,
                                            });
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={[
                                                'spkTableHeaderIconBtn--finishing',
                                                'spkTableHeaderIconTrigger--finishing',
                                                hasCustomSort ? 'is-active' : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Urutkan"
                                            title="Urutkan"
                                        >
                                            <Icon
                                                name={sortIcon}
                                                mode="Decorative"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="spkTableHeaderMenu--finishing spkTableHeaderSortMenu--finishing"
                                        onCloseAutoFocus={(event) =>
                                            event.preventDefault()
                                        }
                                    >
                                        <div className="spkTableHeaderSortFields--finishing">
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Urutkan</span>
                                                <select
                                                    value={sortDraft.sort}
                                                    aria-label="Urutkan"
                                                    onChange={(event) =>
                                                        setSortDraft(
                                                            (current) => ({
                                                                ...current,
                                                                sort: event
                                                                    .target
                                                                    .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {filterOptions.sort.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Urutan</span>
                                                <select
                                                    value={sortDraft.direction}
                                                    aria-label="Urutan"
                                                    onChange={(event) =>
                                                        setSortDraft(
                                                            (current) => ({
                                                                ...current,
                                                                direction:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {filterOptions.direction.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasSortDraftCustom &&
                                                        !hasCustomSort
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearSort();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasSortDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applySort();
                                                    }}
                                                >
                                                    Terapkan
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <DropdownMenu
                                    open={filterMenuOpen}
                                    onOpenChange={(open) => {
                                        setFilterMenuOpen(open);

                                        if (open) {
                                            setFilterDraft({
                                                process:
                                                    filters.process[0] ?? '',
                                                status:
                                                    filters.status[0] ?? '',
                                                date_from:
                                                    filters.date_from ?? '',
                                                date_to:
                                                    filters.date_to ?? '',
                                                craftsman:
                                                    filters.craftsman !== null
                                                        ? String(
                                                              filters.craftsman,
                                                          )
                                                        : '',
                                            });
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={[
                                                'spkTableHeaderIconBtn--finishing',
                                                'spkTableHeaderIconTrigger--finishing',
                                                hasActiveFilters
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Filter"
                                            title="Filter"
                                        >
                                            <Icon
                                                name={filterIcon}
                                                mode="Decorative"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="spkTableHeaderMenu--finishing spkTableHeaderSortMenu--finishing"
                                        onCloseAutoFocus={(event) =>
                                            event.preventDefault()
                                        }
                                    >
                                        <div className="spkTableHeaderSortFields--finishing">
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tanggal dari</span>
                                                <input
                                                    type="date"
                                                    value={
                                                        filterDraft.date_from
                                                    }
                                                    aria-label="Tanggal dari"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                date_from:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                />
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tanggal sampai</span>
                                                <input
                                                    type="date"
                                                    value={filterDraft.date_to}
                                                    aria-label="Tanggal sampai"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                date_to:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                />
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Pengrajin</span>
                                                <select
                                                    value={
                                                        filterDraft.craftsman
                                                    }
                                                    aria-label="Pengrajin"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                craftsman:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua
                                                    </option>
                                                    {filterOptions.craftsman.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Proses</span>
                                                <select
                                                    value={filterDraft.process}
                                                    aria-label="Proses"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                process:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua
                                                    </option>
                                                    {filterOptions.process.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Status</span>
                                                <select
                                                    value={filterDraft.status}
                                                    aria-label="Status"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                status: event
                                                                    .target
                                                                    .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua
                                                    </option>
                                                    {filterOptions.status.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasFilterDraftActive &&
                                                        !hasActiveFilters
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearFilters();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasFilterDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applyFilters();
                                                    }}
                                                >
                                                    Terapkan
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <DropdownMenu
                                    open={entriesMenuOpen}
                                    onOpenChange={(open) => {
                                        setEntriesMenuOpen(open);

                                        if (open) {
                                            setEntriesDraft(
                                                String(filters.per_page),
                                            );
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={[
                                                'spkTableHeaderIconBtn--finishing',
                                                'spkTableHeaderIconTrigger--finishing',
                                                hasCustomEntries
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Tampilkan entri"
                                            title="Tampilkan entri"
                                        >
                                            <Icon
                                                name={listIcon}
                                                mode="Decorative"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="spkTableHeaderMenu--finishing spkTableHeaderSortMenu--finishing"
                                        onCloseAutoFocus={(event) =>
                                            event.preventDefault()
                                        }
                                    >
                                        <div className="spkTableHeaderSortFields--finishing">
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Tampilkan</span>
                                                <select
                                                    value={entriesDraft}
                                                    aria-label="Tampilkan entri"
                                                    onChange={(event) =>
                                                        setEntriesDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {filterOptions.per_page.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasEntriesDraftCustom &&
                                                        !hasCustomEntries
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearEntries();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasEntriesDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applyEntries();
                                                    }}
                                                >
                                                    Terapkan
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <Button
                                design="Default"
                                icon={excelAttachmentIcon}
                                accessibleName="Ekspor"
                                className="spkTableHeaderExportBtn--finishing"
                            />
                            <Button
                                design="Default"
                                icon={printIcon}
                                accessibleName="Cetak"
                                className="spkTableHeaderExportBtn--finishing"
                            />
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn"
                                aria-label="Tambah finishing"
                                onClick={() => router.visit(create.url())}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah</span>
                            </button>
                        </div>
                    </div>

                    {selectedIds.length > 0 ? (
                        <div
                            className="spkTableBulkBar--finishing"
                            role="region"
                            aria-label="Aksi massal status"
                        >
                            <span className="spkTableBulkBarCount--finishing">
                                {selectedIds.length} dipilih
                            </span>
                            <div className="spkTableBulkBarActions--finishing">
                                {bulkActions.canSubmit ? (
                                    <Button
                                        design="Positive"
                                        disabled={bulkSubmitting}
                                        onClick={() => runBulkStatus('submit')}
                                    >
                                        {bulkSubmitting
                                            ? 'Memproses...'
                                            : 'Kirim ke Manager'}
                                    </Button>
                                ) : null}
                                {bulkActions.canManagerApprove ? (
                                    <Button
                                        design="Positive"
                                        disabled={bulkSubmitting}
                                        onClick={() =>
                                            runBulkStatus('manager_approve')
                                        }
                                    >
                                        {bulkSubmitting
                                            ? 'Memproses...'
                                            : 'Approve'}
                                    </Button>
                                ) : null}
                                {bulkActions.canComplete ? (
                                    <Button
                                        design="Positive"
                                        disabled={bulkSubmitting}
                                        onClick={() =>
                                            runBulkStatus('complete')
                                        }
                                    >
                                        {bulkSubmitting
                                            ? 'Memproses...'
                                            : 'Selesai'}
                                    </Button>
                                ) : null}
                                {bulkActions.canDelete ? (
                                    <Button
                                        design="Negative"
                                        disabled={bulkSubmitting}
                                        className="spkTableBulkDeleteBtn--finishing"
                                        onClick={() =>
                                            runBulkStatus('delete')
                                        }
                                    >
                                        {bulkSubmitting
                                            ? 'Memproses...'
                                            : 'Hapus'}
                                    </Button>
                                ) : null}
                                <button
                                    type="button"
                                    className="spkTableHeaderSortClear--finishing"
                                    disabled={bulkSubmitting}
                                    onClick={() => setSelectedIds([])}
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    ) : null}

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCheck--finishing">
                                        <Checkbox
                                            checked={
                                                allPageSelected
                                                    ? true
                                                    : somePageSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={toggleSelectAll}
                                            aria-label="Pilih semua baris"
                                            disabled={pageIds.length === 0}
                                        />
                                    </th>
                                    <th>ID</th>
                                    <th>Tanggal</th>
                                    <th className="spkTableColCenter">Proses</th>
                                    <th>SPK</th>
                                    <th>Pengrajin</th>
                                    <th className="spkTableColCraftsmanDate spkTableColCenter">
                                        Tanggal Serah Terima
                                    </th>
                                    <th className="spkTableColWeight spkTableColWeight--finishing spkTableColCenter">
                                        Berat (g)
                                    </th>
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Berat Bahan (g)
                                    </th>
                                    <th className="spkTableColCenter spkTableColTolerance--finishing">
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
                                        <td colSpan={13}>
                                            Tidak ada data dokumen finishing.
                                        </td>
                                    </tr>
                                ) : (
                                    documents.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className={
                                                selectedIds.includes(item.id)
                                                    ? 'is-selected'
                                                    : undefined
                                            }
                                        >
                                            <td className="spkTableColCheck--finishing">
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        item.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelectRow(
                                                            item.id,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Pilih ${item.docNo ?? item.id}`}
                                                />
                                            </td>
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
                                            <td className="spkTableColWeight spkTableColWeight--finishing">
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
                                            <td className="spkTableColCenter spkTableColTolerance--finishing">
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
                                    visitPage(documents.current_page - 1)
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
                                    visitPage(documents.current_page + 1)
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
