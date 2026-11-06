import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
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
import type { PengrajinEmployeeOption } from '@/components/master-data/pengrajin-fields';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    PengrajinFormDialog,
    type PengrajinFormTarget,
} from '@/pages/master-data/pengrajin/pengrajin-form-dialog';
import {
    destroy,
    index as pengrajinIndex,
} from '@/routes/master-data/pengrajin';

type CraftsmanStatus = 'active' | 'inactive' | 'unlinked' | 'all';

type PengrajinRow = PengrajinFormTarget & {
    employeeName: string | null;
    positionName: string | null;
};

type CraftsmenPaginator = {
    data: PengrajinRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type SortOption = {
    value: string;
    label: string;
};

type PengrajinIndexProps = {
    craftsmen: CraftsmenPaginator;
    formMode: 'create' | null;
    editing: PengrajinFormTarget | null;
    employees: PengrajinEmployeeOption[];
    filters: {
        search: string;
        status: CraftsmanStatus;
        per_page: number;
        sort: string;
        direction: string;
    };
    filterOptions: {
        sort: SortOption[];
        direction: SortOption[];
    };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'name';
const DEFAULT_SORT_DIRECTION = 'asc';
const DEFAULT_STATUS = 'active';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const STATUS_OPTIONS: { value: CraftsmanStatus; label: string }[] = [
    { value: 'all', label: 'Semua pengrajin' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
    { value: 'unlinked', label: 'Belum dihubungkan' },
];

function statusLabel(value: CraftsmanStatus): string {
    return (
        STATUS_OPTIONS.find((option) => option.value === value)?.label ?? value
    );
}

export default function PengrajinIndex({
    craftsmen,
    formMode,
    editing,
    employees,
    filters,
    filterOptions,
}: PengrajinIndexProps) {
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
    const [statusDraft, setStatusDraft] = useState(filters.status);
    const [dialogOpen, setDialogOpen] = useState(
        formMode === 'create' || editing !== null,
    );
    const [dialogCraftsman, setDialogCraftsman] =
        useState<PengrajinFormTarget | null>(editing);

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setStatusDraft(filters.status);
    }, [filters.status]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        if (formMode === 'create') {
            setDialogCraftsman(null);
            setDialogOpen(true);

            return;
        }

        if (editing !== null) {
            setDialogCraftsman(editing);
            setDialogOpen(true);
        }
    }, [formMode, editing]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        status?: CraftsmanStatus;
        sort?: string;
        direction?: string;
    }) => {
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;

        router.get(
            pengrajinIndex.url({
                query: {
                    search:
                        ('search' in params ? params.search : filters.search) ||
                        undefined,
                    status: (() => {
                        const nextStatus =
                            'status' in params ? params.status : filters.status;

                        return nextStatus && nextStatus !== DEFAULT_STATUS
                            ? nextStatus
                            : undefined;
                    })(),
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
        () => Math.max(1, craftsmen.last_page),
        [craftsmen.last_page],
    );

    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== String(DEFAULT_PER_PAGE);
    const hasActiveStatusFilter = filters.status !== DEFAULT_STATUS;
    const hasFilterDraftChanges = statusDraft !== filters.status;
    const hasFilterDraftCustom = statusDraft !== DEFAULT_STATUS;
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
            status: statusDraft,
        });
    };

    const clearTableFilters = () => {
        setStatusDraft(DEFAULT_STATUS);
        setFilterMenuOpen(false);
        visit({
            page: 1,
            status: DEFAULT_STATUS,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setStatusDraft(DEFAULT_STATUS);
        visit({
            search: '',
            page: 1,
            status: DEFAULT_STATUS,
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });
    };

    const openCreateDialog = () => {
        setDialogCraftsman(null);
        setDialogOpen(true);
    };

    const openEditDialog = (craftsman: PengrajinRow) => {
        setDialogCraftsman(craftsman);
        setDialogOpen(true);
    };

    const handleDialogOpenChange = (open: boolean) => {
        setDialogOpen(open);

        if (!open && (formMode === 'create' || editing !== null)) {
            visit({ page: craftsmen.current_page });
        }
    };

    const handleDelete = (craftsman: PengrajinRow) => {
        if (
            !window.confirm(
                `Hapus pengrajin "${craftsman.name ?? craftsman.id}"?`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(craftsman.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Pengrajin" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Pengrajin
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Kelola pengrajin dan hubungkan ke pegawai
                                    serta user
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
                                    aria-label="Cari pengrajin"
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
                                            setStatusDraft(filters.status);
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
                                                hasActiveStatusFilter
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
                                                    aria-label="Filter status pengrajin"
                                                    onChange={(event) =>
                                                        setStatusDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {STATUS_OPTIONS.map(
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
                                                        !hasActiveStatusFilter
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
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Tambah pengrajin"
                                onClick={openCreateDialog}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah Pengrajin</span>
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
                            hasActiveStatusFilter && {
                                key: 'status',
                                label: 'Status',
                                value: statusLabel(filters.status),
                                onRemove: () => {
                                    setStatusDraft(DEFAULT_STATUS);
                                    visit({ status: DEFAULT_STATUS, page: 1 });
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
                                    <th>Nama</th>
                                    <th>Pegawai</th>
                                    <th>Posisi</th>
                                    <th className="spkTableColCenter">
                                        Status
                                    </th>
                                    <th className="spkTableColCenter spkMicroStoneActionCol">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {craftsmen.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            Tidak ada data pengrajin.
                                        </td>
                                    </tr>
                                ) : (
                                    craftsmen.data.map((craftsman, index) => (
                                        <tr key={craftsman.id}>
                                            <td className="spkTableColCenter tabular-nums">
                                                {(craftsmen.current_page - 1) *
                                                    craftsmen.per_page +
                                                    index +
                                                    1}
                                            </td>
                                            <td className="font-medium">
                                                {craftsman.name ?? '—'}
                                            </td>
                                            <td>
                                                {craftsman.employeeName ?? '—'}
                                            </td>
                                            <td>
                                                {craftsman.positionName ?? '—'}
                                            </td>
                                            <td className="spkTableColCenter">
                                                <span
                                                    className={
                                                        craftsman.isActive ===
                                                        'YES'
                                                            ? 'spkStoneStockBadge is-available'
                                                            : 'spkStoneStockBadge is-unavailable'
                                                    }
                                                >
                                                    {craftsman.isActive ===
                                                    'YES'
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="spkTableColCenter spkMicroStoneActionCol">
                                                <div className="spkMicroStoneStockActions">
                                                    <button
                                                        type="button"
                                                        className="spkChainFulfillmentReceiveBtn spkMicroStoneStockBtn"
                                                        aria-label={`Edit ${craftsman.name ?? craftsman.id}`}
                                                        title="Edit"
                                                        onClick={() =>
                                                            openEditDialog(
                                                                craftsman,
                                                            )
                                                        }
                                                    >
                                                        <span className="spkChainFulfillmentActionBtnLabel">
                                                            Edit
                                                        </span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="spkMicroStoneStockDeductBtn spkMicroStoneStockBtn"
                                                        aria-label={`Hapus ${craftsman.name ?? craftsman.id}`}
                                                        title="Hapus"
                                                        onClick={() =>
                                                            handleDelete(
                                                                craftsman,
                                                            )
                                                        }
                                                    >
                                                        <span className="spkChainFulfillmentActionBtnLabel">
                                                            Hapus
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
                            Total {craftsmen.total} baris
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={craftsmen.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: craftsmen.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {craftsmen.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={craftsmen.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: craftsmen.current_page + 1,
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <PengrajinFormDialog
                open={dialogOpen}
                craftsman={dialogCraftsman}
                employees={employees}
                onOpenChange={handleDialogOpenChange}
            />
        </>
    );
}

PengrajinIndex.layout = {
    activeMenu: 'Pengrajin',
    pageTitle: 'Pengrajin',
};
