import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
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
    buildDefaultableFilterQuery,
    FilterMultiSelect,
    haveSameFilterValues,
} from '@/components/filter-multi-select';
import { NotesCell } from '@/components/notes-cell';
import { SpkItemNoLink } from '@/components/spk/spk-item-no-link';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { create, index as spkDiamondIndex, show } from '@/routes/spk-diamond';

type DocumentRow = {
    id: number;
    noSpkDiamond: string;
    tanggal: string | null;
    status: string;
    statusLabel: string;
    spkNo: string | null;
    orderReference: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    spkImageUrl: string | null;
    weightFrame: string | null;
    weightStone: string | null;
    weightTotal: string | null;
    keterangan: string | null;
};

type DocumentsPaginator = {
    data: DocumentRow[];
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
    filters: {
        search: string;
        status: string[];
        per_page: number;
    };
    defaultFilters: {
        status: string[];
    };
    filterOptions: {
        status: FilterOption[];
        per_page: FilterOption[];
    };
};

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

const WEIGHT_ROWS = [
    { key: 'weightFrame' as const, label: 'Rangka' },
    { key: 'weightStone' as const, label: 'Batu' },
    { key: 'weightTotal' as const, label: 'Total' },
];

function buildFilterDraft(filters: SpkDiamondIndexProps['filters']) {
    return {
        status: filters.status,
    };
}

function formatDateDisplay(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '—';
    }

    const [year, month, day] = trimmed.split('-');

    if (!year || !month || !day) {
        return trimmed;
    }

    const monthLabel = MONTH_LABELS[Number(month) - 1];

    return monthLabel
        ? `${day.padStart(2, '0')}-${monthLabel}-${year}`
        : trimmed;
}

function formatWeightValue(value: string | null): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function statusBadgeClass(status: string): string {
    const normalized = status.trim().toUpperCase();

    if (normalized === 'DONE') {
        return 'spkTableBadge--done';
    }

    if (normalized === 'SUBMITTED' || normalized === 'MOUNTING') {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

export default function SpkDiamondIndex({
    documents,
    filters,
    defaultFilters,
    filterOptions,
}: SpkDiamondIndexProps) {
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
        status?: string[];
        per_page?: number;
        page?: number;
    }) => {
        const nextSearch = params.search ?? searchQuery;
        const nextStatus = params.status ?? filters.status;
        const nextPerPage = params.per_page ?? filters.per_page;

        router.get(
            spkDiamondIndex.url({
                query: {
                    search: nextSearch || undefined,
                    status: buildDefaultableFilterQuery(
                        nextStatus,
                        defaultFilters.status,
                    ),
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
        () => Math.max(1, documents.last_page),
        [documents.last_page],
    );

    const hasActiveFilters = filters.status.length > 0;
    const hasCustomEntries = filters.per_page !== 50;
    const hasFilterDraftChanges = !haveSameFilterValues(
        filterDraft.status,
        filters.status,
    );
    const hasFilterDraftActive = filterDraft.status.length > 0;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== '50';

    const applyFilters = () => {
        if (!hasFilterDraftChanges) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({ status: filterDraft.status, page: 1 });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({ status: [] });

        if (!hasActiveFilters) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({ status: [], page: 1 });
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
            <Head title="SPK Diamond" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    SPK Diamond
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Dokumen permintaan batu dossier ke produksi
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
                                    aria-label="Cari dokumen SPK Diamond"
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
                                            <FilterMultiSelect
                                                label="Status"
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
                            <button
                                type="button"
                                className="spkCreateBtn"
                                aria-label="Tambah SPK Diamond"
                                onClick={() => router.visit(create.url())}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah</span>
                            </button>
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
                            filters.status.length > 0 && {
                                key: 'status',
                                label: 'Status',
                                value: describeFilterOptions(
                                    filters.status,
                                    filterOptions.status,
                                ),
                                onRemove: () =>
                                    visitIndex({ status: [], page: 1 }),
                            },
                        ]}
                        onClearAll={() =>
                            visitIndex({
                                search: '',
                                status: [],
                                page: 1,
                            })
                        }
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tanggal</th>
                                    <th>Item</th>
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Berat
                                    </th>
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
                                        <td colSpan={6}>
                                            Tidak ada data dokumen SPK Diamond.
                                        </td>
                                    </tr>
                                ) : (
                                    documents.data.map((item) => (
                                        <tr key={item.id}>
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
                                                    {item.noSpkDiamond}
                                                </button>
                                            </td>
                                            <td>
                                                {formatDateDisplay(
                                                    item.tanggal,
                                                )}
                                            </td>
                                            <td>
                                                <div className="flex items-start gap-3">
                                                    <SpkItemThumbnail
                                                        imageUrl={
                                                            item.spkImageUrl
                                                        }
                                                        spkNo={item.spkNo}
                                                    />
                                                    <div className="flex min-w-0 flex-col items-start gap-1">
                                                        <SpkItemNoLink
                                                            spkNo={item.spkNo}
                                                            orderReference={
                                                                item.orderReference
                                                            }
                                                        />
                                                        <SpkItemSkuColumn
                                                            typeCode={
                                                                item.typeCode
                                                            }
                                                            productItemName={
                                                                item.productItemName
                                                            }
                                                            skuCode={
                                                                item.skuCode
                                                            }
                                                            itemDescription={
                                                                item.skuCode
                                                                    ? null
                                                                    : item.itemDescription
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    {WEIGHT_ROWS.map((row) => (
                                                        <div
                                                            key={row.key}
                                                            className="spkTableWeightRow spkTableWeightRow--material"
                                                        >
                                                            <dt>{row.label}</dt>
                                                            <dd>
                                                                {formatWeightValue(
                                                                    item[
                                                                        row.key
                                                                    ],
                                                                )}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            </td>
                                            <td className="spkTableColNotes spkTableColCenter">
                                                <NotesCell
                                                    notes={item.keterangan}
                                                    docNo={item.noSpkDiamond}
                                                    spkNo={item.spkNo}
                                                />
                                            </td>
                                            <td className="spkTableColStatus spkTableColCenter">
                                                <div className="spkTableStatus">
                                                    <span
                                                        className={`spkTableBadge ${statusBadgeClass(item.status)}`}
                                                    >
                                                        {item.statusLabel}
                                                    </span>
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
                            Total {documents.total} dokumen SPK Diamond
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={documents.current_page <= 1}
                                onClick={() =>
                                    visitIndex({
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
                                    visitIndex({
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
        </>
    );
}

SpkDiamondIndex.layout = {
    activeMenu: 'SPK Diamond',
    pageTitle: 'SPK Diamond',
};
