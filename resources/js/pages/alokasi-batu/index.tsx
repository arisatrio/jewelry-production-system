import { Head, router } from '@inertiajs/react';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState } from 'react';
import {
    ActiveFilterSummary,
    describeFilterOptions,
} from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SpkPaymentStatusBadge,
    SpkTableDescriptionCell,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import type { SpkIndexRow } from '@/components/spk/types';
import { index as alokasiIndex, show } from '@/routes/alokasi-batu';

type ProductionRow = Pick<
    SpkIndexRow,
    | 'rowId'
    | 'produksiNo'
    | 'tipeProduksi'
    | 'customer'
    | 'orderReference'
    | 'requestStockNo'
    | 'requestStockDrkNo'
    | 'paymentStatus'
    | 'orderType'
    | 'typeSkuLabel'
    | 'itemDescription'
    | 'skuAssigned'
    | 'spkImageUrl'
> & {
    stoneSkuLines: string[];
    hasAllocation: boolean;
    stoneLineCount: number;
    diamondLineCount: number;
};

function resolveSpkOrderInfo(row: ProductionRow): string {
    const reference = row.orderReference?.trim() ?? '';
    const requestStockNo = row.requestStockNo?.trim() ?? '';
    const requestStockDrkNo = row.requestStockDrkNo?.trim() ?? '';
    const customer = row.customer?.trim() ?? '';

    if (reference !== '') {
        return reference;
    }

    if (requestStockNo !== '') {
        return requestStockNo;
    }

    if (requestStockDrkNo !== '') {
        return requestStockDrkNo;
    }

    return customer !== '-' ? customer : '';
}

