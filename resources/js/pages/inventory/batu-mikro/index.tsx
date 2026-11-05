import { Head, router } from '@inertiajs/react';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import {
    ActiveFilterSummary,
    describeSort,
} from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index } from '@/routes/inventory/micro-stones';
import { index as stoneTransactionsIndex } from '@/routes/inventory/stone-transactions';
import {
    formatMicroStoneBalanceCrt,
    formatMicroStonePcs,
} from '@/pages/inventory/batu-mikro/micro-stone-display';
import {
    MicroStoneAdjustStockDialog,
    type MicroStoneStockDialogKind,
} from '@/pages/inventory/batu-mikro/micro-stone-adjust-stock-dialog';

type StockStatus = 'available' | 'empty';

type MicroStone = {
    id: number;
    name: string;
    parcel: string | null;
    size: string | null;
    shape: string | null;
    pcsIn: string;
    pcsOut: string;
    balancePcs: string;
    reservedPcs: string;
    availablePcs: string;
    balanceCrt: string;
};

type StonesPaginator = {
    data: MicroStone[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PageProps = {
    stones: StonesPaginator;
    totals: {
        pcs: string;
        crt: string;
    };
    statusSummaries: {
        balance: StockSummaryMetrics;
        available: StockSummaryMetrics;
        empty: StockSummaryMetrics;
    };
    activePeriod: { id: number; label: string } | null;
    addStockStone: MicroStone | null;
    filters: {
        search: string;
        stock_status: StockStatus | null;
        per_page: number;
        sort: string;
        direction: string;
    };
    filterOptions: {
        sort: SortOption[];
        direction: SortOption[];
    };
};

type StockSummaryMetrics = {
    stoneCount: number;
    pcs: string;
    crt: string;
    reservedPcs: string;
};

type SortOption = {
    value: string;
    label: string;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'name';
const DEFAULT_SORT_DIRECTION = 'asc';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const STOCK_STATUS_OPTIONS: { value: '' | StockStatus; label: string }[] = [
    { value: '', label: 'Semua stok' },
    { value: 'available', label: 'Ada stok' },
    { value: 'empty', label: 'Stok kosong / minus' },
];

function stockStatusLabel(value: StockStatus | null): string | null {
    if (value === null) {
        return null;
    }

    return (
        STOCK_STATUS_OPTIONS.find((option) => option.value === value)?.label
        ?? value
    );
}

function formatPcs(value: string): string {
    return formatMicroStonePcs(value);
}

function formatCrt(value: string): string {
    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
}

function formatBalanceCrt(value: string): string {
    return formatMicroStoneBalanceCrt(value);
}

function formatSummaryPcs(value: string): string {
    return Number(value).toLocaleString('id-ID', {
        maximumFractionDigits: 2,
    });
}

function formatSummaryStoneCount(value: number): string {
    return value.toLocaleString('id-ID');
}

function availableStockBadgeClass(value: string): string {
    const numeric = Number(value);

    if (numeric <= 0) {
        return 'spkStoneStockBadge is-unavailable';
    }

    return 'spkStoneStockBadge is-available';
}

export default function MicroStoneIndex({
    stones,
    totals,
    statusSummaries,
    activePeriod,
    addStockStone,
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
    const [stockStatusDraft, setStockStatusDraft] = useState(
        filters.stock_status ?? '',
    );
    const [stockDialogStone, setStockDialogStone] =
        useState<MicroStone | null>(null);
    const [stockDialogKind, setStockDialogKind] =
        useState<MicroStoneStockDialogKind | null>(null);

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setStockStatusDraft(filters.stock_status ?? '');
    }, [filters.stock_status]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        if (addStockStone === null) {
            return;
        }

        setStockDialogStone(addStockStone);
        setStockDialogKind('addition');
    }, [addStockStone]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        stock_status?: StockStatus | null;
        sort?: string;
        direction?: string;
    }) => {
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;

        router.get(
            index.url({
                query: {
                    search:
                        ('search' in params ? params.search : filters.search)
                        || undefined,
                    stock_status:
                        ('stock_status' in params
                            ? params.stock_status
                            : filters.stock_status) || undefined,
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
        () => Math.max(1, stones.last_page),
        [stones.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const hasActiveStockFilter = filters.stock_status !== null;
    const hasActiveTableFilters = hasActiveStockFilter;

    const hasFilterDraftChanges =
        stockStatusDraft !== (filters.stock_status ?? '');

    const hasFilterDraftCustom = stockStatusDraft !== '';

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
            stock_status: (stockStatusDraft as StockStatus) || null,
        });
    };

    const clearTableFilters = () => {
        setStockStatusDraft('');
        setFilterMenuOpen(false);
        visit({
            page: 1,
            stock_status: null,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setStockStatusDraft('');
        visit({
            search: '',
            page: 1,
            stock_status: null,
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });
    };

    const openStockDialog = (
        stone: MicroStone,
        kind: MicroStoneStockDialogKind,
    ): void => {
        setStockDialogStone(stone);
        setStockDialogKind(kind);
    };

    const handleStockDialogOpenChange = (open: boolean): void => {
        if (!open) {
            setStockDialogStone(null);
            setStockDialogKind(null);
        }
    };

    const stockDialogOpen =
        stockDialogStone !== null && stockDialogKind !== null;

    const subtitle = activePeriod
        ? `Periode ${activePeriod.label} · saldo ${formatPcs(totals.pcs)} pcs · ${formatCrt(totals.crt)} crt`
        : 'Kelola stok batu mikro per periode aktif';

    return (
        <>
            <Head title="Stok Batu Mikro" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Stok Batu Mikro
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
                                    aria-label="Cari stok batu mikro"
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
                                            setStockStatusDraft(
                                                filters.stock_status ?? '',
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
                                                <span>Status stok</span>
                                                <select
                                                    value={stockStatusDraft}
                                                    aria-label="Filter status stok"
                                                    onChange={(event) =>
                                                        setStockStatusDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {STOCK_STATUS_OPTIONS.map(
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
                                aria-label="Log transaksi batu mikro"
                                onClick={() =>
                                    router.visit(stoneTransactionsIndex.url())
                                }
                            >
                                <Icon name={listIcon} mode="Decorative" />
                                <span>Log Transaksi</span>
                            </button>
                        </div>
                    </div>

                    <div
                        className="spkStatusCards spkStatusCards--3 spkStatusCards--dossierStock"
                        role="group"
                        aria-label="Ringkasan stok batu mikro"
                    >
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockAvailable',
                                filters.stock_status === null
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.stock_status === null}
                            onClick={() =>
                                visit({
                                    stock_status: null,
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">
                                Total saldo
                            </span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {formatSummaryPcs(
                                        statusSummaries.balance.pcs,
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">
                                    pcs
                                </span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatCrt(statusSummaries.balance.crt)} crt
                                </span>
                                <span className="spkStatusCardHintSep">·</span>
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryStoneCount(
                                        statusSummaries.balance.stoneCount,
                                    )}{' '}
                                    batu
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockReserved',
                                filters.stock_status === 'available'
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.stock_status === 'available'}
                            onClick={() =>
                                visit({
                                    stock_status:
                                        filters.stock_status === 'available'
                                            ? null
                                            : 'available',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">Ada stok</span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {formatSummaryPcs(
                                        statusSummaries.available.pcs,
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">
                                    pcs
                                </span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatCrt(statusSummaries.available.crt)}{' '}
                                    crt
                                </span>
                                <span className="spkStatusCardHintSep">·</span>
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryPcs(
                                        statusSummaries.available.reservedPcs,
                                    )}{' '}
                                    reserved
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockUsed',
                                filters.stock_status === 'empty'
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.stock_status === 'empty'}
                            onClick={() =>
                                visit({
                                    stock_status:
                                        filters.stock_status === 'empty'
                                            ? null
                                            : 'empty',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">
                                Stok kosong / minus
                            </span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {formatSummaryPcs(statusSummaries.empty.pcs)}
                                </span>
                                <span className="spkStatusCardCountUnit">
                                    pcs
                                </span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatCrt(statusSummaries.empty.crt)} crt
                                </span>
                                <span className="spkStatusCardHintSep">·</span>
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryStoneCount(
                                        statusSummaries.empty.stoneCount,
                                    )}{' '}
                                    batu
                                </span>
                            </span>
                        </button>
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
                            hasActiveStockFilter && {
                                key: 'stock_status',
                                label: 'Status stok',
                                value:
                                    stockStatusLabel(filters.stock_status)
                                    ?? '—',
                                onRemove: () => {
                                    visit({ stock_status: null, page: 1 });
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
                                    <th>Bentuk</th>
                                    <th>Batu</th>
                                    <th>Parcel</th>
                                    <th className="spkTableColCenter">
                                        Ukuran
                                    </th>
                                    <th className="spkTableColCenter">
                                        Masuk (pcs)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Keluar (pcs)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Saldo (pcs)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Reserved (pcs)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Tersedia (pcs)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Saldo (crt)
                                    </th>
                                    <th className="spkTableColCenter spkMicroStoneActionCol">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {stones.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            {activePeriod
                                                ? 'Tidak ada batu mikro.'
                                                : 'Periode batu aktif tidak tersedia.'}
                                        </td>
                                    </tr>
                                ) : (
                                    stones.data.map((stone, index) => (
                                        <tr key={stone.id}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(stones.current_page - 1) *
                                                    stones.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td>{stone.shape ?? '—'}</td>
                                            <td className="font-medium">
                                                {stone.name}
                                            </td>
                                            <td>{stone.parcel ?? '—'}</td>
                                            <td className="spkTableColCenter">
                                                {stone.size
                                                    ? `${stone.size} mm`
                                                    : '—'}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums spkChainStockIn">
                                                {formatPcs(stone.pcsIn)}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums spkChainStockOut">
                                                {formatPcs(stone.pcsOut)}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatPcs(stone.balancePcs)}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatPcs(stone.reservedPcs)}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                <span
                                                    className={availableStockBadgeClass(
                                                        stone.availablePcs,
                                                    )}
                                                >
                                                    {formatPcs(
                                                        stone.availablePcs,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatBalanceCrt(stone.balanceCrt)}
                                            </td>
                                            <td className="spkTableColCenter spkMicroStoneActionCol">
                                                <div className="spkMicroStoneStockActions">
                                                    <button
                                                        type="button"
                                                        className="spkChainFulfillmentReceiveBtn spkMicroStoneStockBtn"
                                                        disabled={
                                                            activePeriod ===
                                                            null
                                                        }
                                                        aria-label={`Tambah stok ${stone.name}`}
                                                        title="Tambah stok"
                                                        onClick={() => {
                                                            openStockDialog(
                                                                stone,
                                                                'addition',
                                                            );
                                                        }}
                                                    >
                                                        <span className="spkChainFulfillmentActionBtnLabel">
                                                            (+) Stok
                                                        </span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="spkMicroStoneStockDeductBtn spkMicroStoneStockBtn"
                                                        disabled={
                                                            activePeriod ===
                                                            null
                                                        }
                                                        aria-label={`Kurangi stok ${stone.name}`}
                                                        title="Kurangi stok"
                                                        onClick={() => {
                                                            openStockDialog(
                                                                stone,
                                                                'deduction',
                                                            );
                                                        }}
                                                    >
                                                        <span className="spkChainFulfillmentActionBtnLabel">
                                                            (-) Stok
                                                        </span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {stones.total} baris
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={stones.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: stones.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {stones.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={stones.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: stones.current_page + 1,
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <MicroStoneAdjustStockDialog
                stone={
                    stockDialogStone !== null
                        ? {
                              id: stockDialogStone.id,
                              name: stockDialogStone.name,
                              balancePcs: stockDialogStone.balancePcs,
                              availablePcs: stockDialogStone.availablePcs,
                              balanceCrt: stockDialogStone.balanceCrt,
                          }
                        : null
                }
                kind={stockDialogKind}
                open={stockDialogOpen}
                onOpenChange={handleStockDialogOpenChange}
            />
        </>
    );
}

MicroStoneIndex.layout = {
    activeMenu: 'Stok Batu Mikro',
    pageTitle: 'Stok Batu Mikro',
};
