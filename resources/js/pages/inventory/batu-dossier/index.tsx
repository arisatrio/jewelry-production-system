import { Head, Link, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import barChartIcon from '@ui5/webcomponents-icons/dist/bar-chart.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
    ActiveFilterSummary,
    describeSort,
} from '@/components/active-filter-summary';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    DiamondDossierCreateDialog,
} from '@/pages/inventory/batu-dossier/diamond-dossier-form-dialog';
import { DiamondDossierImportDialog } from '@/pages/inventory/batu-dossier/diamond-dossier-import-dialog';
import type { DossierFormValues, DossierShapeOption } from '@/pages/inventory/batu-dossier/dossier-form-fields';
import {
    index,
    bulkDestroy,
    show as diamondDossierShow,
    stockReport as diamondDossierStockReport,
} from '@/routes/inventory/diamond-dossiers';
import { index as diamondDossierTransactionsIndex } from '@/routes/inventory/diamond-dossier-transactions';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

type DiamondStatus = 'available' | 'reserved' | 'used';

type DossierDiamond = {
    id: number;
    code: string | null;
    diamondType: string | null;
    shapeId: number | null;
    shape: string | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    entryDate: string | null;
    outDate: string | null;
    status: DiamondStatus;
    reservedSpkNo: string | null;
    spkNo: string | null;
    mountingDocumentNo: string | null;
    mountingDocumentId: number | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
};

