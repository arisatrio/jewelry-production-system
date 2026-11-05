import { Head, Link, router } from '@inertiajs/react';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import {
    ActiveFilterSummary,
    describeDateRange,
    describeSort,
} from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as microStonesIndex } from '@/routes/inventory/micro-stones';
import { index as stoneTransactionsIndex } from '@/routes/inventory/stone-transactions';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

type MicroEventType = 'entry' | 'out';

type StoneTransaction = {
    id: number;
    period: string;
    direction: string;
    category: 'Penambahan' | 'Pemakaian' | 'Lainnya';
    transactionType: string;
    stone: string;
    pcs: string;
    crt: string | null;
    signedPcs: string;
    productionNo: string | null;
    documentNo: string | null;
    mountingDocumentId: number | null;
    notes: string | null;
    createdBy: string | null;
    createdDate: string | null;
};

type TransactionsPaginator = {
    data: StoneTransaction[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    transactions: TransactionsPaginator;
    total: string;
    activePeriod: { id: number; label: string } | null;
    filters: {
        search: string;
        date_from: string | null;
        date_to: string | null;
        event_type: MicroEventType | null;
        per_page: number;
        sort: string;
        direction: string;
    };
    filterOptions: {
        sort: SortOption[];
        direction: SortOption[];
    };
};

type SortOption = {
    value: string;
    label: string;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'created_date';
const DEFAULT_SORT_DIRECTION = 'desc';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const EVENT_TYPE_OPTIONS: {
    value: '' | MicroEventType;
    label: string;
}[] = [
    { value: '', label: 'Semua jenis' },
    { value: 'entry', label: 'Penambahan' },
    { value: 'out', label: 'Pemakaian' },
];

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

function eventTypeLabel(value: MicroEventType | null): string | null {
    if (value === null) {
        return null;
    }

    return (
        EVENT_TYPE_OPTIONS.find((option) => option.value === value)?.label
        ?? value
    );
}

function formatPcs(value: string): string {
    const numeric = Number(value);
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Math.abs(numeric));

    return numeric < 0 ? `(${formatted})` : formatted;
}

function formatCrt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
}

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

function categoryBadgeClass(
    category: StoneTransaction['category'],
): string {
    if (category === 'Pemakaian') {
        return 'spkStoneStockBadge is-unavailable';
    }

    if (category === 'Penambahan') {
        return 'spkStoneStockBadge is-available';
    }

    return 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
}

