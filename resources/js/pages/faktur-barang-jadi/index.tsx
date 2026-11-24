import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import {
    ActiveFilterSummary,
    describeDateRange,
    describeFilterOptions,
    describeSort,
} from '@/components/active-filter-summary';
import {
    buildDefaultableFilterQuery,
    FilterMultiSelect,
    haveSameFilterValues,
} from '@/components/filter-multi-select';
import { FakturBarangJadiImportDialog } from '@/components/spk/faktur-barang-jadi-import-dialog';
import {
    FakturBarangJadiReceiptList,
    type FakturBarangJadiReceiptRow,
} from '@/components/spk/faktur-barang-jadi-receipt-list';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    create as fakturBarangJadiCreate,
    index as fakturBarangJadiIndex,
} from '@/routes/faktur-barang-jadi';

type FilterOption = {
    value: string;
    label: string;
};

type ReceiptsPaginator = {
    data: FakturBarangJadiReceiptRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FakturBarangJadiIndexProps = {
    receipts: ReceiptsPaginator;
    filters: {
        search: string;
        sort: string;
        direction: string;
        per_page: number;
        status: string[];
        dari: string[];
        untuk: string[];
        date_from: string | null;
        date_to: string | null;
    };
    defaultFilters: {
        status: string[];
        dari: string[];
        untuk: string[];
    };
    filterOptions: {
        status: FilterOption[];
        dari: FilterOption[];
        untuk: FilterOption[];
        sort: FilterOption[];
        direction: FilterOption[];
        per_page: FilterOption[];
    };
    fakturBarangJadiTableAvailable: boolean;
    receiptEmployeeOptions: string[];
};

const SEARCH_DEBOUNCE_MS = 300;
const DEFAULT_PER_PAGE = 20;

const FAKTUR_INDEX_INFO =
    'Klik nomor faktur atau ikon cetak untuk preview. Kirim ke tujuan setelah faktur dibuat; lokasi SPK berubah setelah diterima.';

function buildFilterDraft(filters: FakturBarangJadiIndexProps['filters']) {
    return {
        status: filters.status,
        dari: filters.dari,
        untuk: filters.untuk,
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    };
}

export default function FakturBarangJadiIndex({
    receipts,
    filters,
    defaultFilters,
    filterOptions,
    fakturBarangJadiTableAvailable,
    receiptEmployeeOptions,
}: FakturBarangJadiIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [importOpen, setImportOpen] = useState(false);
    const [sortMenuOpen, setSortMenuOpen] = useState(false);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [sortDraft, setSortDraft] = useState({
        sort: filters.sort,
        direction: filters.direction,
    });
    const [filterDraft, setFilterDraft] = useState(() =>
        buildFilterDraft(filters),
    );
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));

    const totalPages = useMemo(
        () => Math.max(1, receipts.last_page),
        [receipts.last_page],
    );

    const hasCustomSort = filters.sort !== 'id' || filters.direction !== 'desc';
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== 'id' || sortDraft.direction !== 'desc';

    const hasActiveFilters =
        filters.status.length > 0 ||
        filters.dari.length > 0 ||
        filters.untuk.length > 0 ||
        filters.date_from !== null ||
        filters.date_to !== null;
    const hasFilterDraftChanges =
        !haveSameFilterValues(filterDraft.status, filters.status) ||
        !haveSameFilterValues(filterDraft.dari, filters.dari) ||
        !haveSameFilterValues(filterDraft.untuk, filters.untuk) ||
        filterDraft.date_from !== (filters.date_from ?? '') ||
        filterDraft.date_to !== (filters.date_to ?? '');
    const hasFilterDraftCustom =
        filterDraft.status.length > 0 ||
        filterDraft.dari.length > 0 ||
        filterDraft.untuk.length > 0 ||
        filterDraft.date_from !== '' ||
        filterDraft.date_to !== '';

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== String(DEFAULT_PER_PAGE);

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
        setFilterDraft(buildFilterDraft(filters));
    }, [filters]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    const visitIndex = (params: {
        search?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
        status?: string[];
        dari?: string[];
        untuk?: string[];
        date_from?: string | null;
        date_to?: string | null;
        page?: number;
    }) => {
        const nextSearch = params.search ?? filters.search;
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;
        const nextPerPage = params.per_page ?? filters.per_page;
        const nextStatus = params.status ?? filters.status;
        const nextDari = params.dari ?? filters.dari;
        const nextUntuk = params.untuk ?? filters.untuk;
        const nextDateFrom =
            params.date_from !== undefined
                ? params.date_from
                : filters.date_from;
        const nextDateTo =
            params.date_to !== undefined ? params.date_to : filters.date_to;
        const nextPage = params.page ?? receipts.current_page;

        router.get(
            fakturBarangJadiIndex.url({
                query: {
                    search: nextSearch || undefined,
                    sort: nextSort !== 'id' ? nextSort : undefined,
                    direction:
                        nextDirection !== 'desc' ? nextDirection : undefined,
                    status: buildDefaultableFilterQuery(
                        nextStatus,
                        defaultFilters.status,
                    ),
                    dari: buildDefaultableFilterQuery(
                        nextDari,
                        defaultFilters.dari,
                    ),
                    untuk: buildDefaultableFilterQuery(
                        nextUntuk,
                        defaultFilters.untuk,
                    ),
                    date_from: nextDateFrom || undefined,
                    date_to: nextDateTo || undefined,
                    per_page:
                        nextPerPage !== DEFAULT_PER_PAGE
                            ? nextPerPage
                            : undefined,
                    page: nextPage > 1 ? nextPage : undefined,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            visitIndex({ search: searchQuery, page: 1 });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce search only
    }, [searchQuery, filters.search]);

    const clearAllFilters = () => {
        setSearchQuery('');
        visitIndex({
            search: '',
            status: [],
            dari: [],
            untuk: [],
            date_from: null,
            date_to: null,
            sort: 'id',
            direction: 'desc',
            page: 1,
        });
    };

    const applySort = () => {
        setSortMenuOpen(false);
        visitIndex({
            sort: sortDraft.sort,
            direction: sortDraft.direction,
            page: 1,
        });
    };

    const clearSort = () => {
        setSortDraft({ sort: 'id', direction: 'desc' });
        setSortMenuOpen(false);
        visitIndex({ sort: 'id', direction: 'desc', page: 1 });
    };

    const applyFilters = () => {
        setFilterMenuOpen(false);
        visitIndex({
            status: filterDraft.status,
            dari: filterDraft.dari,
            untuk: filterDraft.untuk,
            date_from: filterDraft.date_from || null,
            date_to: filterDraft.date_to || null,
            page: 1,
        });
    };

    const clearFilters = () => {
        setFilterDraft({
            status: [],
            dari: [],
            untuk: [],
            date_from: '',
            date_to: '',
        });
        setFilterMenuOpen(false);
        visitIndex({
            status: [],
            dari: [],
            untuk: [],
            date_from: null,
            date_to: null,
            page: 1,
        });
    };

    const applyEntries = () => {
        setEntriesMenuOpen(false);
        visitIndex({ per_page: Number(entriesDraft), page: 1 });
    };

    const clearEntries = () => {
        setEntriesDraft(String(DEFAULT_PER_PAGE));
        setEntriesMenuOpen(false);
        visitIndex({ per_page: DEFAULT_PER_PAGE, page: 1 });
    };

    const sortChip =
        hasCustomSort &&
        ({
            key: 'sort',
            label: 'Urutan',
            value: describeSort(
                filters.sort,
                filters.direction,
                filterOptions.sort,
                filterOptions.direction,
            ),
            onRemove: clearSort,
        } as const);

    return (
        <>
            <Head title="Faktur Barang Jadi" />

            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Faktur Barang Jadi
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Daftar faktur yang sudah dibuat
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
                                    aria-label="Cari faktur"
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
                                                <span>Urutkan menurut</span>
                                                <select
                                                    value={sortDraft.sort}
                                                    aria-label="Urutkan menurut"
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
                                            setFilterDraft(
                                                buildFilterDraft(filters),
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
                                                    value={filterDraft.date_from}
                                                    aria-label="Tanggal faktur dari"
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
                                                    aria-label="Tanggal faktur sampai"
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
                                            <FilterMultiSelect
                                                label="Status faktur"
                                                options={filterOptions.status}
                                                value={filterDraft.status}
                                                onChange={(status) =>
                                                    setFilterDraft(
                                                        (current) => ({
                                                            ...current,
                                                            status,
                                                        }),
                                                    )
                                                }
                                            />
                                            <FilterMultiSelect
                                                label="Dari"
                                                options={filterOptions.dari}
                                                value={filterDraft.dari}
                                                onChange={(dari) =>
                                                    setFilterDraft(
                                                        (current) => ({
                                                            ...current,
                                                            dari,
                                                        }),
                                                    )
                                                }
                                            />
                                            <FilterMultiSelect
                                                label="Untuk"
                                                options={filterOptions.untuk}
                                                value={filterDraft.untuk}
                                                onChange={(untuk) =>
                                                    setFilterDraft(
                                                        (current) => ({
                                                            ...current,
                                                            untuk,
                                                        }),
                                                    )
                                                }
                                            />
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
                                                <span>Baris per halaman</span>
                                                <select
                                                    value={entriesDraft}
                                                    aria-label="Baris per halaman"
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
                                                        !hasEntriesDraftCustom
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
                            {fakturBarangJadiTableAvailable ? (
                                <>
                                    <span
                                        className="spkTableHeaderDivider--finishing"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        className="spkCreateBtn"
                                        aria-label="Buat Faktur Barang Jadi"
                                        onClick={() =>
                                            router.visit(
                                                fakturBarangJadiCreate.url(),
                                            )
                                        }
                                    >
                                        <Icon
                                            name={addIcon}
                                            mode="Decorative"
                                        />
                                        <span>Buat Faktur Barang Jadi</span>
                                    </button>
                                    <button
                                        type="button"
                                        className="spkReceiptPrintBtn"
                                        aria-label="Import faktur barang jadi dari Excel"
                                        title="Import faktur dari Excel"
                                        onClick={() => setImportOpen(true)}
                                    >
                                        <Icon
                                            name={excelAttachmentIcon}
                                            mode="Decorative"
                                        />
                                        <span>Import Excel</span>
                                    </button>
                                </>
                            ) : null}
                        </div>
                    </div>

                    {!fakturBarangJadiTableAvailable ? (
                        <MessageStrip design="Information" hideCloseButton>
                            Penyimpanan faktur barang jadi belum tersedia di
                            database.
                        </MessageStrip>
                    ) : (
                        <div className="spkFakturBarangJadiInfoBar">
                            <MessageStrip
                                design="Information"
                                hideCloseButton
                            >
                                {FAKTUR_INDEX_INFO}
                            </MessageStrip>
                        </div>
                    )}

                    <ActiveFilterSummary
                        sort={sortChip || null}
                        filters={[
                            filters.search !== '' && {
                                key: 'search',
                                label: 'Pencarian',
                                value: filters.search,
                                onRemove: () => {
                                    setSearchQuery('');
                                    visitIndex({ search: '', page: 1 });
                                },
                            },
                            filters.status.length > 0 && {
                                key: 'status',
                                label: 'Status',
                                value: describeFilterOptions(
                                    filters.status,
                                    filterOptions.status,
                                ),
                                onRemove: () => {
                                    visitIndex({ status: [], page: 1 });
                                },
                            },
                            filters.dari.length > 0 && {
                                key: 'dari',
                                label: 'Dari',
                                value: describeFilterOptions(
                                    filters.dari,
                                    filterOptions.dari,
                                ),
                                onRemove: () => {
                                    visitIndex({ dari: [], page: 1 });
                                },
                            },
                            filters.untuk.length > 0 && {
                                key: 'untuk',
                                label: 'Untuk',
                                value: describeFilterOptions(
                                    filters.untuk,
                                    filterOptions.untuk,
                                ),
                                onRemove: () => {
                                    visitIndex({ untuk: [], page: 1 });
                                },
                            },
                            (filters.date_from || filters.date_to) && {
                                key: 'date',
                                label: 'Tanggal',
                                value: describeDateRange(
                                    filters.date_from,
                                    filters.date_to,
                                ),
                                onRemove: () => {
                                    visitIndex({
                                        date_from: null,
                                        date_to: null,
                                        page: 1,
                                    });
                                },
                            },
                        ]}
                        onClearAll={clearAllFilters}
                    />

                    {fakturBarangJadiTableAvailable ? (
                        <>
                            <div className="spkTableScroll spkTableScroll--finishing">
                                <FakturBarangJadiReceiptList
                                    employeeOptions={receiptEmployeeOptions}
                                    rows={receipts.data}
                                    meta={null}
                                    page={receipts.current_page}
                                    onPageChange={(page) =>
                                        visitIndex({ page })
                                    }
                                    onWorkflowChanged={() => {
                                        router.reload({ only: ['receipts'] });
                                    }}
                                    layout="page"
                                />
                            </div>

                            <div className="spkTableFooter">
                                <div className="spkTableTotal">
                                    Total{' '}
                                    {receipts.total.toLocaleString('id-ID')}{' '}
                                    Faktur Barang Jadi
                                </div>
                                <div className="spkPagination">
                                    <button
                                        type="button"
                                        className="spkPageBtn"
                                        disabled={receipts.current_page <= 1}
                                        onClick={() =>
                                            visitIndex({
                                                page:
                                                    receipts.current_page - 1,
                                            })
                                        }
                                    >
                                        Sebelumnya
                                    </button>
                                    <button
                                        type="button"
                                        className="spkPageBtn is-active"
                                    >
                                        {receipts.current_page}
                                    </button>
                                    <button
                                        type="button"
                                        className="spkPageBtn"
                                        disabled={
                                            receipts.current_page >= totalPages
                                        }
                                        onClick={() =>
                                            visitIndex({
                                                page:
                                                    receipts.current_page + 1,
                                            })
                                        }
                                    >
                                        Berikutnya
                                    </button>
                                </div>
                            </div>
                        </>
                    ) : null}
                </div>
            </div>

            <FakturBarangJadiImportDialog
                open={importOpen}
                onOpenChange={setImportOpen}
            />
        </>
    );
}

FakturBarangJadiIndex.layout = {
    activeMenu: 'Faktur Barang Jadi',
    pageTitle: 'Faktur Barang Jadi',
};