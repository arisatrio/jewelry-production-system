import { Head, router } from '@inertiajs/react';
import documentIcon from '@ui5/webcomponents-icons/dist/document-text.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import {
    ActiveFilterSummary,
    describeDateRange,
    describeSort,
} from '@/components/active-filter-summary';
import {
    SPK_DIAMOND_INDEX_STONE_DETAIL_HEADERS,
    SpkDiamondIndexDocumentRows,
} from '@/components/spk/spk-diamond-index-document-rows';
import { SpkDiamondReceiptHistoryDialog } from '@/components/spk/spk-diamond-receipt-history-dialog';
import { SpkDiamondReceiptPrintDialog } from '@/components/spk/spk-diamond-receipt-print-dialog';
import type { SpkDiamondDetailStoneRow } from '@/components/spk/spk-stone-allocated-display-cells';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Checkbox } from '@/components/ui/checkbox';
import { index as spkDiamondIndex } from '@/routes/spk-diamond';

type SpkDiamondListRow = {
    id: number;
    docNo: string;
    tanggal: string;
    spkRowId: number;
    spkNo: string | null;
    orderReference?: string | null;
    spkType?: string | null;
    orderTypeLabel?: string | null;
    typeSkuLabel: string | null;
    itemDescription: string | null;
    skuAssigned: boolean;
    spkImageUrl: string | null;
    lineCount: number;
    createdBy: string | null;
    createdDate: string | null;
    status: string;
    statusLabel?: string | null;
    diamondMountingId: number | null;
    diamondMountingDocNo?: string | null;
    shippingInvoiceDocNo: string | null;
    shippingInvoicePrintUrl: string | null;
    allocatedStones: SpkDiamondDetailStoneRow[];
};