export default function StoneTransactionsIndex({
    transactions,
    total,
    activePeriod,
    filters,
    filterOptions,
}: PageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);
    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [eventTypeDraft, setEventTypeDraft] = useState(
        filters.event_type ?? '',
    );
    const [dateFromDraft, setDateFromDraft] = useState(
        filters.date_from ?? '',
    );
    const [dateToDraft, setDateToDraft] = useState(filters.date_to ?? '');

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setEventTypeDraft(filters.event_type ?? '');
        setDateFromDraft(filters.date_from ?? '');
        setDateToDraft(filters.date_to ?? '');
    }, [filters.event_type, filters.date_from, filters.date_to]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        date_from?: string | null;
        date_to?: string | null;
        event_type?: MicroEventType | null;
        sort?: string;
        direction?: string;
    }) => {
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;

        router.get(
            stoneTransactionsIndex.url({
                query: {
                    search:
                        ('search' in params ? params.search : filters.search)
                        || undefined,
                    date_from:
                        ('date_from' in params
                            ? params.date_from
                            : filters.date_from) || undefined,
                    date_to:
                        ('date_to' in params ? params.date_to : filters.date_to)
                        || undefined,
                    event_type:
                        ('event_type' in params
                            ? params.event_type
                            : filters.event_type) || undefined,
                    sort:
                        nextSort !== DEFAULT_SORT ? nextSort : undefined,
                    direction:
                        nextDirection !== DEFAULT_SORT_DIRECTION
                            ? nextDirection
                            : undefined,
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

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);

        if (params.get('create') === '1' && filters.event_type !== 'entry') {
            visit({ event_type: 'entry', page: 1 });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- landing restok pembelian batu mikro
    }, []);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            visit({ search: searchQuery, page: 1 });
        }, 300);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce search only
    }, [searchQuery, filters.search]);

    const totalPages = useMemo(
        () => Math.max(1, transactions.last_page),
        [transactions.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const hasActiveEventFilter = filters.event_type !== null;
    const hasActiveDateFrom = filters.date_from !== null && filters.date_from !== '';
    const hasActiveDateTo = filters.date_to !== null && filters.date_to !== '';
    const hasActiveTableFilters =
        hasActiveEventFilter || hasActiveDateFrom || hasActiveDateTo;

    const hasFilterDraftChanges =
        eventTypeDraft !== (filters.event_type ?? '') ||
        dateFromDraft !== (filters.date_from ?? '') ||
        dateToDraft !== (filters.date_to ?? '');

    const hasFilterDraftCustom =
        eventTypeDraft !== '' ||
        dateFromDraft !== '' ||
        dateToDraft !== '';

    const hasCustomSort =
        filters.sort !== DEFAULT_SORT
        || filters.direction !== DEFAULT_SORT_DIRECTION;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort
        || sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== DEFAULT_SORT
        || sortDraft.direction !== DEFAULT_SORT_DIRECTION;

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visit({
            page: 1,
            per_page: Number(entriesDraft),
        });
    };

    const clearEntries = () => {
        setEntriesDraft(String(DEFAULT_PER_PAGE));
        setEntriesMenuOpen(false);
        visit({
            page: 1,
            per_page: DEFAULT_PER_PAGE,
        });
    };

    const applySort = () => {
        if (
            sortDraft.sort === filters.sort
            && sortDraft.direction === filters.direction
        ) {
            setSortMenuOpen(false);

            return;
        }

        visit({
            sort: sortDraft.sort,
            direction: sortDraft.direction,
            page: 1,
        });
        setSortMenuOpen(false);
    };

    const clearSort = () => {
        setSortDraft({
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });

        if (
            filters.sort === DEFAULT_SORT
            && filters.direction === DEFAULT_SORT_DIRECTION
        ) {
            setSortMenuOpen(false);

            return;
        }

        visit({
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
            page: 1,
        });
        setSortMenuOpen(false);
    };

    const applyTableFilters = () => {
        setFilterMenuOpen(false);
        visit({
            page: 1,
            event_type: (eventTypeDraft as MicroEventType) || null,
            date_from: dateFromDraft || null,
            date_to: dateToDraft || null,
        });
    };

    const clearTableFilters = () => {
        setEventTypeDraft('');
        setDateFromDraft('');
        setDateToDraft('');
        setFilterMenuOpen(false);
        visit({
            page: 1,
            event_type: null,
            date_from: null,
            date_to: null,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setEventTypeDraft('');
        setDateFromDraft('');
        setDateToDraft('');
        visit({
            search: '',
            page: 1,
            event_type: null,
            date_from: null,
            date_to: null,
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });
    };

    const subtitle = activePeriod
        ? `Periode ${activePeriod.label} · neto ${formatPcs(total)} pcs`
        : 'Log masuk dan keluar batu mikro';

    return (
        <>
            <Head title="Transaksi Batu Mikro" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Transaksi Batu Mikro
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    {subtitle}
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
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
                                    aria-label="Cari log transaksi batu mikro"
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
                                                'spkTableHeaderTextBtn--finishing',
                                                hasCustomEntries
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            aria-label="Tampilkan"
                                            title="Tampilkan"
                                        >
                                            <Icon
                                                name={listIcon}
                                                mode="Decorative"
                                            />
                                            <span>Tampilkan</span>
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
                                                    {PER_PAGE_FILTER_OPTIONS.map(
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

                                <DropdownMenu
                                    open={filterMenuOpen}
                                    onOpenChange={(open) => {
                                        setFilterMenuOpen(open);

                                        if (open) {
                                            setEventTypeDraft(
                                                filters.event_type ?? '',
                                            );
                                            setDateFromDraft(
                                                filters.date_from ?? '',
                                            );
                                            setDateToDraft(
                                                filters.date_to ?? '',
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
                                                'spkTableHeaderTextBtn--finishing',
                                                hasActiveTableFilters
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
                                            <span>Filter</span>
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
                                                <span>Jenis</span>
                                                <select
                                                    value={eventTypeDraft}
                                                    aria-label="Jenis transaksi"
                                                    onChange={(event) =>
                                                        setEventTypeDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {EVENT_TYPE_OPTIONS.map(
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
                                                <span>Tanggal dari</span>
                                                <input
                                                    type="date"
                                                    value={dateFromDraft}
                                                    aria-label="Tanggal dari"
                                                    onChange={(event) =>
                                                        setDateFromDraft(
                                                            event.target.value,
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
                                                    value={dateToDraft}
                                                    aria-label="Tanggal sampai"
                                                    onChange={(event) =>
                                                        setDateToDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                />
                                            </label>
                                            <div className="spkTableHeaderSortActions--finishing">
                                                <button
                                                    type="button"
                                                    className="spkTableHeaderSortClear--finishing"
                                                    disabled={
                                                        !hasFilterDraftCustom &&
                                                        !hasActiveTableFilters
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearTableFilters();
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
                                                        applyTableFilters();
                                                    }}
                                                >
                                                    Terapkan
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>

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
                                                'spkTableHeaderTextBtn--finishing',
                                                hasCustomSort
                                                    ? 'is-active'
                                                    : '',
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
                                            <span>Urutkan</span>
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
                                                        !hasSortDraftCustom
                                                        && !hasCustomSort
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
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke stok batu mikro"
                                onClick={() =>
                                    router.visit(microStonesIndex.url())
                                }
                            >
                                <Icon name={navBackIcon} mode="Decorative" />
                                <span>Kembali ke Stok</span>
                            </button>
                        </div>
                    </div>

                    <ActiveFilterSummary
                        sort={
                            hasCustomSort && {
                                key: 'sort',
                                label: 'Urutkan',
                                value: describeSort(
                                    filters.sort,
                                    filters.direction,
                                    filterOptions.sort,
                                    filterOptions.direction,
                                ),
                                onRemove: clearSort,
                            }
                        }
                        filters={[
                            filters.search !== '' && {
                                key: 'search',
                                label: 'Pencarian',
                                value: filters.search,
                                onRemove: () => {
                                    setSearchQuery('');
                                    visit({ search: '', page: 1 });
                                },
                            },
                            hasActiveEventFilter && {
                                key: 'event_type',
                                label: 'Jenis',
                                value:
                                    eventTypeLabel(filters.event_type) ?? '—',
                                onRemove: () => {
                                    visit({ event_type: null, page: 1 });
                                },
                            },
                            (hasActiveDateFrom || hasActiveDateTo) && {
                                key: 'date_range',
                                label: 'Tanggal',
                                value: describeDateRange(
                                    filters.date_from,
                                    filters.date_to,
                                ),
                                onRemove: () => {
                                    visit({
                                        date_from: null,
                                        date_to: null,
                                        page: 1,
                                    });
                                },
                            },
                        ]}
                        onClearAll={clearAllFilters}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCenter">#</th>
                                    <th>Tanggal</th>
                                    <th className="spkTableColCenter">Jenis</th>
                                    <th>Batu</th>
                                    <th className="spkTableColCenter">Pcs</th>
                                    <th className="spkTableColCenter">CRT</th>
                                    <th className="spkTableColCenter">
                                        No. Pasang Batu
                                    </th>
                                    <th className="spkTableColCenter">
                                        No. SPK
                                    </th>
                                    <th>Deskripsi</th>
                                    <th>Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={10}>
                                            {activePeriod
                                                ? 'Tidak ada transaksi batu mikro.'
                                                : 'Periode batu aktif tidak tersedia.'}
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map(
                                        (transaction, index) => (
                                            <tr key={transaction.id}>
                                                <td className="spkTableColCenter tabular-nums">
                                                    {(transactions.current_page -
                                                        1) *
                                                        transactions.per_page +
                                                        index +
                                                        1}
                                                </td>
                                                <td>
                                                    {formatDateDisplay(
                                                        transaction.createdDate,
                                                    )}
                                                </td>
                                                <td className="spkTableColCenter">
                                                    <span
                                                        className={categoryBadgeClass(
                                                            transaction.category,
                                                        )}
                                                    >
                                                        {transaction.category}
                                                    </span>
                                                </td>
                                                <td className="font-medium">
                                                    {transaction.stone}
                                                </td>
                                                <td className="spkTableColCenter tabular-nums">
                                                    {formatPcs(
                                                        transaction.signedPcs,
                                                    )}
                                                </td>
                                                <td className="spkTableColCenter tabular-nums">
                                                    {formatCrt(transaction.crt)}
                                                </td>
                                                <td className="spkTableColCenter">
                                                    {transaction.mountingDocumentId !==
                                                        null &&
                                                    transaction.documentNo !==
                                                        null ? (
                                                        <Link
                                                            href={pasangBatuShow.url(
                                                                transaction.mountingDocumentId,
                                                            )}
                                                            className="spkProduksiLink"
                                                        >
                                                            {
                                                                transaction.documentNo
                                                            }
                                                        </Link>
                                                    ) : (
                                                        transaction.documentNo ??
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="spkTableColCenter">
                                                    {transaction.productionNo !==
                                                        null &&
                                                    transaction.productionNo !==
                                                        '' ? (
                                                        <Link
                                                            href={spkShow.url(
                                                                transaction.productionNo,
                                                            )}
                                                            className="spkProduksiLink"
                                                        >
                                                            {
                                                                transaction.productionNo
                                                            }
                                                        </Link>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td>
                                                    <div className="spkTableDescription">
                                                        <span>
                                                            {
                                                                transaction.transactionType
                                                            }
                                                        </span>
                                                        {transaction.notes ? (
                                                            <span className="spkTableDescriptionItem">
                                                                {
                                                                    transaction.notes
                                                                }
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="spkTableColCenter">
                                                    {transaction.createdBy !==
                                                        null ||
                                                    transaction.createdDate !==
                                                        null ? (
                                                        <div className="spkTableDescription items-center text-center">
                                                            <span>
                                                                {transaction.createdBy ??
                                                                    '—'}
                                                            </span>
                                                            {transaction.createdDate ? (
                                                                <span className="spkTableDescriptionItem">
                                                                    {formatDateDisplay(
                                                                        transaction.createdDate,
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {transactions.total} baris · neto{' '}
                            {formatPcs(total)} pcs
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={transactions.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: transactions.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {transactions.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    transactions.current_page >= totalPages
                                }
                                onClick={() =>
                                    visit({
                                        page: transactions.current_page + 1,
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

StoneTransactionsIndex.layout = {
    activeMenu: 'Transaksi Batu Mikro',
    pageTitle: 'Transaksi Batu Mikro',
};