type DiamondsPaginator = {
    data: DossierDiamond[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type StatusSummaryMetrics = {
    pcs: number;
    crt: string;
};

type StatusSummaryWithSpk = StatusSummaryMetrics & {
    spkCount: number;
};

type PageProps = {
    diamonds: DiamondsPaginator;
    summary: {
        count: number;
        crt: string;
    };
    statusSummaries: {
        available: StatusSummaryMetrics;
        reserved: StatusSummaryWithSpk;
        used: StatusSummaryWithSpk;
    };
    filters: {
        search: string;
        status: DiamondStatus | null;
        per_page: number;
        diamond_type: string | null;
        shape_id: number | null;
        supplier: string | null;
        sort: string;
        direction: string;
    };
    filterOptions: {
        sort: DossierShapeOption[];
        direction: DossierShapeOption[];
    };
    shapeOptions: DossierShapeOption[];
    diamondTypeOptions: DossierShapeOption[];
    supplierOptions: DossierShapeOption[];
    defaults: DossierFormValues;
    lastUpdatedAt: string | null;
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;
const DEFAULT_PER_PAGE = 50;
const DEFAULT_SORT = 'entry_date';
const DEFAULT_SORT_DIRECTION = 'desc';

const PER_PAGE_FILTER_OPTIONS = PAGE_SIZE_OPTIONS.map((size) => ({
    value: String(size),
    label: String(size),
}));

const STATUS_OPTIONS: { value: '' | DiamondStatus; label: string }[] = [
    { value: '', label: 'Semua status' },
    { value: 'available', label: 'Tersedia' },
    { value: 'reserved', label: 'Reserved' },
    { value: 'used', label: 'Terpakai' },
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

function statusLabel(
    status: DiamondStatus,
    reservedSpkNo: string | null,
): string {
    if (status === 'used') {
        return 'Terpakai';
    }

    if (status === 'reserved') {
        return reservedSpkNo !== null && reservedSpkNo !== ''
            ? `Reserved (${reservedSpkNo})`
            : 'Reserved';
    }

    return 'Tersedia';
}

function statusBadgeClass(status: DiamondStatus): string {
    if (status === 'used') {
        return 'spkStoneStockBadge is-unavailable';
    }

    if (status === 'reserved') {
        return 'inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800';
    }

    return 'spkStoneStockBadge is-available';
}

function statusFilterLabel(value: DiamondStatus | null): string | null {
    if (value === null) {
        return null;
    }

    return (
        STATUS_OPTIONS.find((option) => option.value === value)?.label
        ?? value
    );
}

function shapeFilterLabel(
    shapeId: number | null,
    shapeOptions: DossierShapeOption[],
): string | null {
    if (shapeId === null) {
        return null;
    }

    return (
        shapeOptions.find((option) => option.value === String(shapeId))
            ?.label ?? String(shapeId)
    );
}

function optionFilterLabel(
    value: string | null,
    options: DossierShapeOption[],
): string | null {
    if (value === null || value === '') {
        return null;
    }

    return options.find((option) => option.value === value)?.label ?? value;
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
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
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

function dossierAuditCell(
    actor: string | null,
    date: string | null,
): ReactNode {
    if (actor === null && date === null) {
        return '—';
    }

    return (
        <div className="spkTableDescription items-center text-center">
            <span>{actor ?? '—'}</span>
            {date ? (
                <span className="spkTableDescriptionItem">
                    {formatDateDisplay(date)}
                </span>
            ) : null}
        </div>
    );
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

function formatSummaryPcs(value: number): string {
    return value.toLocaleString('id-ID');
}

function formatSummaryCrt(value: string): string {
    return formatCrt(value);
}

export default function DiamondDossierIndex({
    diamonds,
    statusSummaries,
    filters,
    filterOptions,
    shapeOptions,
    diamondTypeOptions,
    supplierOptions,
    defaults,
    lastUpdatedAt,
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
    const [statusDraft, setStatusDraft] = useState(filters.status ?? '');
    const [diamondTypeDraft, setDiamondTypeDraft] = useState(
        filters.diamond_type ?? '',
    );
    const [shapeDraft, setShapeDraft] = useState(
        filters.shape_id !== null ? String(filters.shape_id) : '',
    );
    const [supplierDraft, setSupplierDraft] = useState(filters.supplier ?? '');
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [importDialogOpen, setImportDialogOpen] = useState(false);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);

        if (params.get('create') === '1') {
            setCreateDialogOpen(true);
        }
    }, []);

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkDeleting, setBulkDeleting] = useState(false);

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
        setDiamondTypeDraft(filters.diamond_type ?? '');
    }, [filters.diamond_type]);

    useEffect(() => {
        setShapeDraft(
            filters.shape_id !== null ? String(filters.shape_id) : '',
        );
    }, [filters.shape_id]);

    useEffect(() => {
        setSupplierDraft(filters.supplier ?? '');
    }, [filters.supplier]);

    useEffect(() => {
        setSortDraft({
            sort: filters.sort,
            direction: filters.direction,
        });
    }, [filters.sort, filters.direction]);

    useEffect(() => {
        setSelectedIds([]);
    }, [diamonds.data]);

    const pageIds = useMemo(
        () => diamonds.data.map((diamond) => diamond.id),
        [diamonds.data],
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

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        status?: DiamondStatus | null;
        diamond_type?: string | null;
        shape_id?: number | null;
        supplier?: string | null;
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
                    status:
                        ('status' in params ? params.status : filters.status)
                        || undefined,
                    diamond_type:
                        ('diamond_type' in params
                            ? params.diamond_type
                            : filters.diamond_type) || undefined,
                    shape_id:
                        ('shape_id' in params
                            ? params.shape_id
                            : filters.shape_id) ?? undefined,
                    supplier:
                        ('supplier' in params
                            ? params.supplier
                            : filters.supplier) || undefined,
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
        () => Math.max(1, diamonds.last_page),
        [diamonds.last_page],
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

    const hasActiveStatusFilter = filters.status !== null;
    const hasActiveDiamondTypeFilter = filters.diamond_type !== null;
    const hasActiveShapeFilter = filters.shape_id !== null;
    const hasActiveSupplierFilter = filters.supplier !== null;
    const hasActiveTableFilters =
        hasActiveStatusFilter
        || hasActiveDiamondTypeFilter
        || hasActiveShapeFilter
        || hasActiveSupplierFilter;

    const hasFilterDraftChanges =
        statusDraft !== (filters.status ?? '')
        || diamondTypeDraft !== (filters.diamond_type ?? '')
        || shapeDraft
            !== (filters.shape_id !== null ? String(filters.shape_id) : '')
        || supplierDraft !== (filters.supplier ?? '');

    const hasFilterDraftCustom =
        statusDraft !== ''
        || diamondTypeDraft !== ''
        || shapeDraft !== ''
        || supplierDraft !== '';

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
            status: (statusDraft as DiamondStatus) || null,
            diamond_type: diamondTypeDraft || null,
            shape_id: shapeDraft ? Number(shapeDraft) : null,
            supplier: supplierDraft || null,
        });
    };

    const clearTableFilters = () => {
        setStatusDraft('');
        setDiamondTypeDraft('');
        setShapeDraft('');
        setSupplierDraft('');
        setFilterMenuOpen(false);
        visit({
            page: 1,
            status: null,
            diamond_type: null,
            shape_id: null,
            supplier: null,
        });
    };

    const clearAllFilters = () => {
        setSearchQuery('');
        setStatusDraft('');
        setDiamondTypeDraft('');
        setShapeDraft('');
        setSupplierDraft('');
        visit({
            search: '',
            page: 1,
            status: null,
            diamond_type: null,
            shape_id: null,
            supplier: null,
            sort: DEFAULT_SORT,
            direction: DEFAULT_SORT_DIRECTION,
        });
    };

    const runBulkDelete = () => {
        if (selectedIds.length === 0 || bulkDeleting) {
            return;
        }

        if (
            !window.confirm(
                `Hapus ${selectedIds.length} batu dossier terpilih?`,
            )
        ) {
            return;
        }

        setBulkDeleting(true);
        router.post(
            bulkDestroy.url(),
            { ids: selectedIds },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBulkDeleting(false);
                    setSelectedIds([]);
                },
            },
        );
    };

    return (
        <>
            <Head title="Stok Batu Dossier" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Stok Batu Dossier
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Kelola Stok batu dossier; Terakhir
                                    diperbaharui:{' '}
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
                                    aria-label="Cari stok batu dossier"
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
                                            setStatusDraft(
                                                filters.status ?? '',
                                            );
                                            setDiamondTypeDraft(
                                                filters.diamond_type ?? '',
                                            );
                                            setShapeDraft(
                                                filters.shape_id !== null
                                                    ? String(filters.shape_id)
                                                    : '',
                                            );
                                            setSupplierDraft(
                                                filters.supplier ?? '',
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
                                                <span>Tipe</span>
                                                <select
                                                    value={diamondTypeDraft}
                                                    aria-label="Filter tipe batu"
                                                    onChange={(event) =>
                                                        setDiamondTypeDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua tipe
                                                    </option>
                                                    {diamondTypeOptions.map(
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
                                                <span>Bentuk</span>
                                                <select
                                                    value={shapeDraft}
                                                    aria-label="Filter bentuk batu"
                                                    onChange={(event) =>
                                                        setShapeDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua bentuk
                                                    </option>
                                                    {shapeOptions.map(
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
                                                <span>Supplier</span>
                                                <select
                                                    value={supplierDraft}
                                                    aria-label="Filter supplier batu"
                                                    onChange={(event) =>
                                                        setSupplierDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua supplier
                                                    </option>
                                                    {supplierOptions.map(
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
                                                <span>Status</span>
                                                <select
                                                    value={statusDraft}
                                                    aria-label="Filter status batu"
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
                                aria-label="Log transaksi batu dossier"
                                onClick={() =>
                                    router.visit(
                                        diamondDossierTransactionsIndex.url(),
                                    )
                                }
                            >
                                <Icon name={listIcon} mode="Decorative" />
                                <span>Log Transaksi</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Laporan stok batu dossier"
                                onClick={() =>
                                    router.visit(
                                        diamondDossierStockReport.url(),
                                    )
                                }
                            >
                                <Icon
                                    name={barChartIcon}
                                    mode="Decorative"
                                />
                                <span>Laporan Stok</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Import batu dossier dari Excel"
                                onClick={() => setImportDialogOpen(true)}
                            >
                                <Icon
                                    name={excelAttachmentIcon}
                                    mode="Decorative"
                                />
                                <span>Import Excel</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn"
                                aria-label="Tambah batu dossier"
                                onClick={() => setCreateDialogOpen(true)}
                            >
                                <Icon name={addIcon} mode="Decorative" />
                                <span>Tambah</span>
                            </button>
                        </div>
                    </div>

                    <div
                        className="spkStatusCards spkStatusCards--3 spkStatusCards--dossierStock"
                        role="group"
                        aria-label="Ringkasan stok batu dossier"
                    >
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockAvailable',
                                filters.status === 'available'
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.status === 'available'}
                            onClick={() =>
                                visit({
                                    status:
                                        filters.status === 'available'
                                            ? null
                                            : 'available',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">
                                Total tersedia
                            </span>
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
                                    {formatSummaryCrt(
                                        statusSummaries.available.crt,
                                    )}{' '}
                                    crt
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockReserved',
                                filters.status === 'reserved'
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.status === 'reserved'}
                            onClick={() =>
                                visit({
                                    status:
                                        filters.status === 'reserved'
                                            ? null
                                            : 'reserved',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">
                                Total reserved
                            </span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {formatSummaryPcs(
                                        statusSummaries.reserved.pcs,
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">
                                    pcs
                                </span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryCrt(
                                        statusSummaries.reserved.crt,
                                    )}{' '}
                                    crt
                                </span>
                                <span className="spkStatusCardHintSep">
                                    ·
                                </span>
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryPcs(
                                        statusSummaries.reserved.spkCount,
                                    )}{' '}
                                    SPK
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            className={[
                                'spkStatusCard spkStatusCard--stockUsed',
                                filters.status === 'used'
                                    ? 'is-filterActive'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            aria-pressed={filters.status === 'used'}
                            onClick={() =>
                                visit({
                                    status:
                                        filters.status === 'used'
                                            ? null
                                            : 'used',
                                    page: 1,
                                })
                            }
                        >
                            <span className="spkStatusCardLabel">
                                Total terpakai
                            </span>
                            <strong className="spkStatusCardCount">
                                <span className="spkStatusCardCountValue">
                                    {formatSummaryPcs(
                                        statusSummaries.used.pcs,
                                    )}
                                </span>
                                <span className="spkStatusCardCountUnit">
                                    pcs
                                </span>
                            </strong>
                            <span className="spkStatusCardHint">
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryCrt(
                                        statusSummaries.used.crt,
                                    )}{' '}
                                    crt
                                </span>
                                <span className="spkStatusCardHintSep">
                                    ·
                                </span>
                                <span className="spkStatusCardHintMetric">
                                    {formatSummaryPcs(
                                        statusSummaries.used.spkCount,
                                    )}{' '}
                                    SPK
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
                                    statusFilterLabel(filters.status) ?? '—',
                                onRemove: () => {
                                    visit({ status: null, page: 1 });
                                },
                            },
                            hasActiveDiamondTypeFilter && {
                                key: 'diamond_type',
                                label: 'Tipe',
                                value:
                                    optionFilterLabel(
                                        filters.diamond_type,
                                        diamondTypeOptions,
                                    ) ?? '—',
                                onRemove: () => {
                                    visit({ diamond_type: null, page: 1 });
                                },
                            },
                            hasActiveShapeFilter && {
                                key: 'shape_id',
                                label: 'Bentuk',
                                value:
                                    shapeFilterLabel(
                                        filters.shape_id,
                                        shapeOptions,
                                    ) ?? '—',
                                onRemove: () => {
                                    visit({ shape_id: null, page: 1 });
                                },
                            },
                            hasActiveSupplierFilter && {
                                key: 'supplier',
                                label: 'Supplier',
                                value: filters.supplier ?? '—',
                                onRemove: () => {
                                    visit({ supplier: null, page: 1 });
                                },
                            },
                        ]}
                        onClearAll={clearAllFilters}
                    />

                    {selectedIds.length > 0 ? (
                        <div
                            className="spkTableBulkBar--finishing"
                            role="region"
                            aria-label="Aksi massal stok batu dossier"
                        >
                            <span className="spkTableBulkBarCount--finishing">
                                {selectedIds.length} dipilih
                            </span>
                            <div className="spkTableBulkBarActions--finishing">
                                <Button
                                    design="Negative"
                                    disabled={bulkDeleting}
                                    className="spkTableBulkDeleteBtn--finishing"
                                    onClick={runBulkDelete}
                                >
                                    {bulkDeleting ? 'Memproses...' : 'Hapus'}
                                </Button>
                                <button
                                    type="button"
                                    className="spkTableHeaderSortClear--finishing"
                                    disabled={bulkDeleting}
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
                                    <th className="spkTableColDoc--spk spkTableColDoc--dossierId">
                                        ID
                                    </th>
                                    <th>Tipe</th>
                                    <th>Bentuk</th>
                                    <th className="spkTableColCenter">Warna</th>
                                    <th className="spkTableColCenter">CRT</th>
                                    <th className="spkTableColCenter">
                                        No. Sertifikat
                                    </th>
                                    <th className="spkTableColCenter">
                                        Supplier
                                    </th>
                                    <th className="spkTableColCenter">Tanggal masuk</th>
                                    <th className="spkTableColCenter">Tanggal keluar</th>
                                    <th className="spkTableColCenter">
                                        Status
                                    </th>
                                    <th className="spkTableColCenter">
                                        No. Pasang Batu
                                    </th>
                                    <th className="spkTableColCenter">
                                        No. SPK
                                    </th>
                                    <th className="spkTableColCenter">
                                        Dibuat oleh
                                    </th>
                                    <th className="spkTableColCenter">
                                        Diubah oleh
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {diamonds.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={15}>
                                            Tidak ada batu dossier.
                                        </td>
                                    </tr>
                                ) : (
                                    diamonds.data.map((diamond) => (
                                        <tr
                                            key={diamond.id}
                                            className={
                                                selectedIds.includes(diamond.id)
                                                    ? 'is-selected'
                                                    : undefined
                                            }
                                        >
                                            <td className="spkTableColCheck--finishing">
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        diamond.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelectRow(
                                                            diamond.id,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Pilih ${diamond.code ?? diamond.id}`}
                                                />
                                            </td>
                                            <td className="spkTableColDoc spkTableColDoc--spk spkTableColDoc--dossierId font-medium">
                                                {diamond.code !== null &&
                                                diamond.code !== '' ? (
                                                    <Link
                                                        href={diamondDossierShow.url(
                                                            diamond.id,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {diamond.code}
                                                    </Link>
                                                ) : (
                                                    (diamond.code ?? '—')
                                                )}
                                            </td>
                                            <td>
                                                {diamond.diamondType ?? '—'}
                                            </td>
                                            <td>{diamond.shape ?? '—'}</td>
                                            <td className="spkTableColCenter">
                                                {diamond.color ?? '—'}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums">
                                                {formatCrt(diamond.crt)}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {diamond.certificate ?? '—'}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {diamond.supplier ?? '—'}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {formatDateDisplay(
                                                    diamond.entryDate,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {formatDateDisplay(
                                                    diamond.outDate,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                <span
                                                    className={statusBadgeClass(
                                                        diamond.status,
                                                    )}
                                                >
                                                    {statusLabel(
                                                        diamond.status,
                                                        diamond.reservedSpkNo,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="spkTableColCenter">
                                                {diamond.mountingDocumentId !==
                                                    null &&
                                                diamond.mountingDocumentNo !==
                                                    null ? (
                                                    <Link
                                                        href={pasangBatuShow.url(
                                                            diamond.mountingDocumentId,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {
                                                            diamond.mountingDocumentNo
                                                        }
                                                    </Link>
                                                ) : (
                                                    diamond.mountingDocumentNo ??
                                                    '—'
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {diamond.spkNo !== null &&
                                                diamond.spkNo !== '' ? (
                                                    <Link
                                                        href={spkShow.url(
                                                            diamond.spkNo,
                                                        )}
                                                        className="spkProduksiLink"
                                                    >
                                                        {diamond.spkNo}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {dossierAuditCell(
                                                    diamond.createdBy,
                                                    diamond.createdDate,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                {dossierAuditCell(
                                                    diamond.modifiedBy,
                                                    diamond.modifiedDate,
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
                            Total {diamonds.total} baris
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={diamonds.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        page: diamonds.current_page - 1,
                                    })
                                }
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn is-active"
                            >
                                {diamonds.current_page}
                            </button>
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={diamonds.current_page >= totalPages}
                                onClick={() =>
                                    visit({
                                        page: diamonds.current_page + 1,
                                    })
                                }
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <DiamondDossierCreateDialog
                shapeOptions={shapeOptions}
                diamondTypeOptions={diamondTypeOptions}
                defaults={defaults}
                open={createDialogOpen}
                onOpenChange={setCreateDialogOpen}
            />
            <DiamondDossierImportDialog
                open={importDialogOpen}
                onOpenChange={setImportDialogOpen}
            />
        </>
    );
}

DiamondDossierIndex.layout = {
    activeMenu: 'Stok Batu Dossier',
    pageTitle: 'Stok Batu Dossier',
};