type DocumentsPaginator = {
    data: SpkDiamondListRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = {
    value: string;
    label: string;
};

type SpkDiamondIndexProps = {
    documents: DocumentsPaginator;
    tableAvailable: boolean;
    filters: {
        search: string;
        sort: string;
        direction: string;
        date_from: string | null;
        date_to: string | null;
        per_page: number;
    };
    filterOptions: {
        per_page: FilterOption[];
        sort: FilterOption[];
        direction: FilterOption[];
    };
    receiptEmployeeOptions: string[];
    shippingInvoiceReceiptCount?: number;
};

const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'tanggal';
const DEFAULT_SORT_DIRECTION = 'desc';

const PER_PAGE_FILTER_OPTIONS = [10, 25, 50, 100].map((size) => ({
    value: String(size),
    label: String(size),
}));

export default function SpkDiamondIndex({
    documents,
    tableAvailable,
    filters,
    filterOptions,
    receiptEmployeeOptions,
    shippingInvoiceReceiptCount,
}: SpkDiamondIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));
    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [filterDraft, setFilterDraft] = useState({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [receiptDialogOpen, setReceiptDialogOpen] = useState(false);
    const [receiptHistoryDialogOpen, setReceiptHistoryDialogOpen] =
        useState(false);

    const shippingInvoiceReceiptCountLabel =
        typeof shippingInvoiceReceiptCount === 'number'
            ? shippingInvoiceReceiptCount.toLocaleString('id-ID')
            : '0';

    const visit = (query: Record<string, string | number | null | undefined>) => {
        router.get(
            spkDiamondIndex.url({
                query: {
                    search: filters.search,
                    sort: filters.sort,
                    direction: filters.direction,
                    date_from: filters.date_from ?? undefined,
                    date_to: filters.date_to ?? undefined,
                    per_page: filters.per_page,
                    page: documents.current_page,
                    ...query,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

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
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        });
    }, [filters.date_from, filters.date_to]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setSelectedIds([]);
    }, [documents.data]);

    const pageIds = useMemo(
        () => documents.data.map((row) => row.id),
        [documents.data],
    );
    const allPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const somePageSelected = pageIds.some((id) => selectedIds.includes(id));

    const toggleSelectAll = (checked: boolean | 'indeterminate') => {
        if (checked === true) {
            setSelectedIds(pageIds);

            return;
        }

        setSelectedIds([]);
    };

    const toggleSelectRow = (
        id: number,
        checked: boolean | 'indeterminate',
    ) => {
        setSelectedIds((current) => {
            if (checked === true) {
                return current.includes(id) ? current : [...current, id];
            }

            return current.filter((rowId) => rowId !== id);
        });
    };

    const openReceiptPrintDialog = (): void => {
        if (selectedIds.length === 0) {
            window.alert('Pilih SPK Pasang Batu terlebih dahulu.');

            return;
        }

        setReceiptDialogOpen(true);
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
        () => Math.max(1, documents.last_page),
        [documents.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom =
        entriesDraft !== String(DEFAULT_PER_PAGE);

    const hasDateFilter =
        filters.date_from !== null || filters.date_to !== null;
    const hasActiveTableFilters = hasDateFilter;
    const hasFilterDraftChanges =
        filterDraft.date_from !== (filters.date_from ?? '') ||
        filterDraft.date_to !== (filters.date_to ?? '');
    const hasFilterDraftCustom =
        filterDraft.date_from !== '' || filterDraft.date_to !== '';

    const hasCustomSort =
        filters.sort !== DEFAULT_SORT ||
        filters.direction !== DEFAULT_SORT_DIRECTION;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== DEFAULT_SORT ||
        sortDraft.direction !== DEFAULT_SORT_DIRECTION;

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
            direction: DEFAULT_SORT_DIRECTION,
        });

        if (
            filters.sort === DEFAULT_SORT &&
            filters.direction === DEFAULT_SORT_DIRECTION
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
            date_from:
                filterDraft.date_from.trim() !== ''
                    ? filterDraft.date_from
                    : null,
            date_to:
                filterDraft.date_to.trim() !== ''
                    ? filterDraft.date_to
                    : null,
        });
    };

    const clearTableFilters = () => {
        setFilterDraft({ date_from: '', date_to: '' });
        setFilterMenuOpen(false);
        visit({
            page: 1,
            date_from: null,
            date_to: null,
        });
    };

    return (
        <>
            <Head title="SPK Pasang Batu" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    SPK Pasang Batu
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Daftar dokumen SPK Pasang Batu dari alokasi batu
                                    SPK
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
                                    aria-label="Cari SPK Pasang Batu"
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
                                            setFilterDraft({
                                                date_from:
                                                    filters.date_from ?? '',
                                                date_to: filters.date_to ?? '',
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
                                                <span>Tanggal dari</span>
                                                <input
                                                    type="date"
                                                    value={filterDraft.date_from}
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
                            </div>
                            <button
                                type="button"
                                className="spkReceiptPrintBtn"
                                aria-label={
                                    selectedIds.length > 0
                                        ? `Buat Faktur Pengiriman (${selectedIds.length} SPK Pasang Batu)`
                                        : 'Buat Faktur Pengiriman'
                                }
                                title={
                                    selectedIds.length > 0
                                        ? `Buat Faktur Pengiriman (${selectedIds.length} SPK Pasang Batu)`
                                        : 'Pilih baris SPK Pasang Batu terlebih dahulu'
                                }
                                onClick={openReceiptPrintDialog}
                            >
                                <Icon name={printIcon} mode="Decorative" />
                                <span>Buat Faktur Pengiriman</span>
                                {selectedIds.length > 0 ? (
                                    <span
                                        className="spkReceiptPrintCount"
                                        aria-hidden="true"
                                    >
                                        {selectedIds.length}
                                    </span>
                                ) : null}
                            </button>
                        </div>
                    </div>

                    {tableAvailable ? (
                        <div className="spkStoreStockRequestBar">
                            <Button
                                className="spkStoreStockRequestBtn"
                                design="Default"
                                icon={documentIcon}
                                accessibleName={
                                    typeof shippingInvoiceReceiptCount ===
                                        'number' &&
                                    shippingInvoiceReceiptCount > 0
                                        ? `Faktur Tanda Terima: ${shippingInvoiceReceiptCountLabel} faktur`
                                        : 'Faktur Tanda Terima'
                                }
                                tooltip="Daftar faktur tanda terima SPK Pasang Batu"
                                onClick={() =>
                                    setReceiptHistoryDialogOpen(true)
                                }
                            >
                                Faktur Tanda Terima
                                {typeof shippingInvoiceReceiptCount ===
                                    'number' &&
                                shippingInvoiceReceiptCount > 0 ? (
                                    <span
                                        className="spkStoreStockRequestCount"
                                        aria-hidden="true"
                                    >
                                        {shippingInvoiceReceiptCountLabel}
                                    </span>
                                ) : null}
                            </Button>
                        </div>
                    ) : null}

                    {!tableAvailable ? (
                        <MessageStrip design="Information" hideCloseButton>
                            Penyimpanan SPK Pasang Batu belum tersedia di database.
                        </MessageStrip>
                    ) : null}

                    <ActiveFilterSummary
                        sort={
                            hasCustomSort
                                ? {
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
                                : null
                        }
                        filters={[
                            filters.search.trim() !== '' && {
                                key: 'search',
                                label: 'Pencarian',
                                value: filters.search.trim(),
                                onRemove: () => {
                                    setSearchQuery('');
                                    visit({ search: '', page: 1 });
                                },
                            },
                            (filters.date_from !== null ||
                                filters.date_to !== null) && {
                                key: 'date',
                                label: 'Tanggal',
                                value: describeDateRange(
                                    filters.date_from,
                                    filters.date_to,
                                ),
                                onRemove: () => {
                                    clearTableFilters();
                                },
                            },
                        ]}
                        onClearAll={() => {
                            setSearchQuery('');
                            visit({
                                search: '',
                                sort: DEFAULT_SORT,
                                direction: DEFAULT_SORT_DIRECTION,
                                date_from: null,
                                date_to: null,
                                page: 1,
                            });
                        }}
                    />

                    {selectedIds.length > 0 ? (
                        <div
                            className="spkTableBulkBar--finishing"
                            role="region"
                            aria-label="Baris terpilih"
                        >
                            <span className="spkTableBulkBarCount--finishing">
                                {selectedIds.length} dipilih
                            </span>
                            <div className="spkTableBulkBarActions--finishing">
                                <button
                                    type="button"
                                    className="spkTableHeaderSortClear--finishing"
                                    onClick={() => setSelectedIds([])}
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    ) : null}

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable spkTable--spkDiamondIndex">
                            <thead>
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="spkTableColCheck--finishing"
                                    >
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
                                    <th
                                        rowSpan={2}
                                        className="spkTableColDoc--spk spkTableColDoc--spkDiamond"
                                    >
                                        ID
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="spkTableColCenter spkTableColCreatedMeta"
                                    >
                                        Tanggal
                                    </th>
                                    <th rowSpan={2}>Item</th>
                                    <th
                                        colSpan={
                                            SPK_DIAMOND_INDEX_STONE_DETAIL_HEADERS.length
                                        }
                                        className="spkTableColStoneDetailGroup spkTableColCenter"
                                    >
                                        Detail batu
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="spkTableColStatus spkTableColCenter"
                                    >
                                        Status
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="spkTableColCenter spkTableColSpkDiamondShippingInvoice"
                                    >
                                        No Faktur Pengiriman
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="spkTableColCenter"
                                    >
                                        No Pasang Batu
                                    </th>
                                </tr>
                                <tr className="spkTableSubHeaderRow--stoneDetail">
                                    {SPK_DIAMOND_INDEX_STONE_DETAIL_HEADERS.map(
                                        (column) => (
                                            <th
                                                key={column.key}
                                                className={[
                                                    'spkTableColStoneDetailSub',
                                                    column.className,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            >
                                                {column.label}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {documents.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={12}>
                                            Tidak ada data SPK Pasang Batu.
                                        </td>
                                    </tr>
                                ) : (
                                    documents.data.map((row) => (
                                        <SpkDiamondIndexDocumentRows
                                            key={row.id}
                                            row={row}
                                            selected={selectedIds.includes(
                                                row.id,
                                            )}
                                            onToggleSelect={toggleSelectRow}
                                        />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {documents.total.toLocaleString('id-ID')}{' '}
                            SPK Pasang Batu
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={documents.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: documents.current_page - 1,
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
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <SpkDiamondReceiptPrintDialog
                open={receiptDialogOpen}
                onOpenChange={setReceiptDialogOpen}
                selectedIds={selectedIds}
                employeeOptions={receiptEmployeeOptions}
                onSent={() => setSelectedIds([])}
            />
            <SpkDiamondReceiptHistoryDialog
                open={receiptHistoryDialogOpen}
                onOpenChange={setReceiptHistoryDialogOpen}
                onDocumentsReceived={() =>
                    router.reload({
                        only: ['documents', 'shippingInvoiceReceiptCount'],
                    })
                }
            />
        </>
    );
}

SpkDiamondIndex.layout = {
    activeMenu: 'SPK Pasang Batu',
    pageTitle: 'SPK Pasang Batu',
};