type ProductionsPaginator = {
    data: ProductionRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = { value: string; label: string };

type AlokasiBatuIndexProps = {
    productions: ProductionsPaginator;
    filters: {
        search: string;
        allocation: string;
        per_page: number;
    };
    defaultFilters: {
        allocation: string;
    };
    filterOptions: {
        allocation: FilterOption[];
        per_page: FilterOption[];
    };
};

function buildFilterDraft(filters: AlokasiBatuIndexProps['filters']) {
    return {
        allocation: filters.allocation,
    };
}

function allocationBadgeClass(hasAllocation: boolean): string {
    return hasAllocation
        ? 'spkTableBadge--approved'
        : 'spkTableBadge--default';
}

function allocationStatusLabel(hasAllocation: boolean): string {
    return hasAllocation ? 'Sudah dialokasi' : 'Belum dialokasi';
}

export default function AlokasiBatuIndex({
    productions,
    filters,
    defaultFilters,
    filterOptions,
}: AlokasiBatuIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [filterDraft, setFilterDraft] = useState(() =>
        buildFilterDraft(filters),
    );
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        setFilterDraft(buildFilterDraft(filters));
    }, [filters]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    const visitIndex = (params: {
        search?: string;
        allocation?: string;
        per_page?: number;
        page?: number;
    }) => {
        const nextSearch = params.search ?? searchQuery;
        const nextAllocation = params.allocation ?? filters.allocation;
        const nextPerPage = params.per_page ?? filters.per_page;

        router.get(
            alokasiIndex.url({
                query: {
                    search: nextSearch || undefined,
                    allocation:
                        nextAllocation !== defaultFilters.allocation
                            ? nextAllocation
                            : undefined,
                    per_page: nextPerPage !== 50 ? nextPerPage : undefined,
                    page: params.page ?? 1,
                },
            }),
            {},
            { preserveState: true, replace: true },
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
    }, [searchQuery, filters.search]);

    const totalPages = useMemo(
        () => Math.max(1, productions.last_page),
        [productions.last_page],
    );

    const hasActiveFilters =
        filters.allocation !== defaultFilters.allocation;
    const hasCustomEntries = filters.per_page !== 50;
    const hasFilterDraftChanges =
        filterDraft.allocation !== filters.allocation;
    const hasFilterDraftActive =
        filterDraft.allocation !== defaultFilters.allocation;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== '50';

    const applyFilters = () => {
        if (!hasFilterDraftChanges) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({ allocation: filterDraft.allocation, page: 1 });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({ allocation: defaultFilters.allocation });

        if (!hasActiveFilters) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({ allocation: defaultFilters.allocation, page: 1 });
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

    return (
        <>
            <Head title="Alokasi Batu" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing spkTableHeaderBar--spk">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Alokasi Batu
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Cadangan batu mikro dan dossier per SPK
                                    produksi
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
                                    aria-label="Cari SPK alokasi batu"
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
                                                <span>Status alokasi</span>
                                                <select
                                                    value={
                                                        filterDraft.allocation
                                                    }
                                                    aria-label="Status alokasi"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                allocation:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {filterOptions.allocation.map(
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
                        </div>
                    </div>

                    <ActiveFilterSummary
                        filters={[
                            filters.search !== '' && {
                                key: 'search',
                                label: 'Pencarian',
                                value: filters.search,
                                onRemove: () =>
                                    visitIndex({ search: '', page: 1 }),
                            },
                            filters.allocation !==
                                defaultFilters.allocation && {
                                key: 'allocation',
                                label: 'Status alokasi',
                                value: describeFilterOptions(
                                    [filters.allocation],
                                    filterOptions.allocation,
                                ),
                                onRemove: () =>
                                    visitIndex({
                                        allocation: defaultFilters.allocation,
                                        page: 1,
                                    }),
                            },
                        ]}
                        onClearAll={() =>
                            visitIndex({
                                search: '',
                                allocation: defaultFilters.allocation,
                                page: 1,
                            })
                        }
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColDoc--spk">
                                        No SPK
                                    </th>
                                    <th>Item</th>
                                    <th>Daftar Batu SPK</th>
                                    <th className="spkTableColCenter">
                                        Baris alokasi
                                    </th>
                                    <th className="spkTableColStatus spkTableColCenter">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {productions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            Tidak ada data SPK untuk alokasi
                                            batu.
                                        </td>
                                    </tr>
                                ) : (
                                    productions.data.map((row) => {
                                        const orderInfo = resolveSpkOrderInfo(row);

                                        return (
                                        <tr key={row.rowId}>
                                            <td className="spkTableColDoc spkTableColDoc--spk">
                                                <div className="spkTableDocMetaBlock">
                                                    <button
                                                        type="button"
                                                        className="spkProduksiLink"
                                                        onClick={() =>
                                                            router.visit(
                                                                show.url(
                                                                    row.produksiNo,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {row.produksiNo}
                                                    </button>
                                                    <div className="flex flex-nowrap items-center gap-1">
                                                        <span
                                                            className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
                                                        >
                                                            {row.tipeProduksi}
                                                        </span>
                                                        {row.orderType ? (
                                                            <span className="spkTableBadge spkTableBadge--orderType">
                                                                {row.orderType}
                                                            </span>
                                                        ) : null}
                                                        <SpkPaymentStatusBadge
                                                            status={
                                                                row.paymentStatus
                                                            }
                                                        />
                                                    </div>
                                                    {orderInfo !== '' ? (
                                                        <span className="spkTableDocMeta">
                                                            {orderInfo}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td>
                                                <div className="flex items-start gap-3">
                                                    <SpkItemThumbnail
                                                        imageUrl={
                                                            row.spkImageUrl
                                                        }
                                                        spkNo={row.produksiNo}
                                                    />
                                                    <SpkTableDescriptionCell
                                                        row={row}
                                                    />
                                                </div>
                                            </td>
                                            <td>
                                                {row.stoneSkuLines.length ===
                                                0 ? (
                                                    <span>—</span>
                                                ) : (
                                                    <ul className="spkTableDescription m-0 list-none p-0">
                                                        {row.stoneSkuLines.map(
                                                            (line, index) => (
                                                                <li
                                                                    key={`${row.rowId}-stone-${index}`}
                                                                    className="spkTableDescriptionItem"
                                                                >
                                                                    {line}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {row.stoneLineCount} mikro
                                                <br />
                                                {row.diamondLineCount} dossier
                                            </td>
                                            <td className="spkTableColStatus spkTableColCenter">
                                                <div className="spkTableStatus">
                                                    <span
                                                        className={`spkTableBadge ${allocationBadgeClass(row.hasAllocation)}`}
                                                    >
                                                        {allocationStatusLabel(
                                                            row.hasAllocation,
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="spkTableFooter">
                        <div className="spkTableTotal">
                            Total {productions.total.toLocaleString('id-ID')}{' '}
                            SPK
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={productions.current_page <= 1}
                                onClick={() =>
                                    visitIndex({
                                        page: productions.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {productions.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={
                                    productions.current_page >= totalPages
                                }
                                onClick={() =>
                                    visitIndex({
                                        page: productions.current_page + 1,
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

AlokasiBatuIndex.layout = {
    activeMenu: 'Alokasi Batu',
};
