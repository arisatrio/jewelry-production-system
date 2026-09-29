import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
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
import { NotesCell, JewelCadFileIcon } from '@/components/notes-cell';
import { SpkItemNoLink } from '@/components/spk/spk-item-no-link';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import { SpkProcessStatusCards } from '@/components/spk/spk-process-status-cards';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    create,
    index as jewelCadIndex,
    show,
    bulkStatus,
} from '@/routes/jewelcad';
type JewelCadRow = {
    id: number;
    requestId: number;
    docNo: string | null;
    transDate: string | null;
    operator: string | null;
    status: string | null;
    statusLabel: string | null;
    notes: string | null;
    material: string | null;
    spkNo: string | null;
    orderReference: string | null;
    spkId: number | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    spkImageUrl: string | null;
    qty: number | null;
    qtyLabel: string | null;
    jwcad3d: string | null;
    goldWeight: string | null;
    estimationBrj: string | null;
};

type RequestsPaginator = {
    data: JewelCadRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = {
    value: string;
    label: string;
};

type JewelCadIndexProps = {
    requests: RequestsPaginator;
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
        operator: string[];
        per_page: number;
    };
    defaultFilters: {
        status: string[];
    };
    filterOptions: {
        status: FilterOption[];
        operator: FilterOption[];
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

function buildFilterDraft(filters: JewelCadIndexProps['filters']) {
    return {
        status: filters.status,
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        operator: filters.operator,
    };
}

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

function statusBadgeClass(
    status: string | null,
    statusLabel: string | null,
): string {
    const label = formatStatusLabel(statusLabel, status).toLowerCase();

    if (label.includes('done') || label.includes('completed')) {
        return 'spkTableBadge--done';
    }

    if (label.includes('draft')) {
        return 'spkTableBadge--default';
    }

    if (label.includes('approval') || label.includes('serahkan')) {
        return 'spkTableBadge--approved';
    }

    return 'spkTableBadge--default';
}

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

    const [year, month, day] = (trimmed.split(/\s+/u)[0] ?? '').split('-');

    if (!year || !month || !day) {
        return trimmed;
    }

    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!monthLabel) {
        return trimmed;
    }

    return `${day.padStart(2, '0')}-${monthLabel}-${year}`;
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

export default function JewelCadIndex({
    requests,
    spkStatusCounts,
    filters,
    defaultFilters,
    filterOptions,
    bulkActions,
}: JewelCadIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
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
        setFilterDraft(buildFilterDraft(filters));
    }, [filters]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    useEffect(() => {
        setSelectedIds([]);
    }, [requests.data]);

    const pageIds = useMemo(() => {
        const ids: number[] = [];

        for (const item of requests.data) {
            if (!ids.includes(item.requestId)) {
                ids.push(item.requestId);
            }
        }

        return ids;
    }, [requests.data]);
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
                `Hapus ${selectedIds.length} request JewelCAD terpilih?`,
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
        operator?: string[];
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
        const nextOperator = params.operator ?? filters.operator;
        const nextPerPage = params.per_page ?? filters.per_page;

        router.get(
            jewelCadIndex.url({
                query: {
                    search: nextSearch || undefined,
                    sort: nextSort !== 'id' ? nextSort : undefined,
                    direction:
                        nextDirection !== 'desc' ? nextDirection : undefined,
                    status: buildDefaultableFilterQuery(
                        nextStatus,
                        defaultFilters.status,
                    ),
                    date_from: nextDateFrom || undefined,
                    date_to: nextDateTo || undefined,
                    operator:
                        nextOperator.length > 0 ? nextOperator : undefined,
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
        () => Math.max(1, requests.last_page),
        [requests.last_page],
    );

    const hasCustomSort = filters.sort !== 'id' || filters.direction !== 'desc';
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasSortDraftCustom =
        sortDraft.sort !== 'id' || sortDraft.direction !== 'desc';

    const hasActiveFilters =
        filters.status.length > 0 ||
        filters.date_from !== null ||
        filters.date_to !== null ||
        filters.operator.length > 0;
    const hasFilterDraftChanges =
        !haveSameFilterValues(filterDraft.status, filters.status) ||
        (filterDraft.date_from || '') !== (filters.date_from ?? '') ||
        (filterDraft.date_to || '') !== (filters.date_to ?? '') ||
        !haveSameFilterValues(filterDraft.operator, filters.operator);
    const hasFilterDraftCustom =
        filterDraft.status.length > 0 ||
        filterDraft.date_from !== '' ||
        filterDraft.date_to !== '' ||
        filterDraft.operator.length > 0;

    const hasCustomEntries = filters.per_page !== 50;
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
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
        const nextDateFrom =
            filterDraft.date_from === '' ? null : filterDraft.date_from;
        const nextDateTo =
            filterDraft.date_to === '' ? null : filterDraft.date_to;

        const unchanged =
            haveSameFilterValues(filterDraft.status, filters.status) &&
            nextDateFrom === filters.date_from &&
            nextDateTo === filters.date_to &&
            haveSameFilterValues(filterDraft.operator, filters.operator);

        if (unchanged) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            status: filterDraft.status,
            date_from: nextDateFrom,
            date_to: nextDateTo,
            operator: filterDraft.operator,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({
            status: [],
            date_from: '',
            date_to: '',
            operator: [],
        });

        if (
            filters.status.length === 0 &&
            filters.date_from === null &&
            filters.date_to === null &&
            filters.operator.length === 0
        ) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            status: [],
            date_from: null,
            date_to: null,
            operator: [],
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
            <Head title="JewelCAD" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    JewelCAD
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Data pengerjaan proses JewelCAD
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <SpkProcessStatusCards
                                counts={spkStatusCounts}
                                variant="alerts"
                                processLabel="JewelCAD"
                                module="jewelcad"
                                documentUrl={show.url}
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
                                    placeholder="Cari"
                                    value={searchQuery}
                                    autoComplete="off"
                                    aria-label="Cari request JewelCAD"
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
                                            <FilterMultiSelect
                                                label="Operator"
                                                options={filterOptions.operator}
                                                value={filterDraft.operator}
                                                onChange={(operator) =>
                                                    setFilterDraft(
                                                        (current) => ({
                                                            ...current,
                                                            operator,
                                                        }),
                                                    )
                                                }
                                            />
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
                                aria-label="Tambah request JewelCAD"
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
                            (filters.date_from !== null ||
                                filters.date_to !== null) && {
                                key: 'date',
                                label: 'Tanggal',
                                value: describeDateRange(
                                    filters.date_from,
                                    filters.date_to,
                                ),
                                onRemove: () =>
                                    visitIndex({
                                        date_from: null,
                                        date_to: null,
                                        page: 1,
                                    }),
                            },
                            filters.operator.length > 0 && {
                                key: 'operator',
                                label: 'Operator',
                                value: describeFilterOptions(
                                    filters.operator,
                                    filterOptions.operator,
                                ),
                                onRemove: () =>
                                    visitIndex({ operator: [], page: 1 }),
                            },
                            filters.status.length > 0 && {
                                key: 'status',
                                label: 'Status',
                                value: describeFilterOptions(
                                    filters.status,
                                    filterOptions.status,
                                ),
                                onRemove: () =>
                                    visitIndex({
                                        status: [],
                                        page: 1,
                                    }),
                            },
                        ]}
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
                        onClearAll={() =>
                            visitIndex({
                                search: '',
                                status: [],
                                date_from: null,
                                date_to: null,
                                operator: [],
                                sort: 'id',
                                direction: 'desc',
                                page: 1,
                            })
                        }
                    />

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
                                        onClick={() => runBulkStatus('delete')}
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
                                    <th>Item</th>
                                    <th>Operator</th>
                                    <th>Bahan Emas</th>
                                    <th className="spkTableColWeight spkTableColCenter">
                                        Berat (g)
                                    </th>
                                    <th className="spkTableColNotes spkTableColCenter">
                                        File
                                    </th>
                                    <th className="spkTableColNotes spkTableColCenter">
                                        Catatan
                                    </th>
                                    <th className="spkTableColCenter">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={10}>
                                            Tidak ada data request JewelCAD.
                                        </td>
                                    </tr>
                                ) : (
                                    requests.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className={
                                                [
                                                    selectedIds.includes(
                                                        item.requestId,
                                                    )
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
                                                    .join(' ') || undefined
                                            }
                                        >
                                            <td className="spkTableColCheck--finishing">
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        item.requestId,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelectRow(
                                                            item.requestId,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Pilih ${item.docNo ?? item.requestId}`}
                                                />
                                            </td>
                                            <td className="spkTableColDoc">
                                                <button
                                                    type="button"
                                                    className="spkProduksiLink"
                                                    onClick={() =>
                                                        router.visit(
                                                            show.url(
                                                                item.requestId,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {item.docNo ?? '—'}
                                                </button>
                                            </td>
                                            <td>
                                                {formatDateDisplay(
                                                    item.transDate,
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
                                            <td>{item.operator ?? '—'}</td>
                                            <td>{item.material ?? '—'}</td>
                                            <td className="spkTableColWeight">
                                                <dl className="spkTableWeightStack">
                                                    <div className="spkTableWeightRow">
                                                        <dt>Awal SPK</dt>
                                                        <dd>
                                                            {item.goldWeight ??
                                                                '—'}
                                                        </dd>
                                                    </div>
                                                    <div className="spkTableWeightRow">
                                                        <dt>Est. Cor</dt>
                                                        <dd>
                                                            {item.estimationBrj ??
                                                                '—'}
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </td>
                                            <td className="spkTableColNotes spkTableColCenter">
                                                <NotesCell
                                                    notes={item.jwcad3d}
                                                    title="File JewelCAD"
                                                    docNo={item.docNo}
                                                    spkNo={item.spkNo}
                                                    icon={JewelCadFileIcon}
                                                />
                                            </td>
                                            <td className="spkTableColNotes spkTableColCenter">
                                                <NotesCell
                                                    notes={item.notes}
                                                    title="Catatan"
                                                    docNo={item.docNo}
                                                    spkNo={item.spkNo}
                                                />
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
                            Total {requests.total} request JewelCAD
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={requests.current_page <= 1}
                                onClick={() =>
                                    visitIndex({
                                        page: requests.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {requests.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={requests.current_page >= totalPages}
                                onClick={() =>
                                    visitIndex({
                                        page: requests.current_page + 1,
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

JewelCadIndex.layout = {
    activeMenu: 'JewelCAD',
    pageTitle: 'JewelCAD',
};
