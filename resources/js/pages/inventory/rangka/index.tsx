import { Head, Link, router } from '@inertiajs/react';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import {
    ActiveFilterSummary,
    describeSort,
} from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SpkTableDescriptionCell } from '@/components/spk/spk-list-cells';
import {
    index as stokRangkaIndex,
    show as stokRangkaShow,
} from '@/routes/inventory/frame-stocks';
import { show as spkShow } from '@/routes/spk';

type FilterOption = {
    value: string;
    label: string;
};

type StokRangkaRow = {
    id: number;
    docNo: string | null;
    skuCode: string | null;
    skuBreakdownName: string | null;
    category: string | null;
    itemName: string | null;
    spkNo: string | null;
    dateIn: string | null;
    dateOut: string | null;
    goldWeight: string | null;
    goldColor: string | null;
    status: string | null;
    createdBy: string | null;
    updatedBy: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

type ItemsPaginator = {
    data: StokRangkaRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type StockSummaryMetrics = {
    count: number;
    weight: string;
};

type StokRangkaIndexProps = {
    items: ItemsPaginator;
    summary: {
        count: number;
        weight: string;
    };
    stockSummaries: {
        ready: StockSummaryMetrics;
        used: StockSummaryMetrics;
    };
    filters: {
        search: string;
        per_page: number;
        sort: string;
        direction: string;
        status: string | null;
        category: string | null;
        gold_color: string | null;
    };
    filterOptions: {
        sort: FilterOption[];
        direction: FilterOption[];
    };
    categoryOptions: FilterOption[];
    goldColorOptions: FilterOption[];
    statusOptions: FilterOption[];
    lastUpdatedAt: string | null;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'updated_at';
const DEFAULT_SORT_DIRECTION = 'desc';
const DEFAULT_STATUS = 'READY';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const CENTER_COL_CLASS = 'spkTableColCenter text-center';

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

function formatDateTimeDisplay(value: string | null): string {
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
        return `${dateLabel} 00:00:00`;
    }

    const [hours = '00', minutes = '00', seconds = '00'] = timePart.split(':');

    return `${dateLabel} ${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}:${seconds.padStart(2, '0')}`;
}

function formatWeight(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function statusBadgeClass(status: string | null): string {
    if (status === 'READY') {
        return 'spkStoneStockBadge is-available';
    }

    if (status === 'USED') {
        return 'spkStoneStockBadge is-unavailable';
    }

    if (status !== null && status !== '') {
        return 'inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800';
    }

    return 'spkStoneStockBadge is-unavailable';
}

function auditCell(actor: string | null, date: string | null): ReactNode {
    if (actor === null && date === null) {
        return '—';
    }

    return (
        <div className="flex flex-col items-center gap-0.5 text-center">
            <span>{actor ?? '—'}</span>
            {date ? (
                <span className="spkTableDescriptionItem">
                    {formatDateTimeDisplay(date)}
                </span>
            ) : null}
        </div>
    );
}

function statusQueryValue(
    status: string | null | undefined,
    hasExplicitStatus: boolean,
): string | undefined {
    const value = hasExplicitStatus ? status : status;

    if (value === null || value === '') {
        return 'all';
    }

    if (value === DEFAULT_STATUS) {
        return undefined;
    }

    return value;
}

function resolveStatusQuery(
    params: { status?: string | null },
    currentStatus: string | null,
): string | undefined {
    if ('status' in params) {
        return statusQueryValue(params.status, true);
    }

    return statusQueryValue(currentStatus, false);
}

function optionFilterLabel(
    value: string | null,
    options: FilterOption[],
): string | null {
    if (value === null || value === '') {
        return null;
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

export default function StokRangkaIndex({
    items,
    stockSummaries,
    filters,
    filterOptions,
    categoryOptions,
    goldColorOptions,
    statusOptions,
    lastUpdatedAt,
}: StokRangkaIndexProps) {
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
    const [statusDraft, setStatusDraft] = useState(filters.status ?? '');
    const [categoryDraft, setCategoryDraft] = useState(filters.category ?? '');
    const [goldColorDraft, setGoldColorDraft] = useState(
        filters.gold_color ?? '',
    );

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setStatusDraft(filters.status ?? '');
    }, [filters.status]);

    useEffect(() => {
        setCategoryDraft(filters.category ?? '');
    }, [filters.category]);

    useEffect(() => {
        setGoldColorDraft(filters.gold_color ?? '');
    }, [filters.gold_color]);

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
        status?: string | null;
        category?: string | null;
        gold_color?: string | null;
        sort?: string;
        direction?: string;
    }) => {
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;

        router.get(
            stokRangkaIndex.url({
                query: {
                    search:
                        ('search' in params ? params.search : filters.search)
                        || undefined,
                    status: resolveStatusQuery(params, filters.status),
                    category:
                        ('category' in params
                            ? params.category
                            : filters.category) || undefined,
                    gold_color:
                        ('gold_color' in params
                            ? params.gold_color
                            : filters.gold_color) || undefined,
                    sort: nextSort !== DEFAULT_SORT ? nextSort : undefined,
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
        () => Math.max(1, items.last_page),
        [items.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const hasCustomSort =
        filters.sort !== DEFAULT_SORT
        || filters.direction !== DEFAULT_SORT_DIRECTION;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort
        || sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== DEFAULT_SORT
        || sortDraft.direction !== DEFAULT_SORT_DIRECTION;

    const hasActiveStatusFilter =
        filters.status !== null && filters.status !== DEFAULT_STATUS;
    const hasActiveCategoryFilter = filters.category !== null;
    const hasActiveGoldColorFilter = filters.gold_color !== null;
    const hasActiveTableFilters =
        hasActiveStatusFilter
        || hasActiveCategoryFilter
        || hasActiveGoldColorFilter;

    const hasFilterDraftChanges =
        statusDraft !== (filters.status ?? '')
        || categoryDraft !== (filters.category ?? '')
        || goldColorDraft !== (filters.gold_color ?? '');

    const hasFilterDraftCustom =
        (statusDraft !== '' && statusDraft !== DEFAULT_STATUS)
        || (statusDraft === '' && filters.status === DEFAULT_STATUS)
        || categoryDraft !== ''
        || goldColorDraft !== '';

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
            status: statusDraft !== '' ? statusDraft : 'all',
            category: categoryDraft || null,
            gold_color: goldColorDraft || null,
        });
    };

    const clearTableFilters = () => {
        setStatusDraft(DEFAULT_STATUS);
        setCategoryDraft('');
        setGoldColorDraft('');
        setFilterMenuOpen(false);
        visit({
            page: 1,
            status: DEFAULT_STATUS,
            category: null,
            gold_color: null,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setStatusDraft(DEFAULT_STATUS);
        setCategoryDraft('');
        setGoldColorDraft('');
        visit({
            search: '',
            page: 1,
            status: DEFAULT_STATUS,
            category: null,
            gold_color: null,
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });
    };

    return (
        <>
            <Head title="Stok Rangka" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Stok Rangka
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Kelola stok rangka; Terakhir diperbaharui:{' '}
                                    {formatDateTimeDisplay(lastUpdatedAt)}
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
                                    aria-label="Cari stok rangka"
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
                                                        !hasEntriesDraftCustom
                                                        && !hasCustomEntries
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
                                            setStatusDraft(
                                                filters.status ?? '',
                                            );
                                            setCategoryDraft(
                                                filters.category ?? '',
                                            );
                                            setGoldColorDraft(
                                                filters.gold_color ?? '',
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
                                                <span>Status</span>
                                                <select
                                                    value={statusDraft}
                                                    aria-label="Filter status"
                                                    onChange={(event) =>
                                                        setStatusDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua status
                                                    </option>
                                                    {statusOptions.map(
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
                                                <span>Kategori</span>
                                                <select
                                                    value={categoryDraft}
                                                    aria-label="Filter kategori"
                                                    onChange={(event) =>
                                                        setCategoryDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua kategori
                                                    </option>
                                                    {categoryOptions.map(
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
                                                <span>Warna emas</span>
                                                <select
                                                    value={goldColorDraft}
                                                    aria-label="Filter warna emas"
                                                    onChange={(event) =>
                                                        setGoldColorDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua warna
                                                    </option>
                                                    {goldColorOptions.map(
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
                                                        !hasFilterDraftCustom
                                                        && !hasActiveTableFilters
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
                        </div>
                    </div>

                    <div
                        className="spkStatusCards spkStatusCards--2 spkStatusCards--dossierStock"
                        role="group"
                        aria-label="Ringkasan stok rangka"
                    >
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockAvailable',
                                filters.status === 'READY' ? 'is-filterActive' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.status === 'READY'}
                            onClick={() =>
                                visit({
                                    status:
                                        filters.status === 'READY'
                                            ? 'all'
                                            : 'READY',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">Ready</span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {stockSummaries.ready.count.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">pcs</span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatWeight(stockSummaries.ready.weight)} g
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockUsed',
                                filters.status === 'USED' ? 'is-filterActive' : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.status === 'USED'}
                            onClick={() =>
                                visit({
                                    status:
                                        filters.status === 'USED'
                                            ? null
                                            : 'USED',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">Used</span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {stockSummaries.used.count.toLocaleString(
                                        'id-ID',
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">pcs</span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatWeight(stockSummaries.used.weight)} g
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
                            hasActiveStatusFilter && {
                                key: 'status',
                                label: 'Status',
                                value:
                                    optionFilterLabel(
                                        filters.status,
                                        statusOptions,
                                    ) ?? '—',
                                onRemove: () => {
                                    visit({ status: null, page: 1 });
                                },
                            },
                            hasActiveCategoryFilter && {
                                key: 'category',
                                label: 'Kategori',
                                value:
                                    optionFilterLabel(
                                        filters.category,
                                        categoryOptions,
                                    ) ?? '—',
                                onRemove: () => {
                                    visit({ category: null, page: 1 });
                                },
                            },
                            hasActiveGoldColorFilter && {
                                key: 'gold_color',
                                label: 'Warna emas',
                                value:
                                    optionFilterLabel(
                                        filters.gold_color,
                                        goldColorOptions,
                                    ) ?? '—',
                                onRemove: () => {
                                    visit({ gold_color: null, page: 1 });
                                },
                            },
                        ]}
                        onClearAll={clearAllFilters}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColDoc--spk spkTableColDoc--dossierId">
                                        ID
                                    </th>
                                    <th>Kategori</th>
                                    <th>SKU</th>
                                    <th>Nama item</th>
                                    <th className={CENTER_COL_CLASS}>
                                        Tanggal masuk
                                    </th>
                                    <th className={CENTER_COL_CLASS}>
                                        Tanggal keluar
                                    </th>
                                    <th className={CENTER_COL_CLASS}>
                                        Warna emas
                                    </th>
                                    <th className={CENTER_COL_CLASS}>
                                        Berat (g)
                                    </th>
                                    <th className={CENTER_COL_CLASS}>Status</th>
                                    <th className={CENTER_COL_CLASS}>No. SPK</th>
                                    <th className={CENTER_COL_CLASS}>
                                        Dibuat oleh
                                    </th>
                                    <th className={CENTER_COL_CLASS}>
                                        Diubah oleh
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data stok rangka.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item) => (
                                        <tr key={item.id}>
                                            <td className="spkTableColDoc spkTableColDoc--spk spkTableColDoc--dossierId font-medium">
                                                {item.docNo !== null
                                                && item.docNo !== '' ? (
                                                    <Link
                                                        href={stokRangkaShow.url(
                                                            item.id,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {item.docNo}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>{item.category ?? '—'}</td>
                                            <td>
                                                <SpkTableDescriptionCell
                                                    row={{
                                                        typeSkuLabel:
                                                            item.skuCode,
                                                        itemDescription:
                                                            item.skuBreakdownName,
                                                        skuAssigned: Boolean(
                                                            item.skuCode?.trim()
                                                            || item
                                                                .skuBreakdownName
                                                                ?.trim(),
                                                        ),
                                                    }}
                                                />
                                            </td>
                                            <td>{item.itemName ?? '—'}</td>
                                            <td className={CENTER_COL_CLASS}>
                                                {formatDateDisplay(item.dateIn)}
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                {formatDateDisplay(item.dateOut)}
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                {item.goldColor ?? '—'}
                                            </td>
                                            <td
                                                className={`${CENTER_COL_CLASS} tabular-nums`}
                                            >
                                                {formatWeight(item.goldWeight)}
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                <div className="flex justify-center">
                                                    <span
                                                        className={statusBadgeClass(
                                                            item.status,
                                                        )}
                                                    >
                                                        {item.status ?? '—'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                {item.spkNo !== null
                                                && item.spkNo !== '' ? (
                                                    <Link
                                                        href={spkShow.url(
                                                            item.spkNo,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {item.spkNo}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                {auditCell(
                                                    item.createdBy,
                                                    item.createdAt,
                                                )}
                                            </td>
                                            <td className={CENTER_COL_CLASS}>
                                                {auditCell(
                                                    item.updatedBy,
                                                    item.updatedAt,
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
                            Total {items.total} baris
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={items.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: items.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {items.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={items.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: items.current_page + 1,
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

StokRangkaIndex.layout = {
    activeMenu: 'Stok Rangka',
    pageTitle: 'Stok Rangka',
};
