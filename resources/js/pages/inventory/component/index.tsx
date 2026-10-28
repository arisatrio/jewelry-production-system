import { Head, Link, router, usePage } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import chainLinkIcon from '@ui5/webcomponents-icons/dist/chain-link.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import productIcon from '@ui5/webcomponents-icons/dist/product.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import { ComponentAddDialog } from '@/pages/inventory/component/component-add-dialog';
import { ComponentRequestDialog } from '@/pages/inventory/component/component-request-dialog';
import {
    ComponentAddStockDialog,
    type ChainAddStockItem,
} from '@/pages/inventory/component/component-add-stock-dialog';
import {
    ActiveFilterSummary,
    describeSort,
} from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { edit, index as chainsIndex, show } from '@/routes/inventory/components';
import { index as chainTransactionsIndex } from '@/routes/inventory/component-transactions';

type ChainRow = {
    id: number;
    componentCategory: string;
    item: string;
    itemCode: string;
    itemName: string | null;
    description: string | null;
    stockIn: number;
    stockOut: number;
    balance: number;
    balanceLength: string;
};

type ItemsPaginator = {
    data: ChainRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = {
    value: string;
    label: string;
};

type ChainIndexProps = {
    items: ItemsPaginator;
    categories: string[];
    colorOptions: string[];
    filters: {
        search: string;
        category: string;
        per_page: number;
        sort: string;
        direction: string;
    };
    filterOptions: {
        sort: FilterOption[];
        direction: FilterOption[];
    };
    summary: {
        totalAvailable: number;
    };
    componentRequestCount: number;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'category';
const DEFAULT_DIRECTION = 'asc';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

export default function ChainIndex({
    items,
    categories,
    colorOptions,
    filters,
    filterOptions,
    summary,
    componentRequestCount,
}: ChainIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);
    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [categoryDraft, setCategoryDraft] = useState(filters.category);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));
    const [stockItem, setStockItem] = useState<ChainAddStockItem | null>(null);
    const [stockDialogOpen, setStockDialogOpen] = useState(false);
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [chainRequestDialogOpen, setComponentRequestDialogOpen] =
        useState(false);
    const componentRequestCountLabel =
        componentRequestCount.toLocaleString('id-ID');
    const pageErrors =
        usePage<{ errors?: Partial<Record<string, string>> }>().props
            .errors ?? {};

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        const hasComponentFormErrors =
            pageErrors.component_category !== undefined ||
            pageErrors.item !== undefined ||
            pageErrors.item_code !== undefined ||
            pageErrors.description !== undefined;

        if (hasComponentFormErrors) {
            setCreateDialogOpen(true);
        }
    }, [pageErrors]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        category?: string;
        sort?: string;
        direction?: string;
    }) => {
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;

        router.get(
            chainsIndex.url({
                query: {
                    search: (params.search ?? filters.search) || undefined,
                    category:
                        (params.category ?? filters.category) || undefined,
                    per_page: params.per_page ?? filters.per_page,
                    sort:
                        nextSort !== DEFAULT_SORT ? nextSort : undefined,
                    direction:
                        nextDirection !== DEFAULT_DIRECTION
                            ? nextDirection
                            : undefined,
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

    const hasActiveCategoryFilter = filters.category !== '';
    const hasCategoryDraftChanges = categoryDraft !== filters.category;
    const hasCategoryDraftCustom = categoryDraft !== '';

    const hasCustomSort =
        filters.sort !== DEFAULT_SORT ||
        filters.direction !== DEFAULT_DIRECTION;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== DEFAULT_SORT ||
        sortDraft.direction !== DEFAULT_DIRECTION;

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const applySort = () => {
        if (
            sortDraft.sort === filters.sort &&
            sortDraft.direction === filters.direction
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
            direction: DEFAULT_DIRECTION,
        });

        if (
            filters.sort === DEFAULT_SORT &&
            filters.direction === DEFAULT_DIRECTION
        ) {
            setSortMenuOpen(false);

            return;
        }

        visit({
            sort: DEFAULT_SORT,
            direction: DEFAULT_DIRECTION,
            page: 1,
        });
        setSortMenuOpen(false);
    };

    const applyCategoryFilter = () => {
        if (categoryDraft === filters.category) {
            setFilterMenuOpen(false);

            return;
        }

        visit({ category: categoryDraft, page: 1 });
        setFilterMenuOpen(false);
    };

    const clearCategoryFilter = () => {
        setCategoryDraft('');

        if (filters.category === '') {
            setFilterMenuOpen(false);

            return;
        }

        visit({ category: '', page: 1 });
        setFilterMenuOpen(false);
    };

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

    const openStockDialog = (row: ChainRow) => {
        setStockItem({
            id: row.id,
            item: row.item,
            itemCode: row.itemCode,
            itemName: row.itemName,
        });
        setStockDialogOpen(true);
    };

    const handleStockDialogOpenChange = (open: boolean) => {
        setStockDialogOpen(open);

        if (!open) {
            setStockItem(null);
        }
    };

    return (
        <>
            <Head title="Stok Komponen" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div
                        className="spkTableToolbar spkTableHeaderBar--finishing spkTableHeaderBar--chain"
                    >
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Stok Komponen
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Stok komponen yang tersedia di gudang.
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableStatusAlerts--finishing"
                                role="status"
                                aria-label={`Total stok tersedia ${summary.totalAvailable.toLocaleString('id-ID')} unit`}
                            >
                                <MessageStrip
                                    design="Positive"
                                    hideCloseButton
                                    className="spkTableStatusAlertStrip--finishing"
                                >
                                    <span
                                        className="spkTableStatusAlertLabel--finishing"
                                    >
                                        Total stok tersedia
                                    </span>
                                    <strong
                                        className="spkTableStatusAlertCount--finishing"
                                    >
                                        {summary.totalAvailable.toLocaleString(
                                            'id-ID',
                                        )}
                                    </strong>
                                </MessageStrip>
                            </div>
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
                                    aria-label="Cari chain"
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
                                            setCategoryDraft(filters.category);
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={[
                                                'spkTableHeaderIconBtn--finishing',
                                                'spkTableHeaderIconTrigger--finishing',
                                                hasActiveCategoryFilter
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
                                                <span>Kategori</span>
                                                <select
                                                    value={categoryDraft}
                                                    aria-label="Kategori chain"
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
                                                    {categories.map(
                                                        (category) => (
                                                            <option
                                                                key={category}
                                                                value={category}
                                                            >
                                                                {category}
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
                                                        !hasCategoryDraftCustom &&
                                                        !hasActiveCategoryFilter
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        clearCategoryFilter();
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                                <Button
                                                    design="Default"
                                                    disabled={
                                                        !hasCategoryDraftChanges
                                                    }
                                                    className="spkTableHeaderSortApply--finishing"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        applyCategoryFilter();
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
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Log transaksi chain"
                                onClick={() =>
                                    router.visit(chainTransactionsIndex.url())
                                }
                            >
                                <Icon name={listIcon} mode="Decorative" />
                                <span>Log Transaksi</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn"
                                aria-label="Tambah chain"
                                onClick={() => setCreateDialogOpen(true)}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah</span>
                            </button>
                        </div>
                    </div>

                    <div className="spkStoreStockRequestBar">
                        <Button
                            className="spkStoreStockRequestBtn"
                            design="Default"
                            icon={chainLinkIcon}
                            accessibleName={`Permintaan Komponen: ${componentRequestCountLabel} baris menunggu stok`}
                            tooltip="Kebutuhan komponen dari SPK yang belum keluar stok"
                            onClick={() => setComponentRequestDialogOpen(true)}
                        >
                            Permintaan Komponen
                            <span
                                className="spkStoreStockRequestCount"
                                aria-hidden="true"
                            >
                                {componentRequestCountLabel}
                            </span>
                        </Button>
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
                            hasActiveCategoryFilter && {
                                key: 'category',
                                label: 'Kategori',
                                value: filters.category,
                                onRemove: () =>
                                    visit({ category: '', page: 1 }),
                            },
                        ]}
                        onClearAll={() => {
                            setSearchQuery('');
                            setSortDraft({
                                sort: DEFAULT_SORT,
                                direction: DEFAULT_DIRECTION,
                            });
                            visit({
                                search: '',
                                category: '',
                                sort: DEFAULT_SORT,
                                direction: DEFAULT_DIRECTION,
                                page: 1,
                            });
                        }}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColCenter">#</th>
                                    <th>Kategori</th>
                                    <th>Item</th>
                                    <th>Kode</th>
                                    <th>Keterangan</th>
                                    <th className="spkTableColCenter">
                                        Tersedia
                                    </th>
                                    <th className="spkTableColCenter">
                                        Terpakai
                                    </th>
                                    <th className="spkTableColCenter">Stok</th>
                                    <th
                                        className="spkTableActionCol spkTableActionCol--iconGroup spkTableColCenter"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9}>
                                            Tidak ada data komponen.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item, index) => (
                                        <tr key={item.id}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(items.current_page - 1) *
                                                    items.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td>{item.componentCategory}</td>
                                            <td>
                                                <Link
                                                    href={show.url(item.id)}
                                                    className="spkProduksiLink"
                                                >
                                                    {item.item}
                                                </Link>
                                            </td>
                                            <td>{item.itemCode}</td>
                                            <td>{item.description ?? '—'}</td>
                                            <td
                                                className="spkTableColCenter spkChainStockIn tabular-nums"
                                            >
                                                {item.stockIn}
                                            </td>
                                            <td
                                                className="spkTableColCenter spkChainStockOut tabular-nums"
                                            >
                                                {item.stockOut}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                <span className="spkChainStockBalanceLabel">
                                                    {item.balance}
                                                </span>
                                            </td>
                                            <td
                                                className="spkTableActionCol spkTableActionCol--iconGroup spkTableColCenter"
                                            >
                                                <div
                                                    className="spkTableRowActions spkTableRowActions--row"
                                                >
                                                    <Button
                                                        design="Transparent"
                                                        type="Button"
                                                        icon={productIcon}
                                                        accessibleName="Detail barang"
                                                        title="Detail barang"
                                                        onClick={() =>
                                                            router.visit(
                                                                show.url(
                                                                    item.id,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        design="Transparent"
                                                        type="Button"
                                                        icon={addIcon}
                                                        accessibleName="Tambah stok"
                                                        title="Tambah stok"
                                                        onClick={() =>
                                                            openStockDialog(item)
                                                        }
                                                    />
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
                            Total {items.total} chain
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

            <ComponentAddDialog
                categories={categories}
                open={createDialogOpen}
                onOpenChange={setCreateDialogOpen}
            />

            <ComponentAddStockDialog
                item={stockItem}
                colorOptions={colorOptions}
                open={stockDialogOpen}
                returnTo="index"
                onOpenChange={handleStockDialogOpenChange}
            />

            <ComponentRequestDialog
                open={chainRequestDialogOpen}
                onOpenChange={setComponentRequestDialogOpen}
            />
        </>
    );
}

ChainIndex.layout = {
    activeMenu: 'Stok Komponen',
    pageTitle: 'Stok Komponen',
};
