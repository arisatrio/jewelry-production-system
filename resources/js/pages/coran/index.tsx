import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { Button } from '@ui5/webcomponents-react/Button';
import {
    bulkStatus,
    create,
    index as coranIndex,
    show,
} from '@/routes/coran';
import { CoranSpkStatusCards } from '@/components/coran/coran-spk-status-cards';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type MaterialColorBreakdown = {
    bahan: string | null;
    hasil: string | null;
    sisa: string | null;
};

type CoranRow = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    statusLabel: string | null;
    craftsmanName: string | null;
    spkNos: string[];
    spkCount: number;
    totalSpkWeight: string | null;
    totalSubmitMaterial: string | null;
    totalResultMaterial: string | null;
    materialsByColor: {
        roseGold: MaterialColorBreakdown;
        whiteGold: MaterialColorBreakdown;
        yellowGold: MaterialColorBreakdown;
    };
    hasilCoranPercents: {
        hasil: string | null;
        sisa: string | null;
        susut: string | null;
    };
    shrink: string | null;
};

type CoransPaginator = {
    data: CoranRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = {
    value: string;
    label: string;
};

type CoranIndexProps = {
    corans: CoransPaginator;
    spkStatusCounts: {
        pending: number;
        inProgress: number;
        completed: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: string;
        status: string[];
        date_from: string | null;
        date_to: string | null;
        per_page: number;
    };
    filterOptions: {
        status: FilterOption[];
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

    if (label.includes('draft') || label.includes('open')) {
        return 'spkTableBadge--default';
    }

    if (label.includes('approval') || label.includes('pengajuan') || label.includes('serahkan')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

function isStatusIncomplete(
    status: string | null,
    statusLabel: string | null,
): boolean {
    const label = formatStatusLabel(statusLabel, status).toLowerCase();

    if (label === '—' || label === '') {
        return true;
    }

    return !(label.includes('done') || label.includes('completed'));
}

function formatWeightValue(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

const TOTAL_WEIGHT_ROWS = [
    { key: 'totalSubmitMaterial', label: 'Bahan' },
    { key: 'totalSpkWeight', label: 'Hasil' },
    { key: 'totalResultMaterial', label: 'Sisa' },
] as const satisfies ReadonlyArray<{
    key: keyof Pick<
        CoranRow,
        'totalSubmitMaterial' | 'totalSpkWeight' | 'totalResultMaterial'
    >;
    label: string;
}>;

const MATERIAL_COLOR_COLUMNS = [
    { key: 'roseGold', label: 'Rose Gold' },
    { key: 'whiteGold', label: 'White Gold' },
    { key: 'yellowGold', label: 'Yellow Gold' },
] as const satisfies ReadonlyArray<{
    key: keyof CoranRow['materialsByColor'];
    label: string;
}>;

const MATERIAL_COLOR_ROWS = [
    { key: 'bahan', label: 'Bahan' },
    { key: 'hasil', label: 'Hasil' },
    { key: 'sisa', label: 'Sisa' },
] as const satisfies ReadonlyArray<{
    key: keyof MaterialColorBreakdown;
    label: string;
}>;

const HASIL_CORAN_PERCENT_ROWS = [
    { key: 'hasil', label: 'Hasil' },
    { key: 'sisa', label: 'Sisa' },
    { key: 'susut', label: 'Susut' },
] as const satisfies ReadonlyArray<{
    key: keyof CoranRow['hasilCoranPercents'];
    label: string;
}>;

export default function CoranIndex({
    corans,
    spkStatusCounts,
    filters,
    filterOptions,
    bulkActions,
}: CoranIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [filterDraft, setFilterDraft] = useState({
        status: filters.status[0] ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        setFilterDraft({
            status: filters.status[0] ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        });
    }, [filters.status, filters.date_from, filters.date_to]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setSelectedIds([]);
    }, [corans.data]);

    const pageIds = useMemo(
        () => corans.data.map((item) => item.id),
        [corans.data],
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
                `Hapus ${selectedIds.length} dokumen coran terpilih?`,
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

    const visitIndex = (params: {
        search?: string;
        sort?: string;
        direction?: string;
        status?: string[];
        date_from?: string | null;
        date_to?: string | null;
        per_page?: number;
        page?: number;
    }) => {
        const nextSearch = params.search ?? searchQuery;
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;
        const nextStatus = params.status ?? filters.status;
        const nextDateFrom =
            params.date_from !== undefined
                ? params.date_from
                : filters.date_from;
        const nextDateTo =
            params.date_to !== undefined ? params.date_to : filters.date_to;
        const nextPerPage = params.per_page ?? filters.per_page;

        router.get(
            coranIndex.url({
                query: {
                    search: nextSearch || undefined,
                    sort: nextSort !== 'id' ? nextSort : undefined,
                    direction:
                        nextDirection !== 'desc' ? nextDirection : undefined,
                    status: nextStatus.length > 0 ? nextStatus : undefined,
                    date_from: nextDateFrom || undefined,
                    date_to: nextDateTo || undefined,
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

            visitIndex({ search: searchQuery, page: 1 });
        }, 300);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce search only
    }, [searchQuery, filters.search]);

    const totalPages = useMemo(
        () => Math.max(1, corans.last_page),
        [corans.last_page],
    );

    const hasCustomSort =
        filters.sort !== 'id' || filters.direction !== 'desc';
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== 'id' || sortDraft.direction !== 'desc';

    const hasActiveFilters =
        filters.status.length > 0 ||
        filters.date_from !== null ||
        filters.date_to !== null;
    const hasFilterDraftChanges =
        (filterDraft.status || '') !== (filters.status[0] ?? '') ||
        (filterDraft.date_from || '') !== (filters.date_from ?? '') ||
        (filterDraft.date_to || '') !== (filters.date_to ?? '');
    const hasFilterDraftCustom =
        filterDraft.status !== '' ||
        filterDraft.date_from !== '' ||
        filterDraft.date_to !== '';

    const hasCustomEntries = filters.per_page !== 50;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== '50';

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
        const nextStatus =
            filterDraft.status === '' ? [] : [filterDraft.status];
        const nextDateFrom =
            filterDraft.date_from === '' ? null : filterDraft.date_from;
        const nextDateTo =
            filterDraft.date_to === '' ? null : filterDraft.date_to;

        const unchanged =
            nextStatus[0] === filters.status[0] &&
            nextStatus.length === filters.status.length &&
            nextDateFrom === filters.date_from &&
            nextDateTo === filters.date_to;

        if (unchanged) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            status: nextStatus,
            date_from: nextDateFrom,
            date_to: nextDateTo,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({
            status: '',
            date_from: '',
            date_to: '',
        });

        if (
            filters.status.length === 0 &&
            filters.date_from === null &&
            filters.date_to === null
        ) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            status: [],
            date_from: null,
            date_to: null,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visitIndex({
            page: 1,
            per_page: Number(entriesDraft),
        });
    };

    const clearEntries = () => {
        setEntriesDraft('50');
        setEntriesMenuOpen(false);
        visitIndex({
            page: 1,
            per_page: 50,
        });
    };

    return (
        <>
            <Head title="Coran" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Coran
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Data pengerjaan proses coran
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <CoranSpkStatusCards
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
                                    placeholder="Cari dokumen / nomor SPK"
                                    value={searchQuery}
                                    autoComplete="off"
                                    aria-label="Cari dokumen coran atau nomor SPK"
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
                                                status:
                                                    filters.status[0] ?? '',
                                                date_from:
                                                    filters.date_from ?? '',
                                                date_to:
                                                    filters.date_to ?? '',
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
                                                        !hasFilterDraftCustom &&
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
                                aria-label="Tambah dokumen coran"
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
                                    <th>Pengrajin</th>
                                    <th className="spkTableColCenter">
                                        Total SPK
                                    </th>
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Berat Hasil Coran (g)
                                    </th>
                                    {MATERIAL_COLOR_COLUMNS.map((column) => (
                                        <th
                                            key={column.key}
                                            className="spkTableColWeight spkTableColCenter"
                                        >
                                            {column.label}
                                        </th>
                                    ))}
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Susut (g)
                                    </th>
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Persentase Hasil Coran (%)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {corans.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data dokumen coran.
                                        </td>
                                    </tr>
                                ) : (
                                    corans.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className={[
                                                selectedIds.includes(item.id)
                                                    ? 'is-selected'
                                                    : '',
                                                isStatusIncomplete(
                                                    item.status,
                                                    item.statusLabel,
                                                )
                                                    ? 'is-incomplete'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ') || undefined}
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
                                            <td className="spkTableColDoc">
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
                                            <td>{item.transDate ?? '—'}</td>
                                            <td>{item.craftsmanName ?? '—'}</td>
                                            <td className="spkTableColCenter">
                                                {item.spkCount > 0
                                                    ? item.spkCount
                                                    : '—'}
                                            </td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    {TOTAL_WEIGHT_ROWS.map(
                                                        (row) => (
                                                            <div
                                                                key={row.key}
                                                                className="spkTableWeightRow"
                                                            >
                                                                <dt>
                                                                    {row.label}
                                                                </dt>
                                                                <dd>
                                                                    {formatWeightValue(
                                                                        item[
                                                                            row
                                                                                .key
                                                                        ],
                                                                    )}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </td>
                                            {MATERIAL_COLOR_COLUMNS.map(
                                                (column) => (
                                                    <td
                                                        key={column.key}
                                                        className="spkTableColWeight"
                                                    >
                                                        <dl className="spkTableWeightStack">
                                                            {MATERIAL_COLOR_ROWS.map(
                                                                (row) => (
                                                                    <div
                                                                        key={
                                                                            row.key
                                                                        }
                                                                        className="spkTableWeightRow"
                                                                    >
                                                                        <dt>
                                                                            {
                                                                                row.label
                                                                            }
                                                                        </dt>
                                                                        <dd>
                                                                            {formatWeightValue(
                                                                                item
                                                                                    .materialsByColor[
                                                                                    column
                                                                                        .key
                                                                                ][
                                                                                    row
                                                                                        .key
                                                                                ],
                                                                            )}
                                                                        </dd>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </dl>
                                                    </td>
                                                ),
                                            )}
                                            <td className="spkTableColWeight spkTableColCenter">
                                                <span className="spkTableShrinkValue">
                                                    {item.shrink ?? '—'}
                                                </span>
                                            </td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    {HASIL_CORAN_PERCENT_ROWS.map(
                                                        (row) => (
                                                            <div
                                                                key={row.key}
                                                                className={[
                                                                    'spkTableWeightRow',
                                                                    row.key ===
                                                                    'susut'
                                                                        ? 'is-shrink'
                                                                        : '',
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(' ')}
                                                            >
                                                                <dt>
                                                                    {row.label}
                                                                </dt>
                                                                <dd>
                                                                    {item
                                                                        .hasilCoranPercents[
                                                                        row.key
                                                                    ] ?? '—'}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </td>
                                            <td className="spkTableColCenter">
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
                            Total {corans.total} dokumen coran
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={corans.current_page <= 1}
                                onClick={() =>
                                    visitIndex({
                                        page: corans.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {corans.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={corans.current_page >= totalPages}
                                onClick={() =>
                                    visitIndex({
                                        page: corans.current_page + 1,
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

CoranIndex.layout = {
    activeMenu: 'Coran',
    pageTitle: 'Coran',
};
