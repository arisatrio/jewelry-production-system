import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo } from 'react';
import {
    SpkAssignSkuDialog,
    type SpkAssignSkuDialogRow,
} from '@/components/spk/spk-assign-sku-dialog';
import {
    ActiveFilterSummary,
    describeFilterOptions,
} from '@/components/active-filter-summary';
import {
    buildDefaultableFilterQuery,
    FilterMultiSelect,
    haveSameFilterValues,
} from '@/components/filter-multi-select';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    displayListDate,
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import { SpkRowPrintButton } from '@/components/spk/spk-row-print-button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as barangJadiIndex } from '@/routes/inventory/barang-jadi';
import { show as spkShow } from '@/routes/spk';

type FilterOption = {
    value: string;
    label: string;
};

type BarangJadiRow = {
    rowId: number;
    spkNo: string;
    tipeProduksi: string;
    customer: string;
    orderReference: string | null;
    requestStockNo: string | null;
    requestStockDrkNo: string | null;
    orderType: string | null;
    plu: string | null;
    skuFromPlu: {
        typeSkuLabel: string | null;
        itemDescription: string | null;
        skuAssigned: boolean;
    } | null;
    spkImageUrl: string | null;
    typeSkuLabel: string | null;
    itemDescription: string | null;
    skuAssigned: boolean;
    keterangan: string | null;
    goldWeight: string | null;
    lastWeight: string | null;
    prosesTerakhir: string;
    prosesTerakhirDate: string;
    prosesTerakhirDisplay: string | null;
    prosesTerakhirCompleted: boolean | null;
    status: string;
    deliveryDate: string | null;
};

function isDelivered(row: BarangJadiRow): boolean {
    return (row.deliveryDate?.trim() ?? '') !== '';
}

function formatWeightGram(value: string | null): string {
    if (value === null || value.trim() === '') {
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

type ItemsPaginator = {
    data: BarangJadiRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type PluSummary = {
    belumDisetPlu: number;
    sudahDisetPlu: number;
};

type SkuSummary = {
    skuTidakSama: number;
    skuSesuai: number;
};

type PluStatusFilter = 'belum' | 'sudah' | null;
type SkuStatusFilter = 'beda' | 'sesuai' | null;

const SKU_STATUS_ALERTS = [
    {
        key: 'skuTidakSama' as const,
        filter: 'beda' as const,
        label: 'SKU Tidak Sama',
        hint: 'SKU SPK berbeda dengan SKU inventory dari PLU',
        design: 'Critical' as const,
    },
    {
        key: 'skuSesuai' as const,
        filter: 'sesuai' as const,
        label: 'SKU Sesuai',
        hint: 'SKU SPK sama dengan SKU inventory dari PLU',
        design: 'Positive' as const,
    },
];

const PLU_STATUS_ALERTS = [
    {
        key: 'belumDisetPlu' as const,
        filter: 'belum' as const,
        label: 'Belum Diset PLU',
        hint: 'SPK selesai yang belum delivery dan belum memiliki PLU',
        design: 'Critical' as const,
    },
    {
        key: 'sudahDisetPlu' as const,
        filter: 'sudah' as const,
        label: 'Sudah Delivery/Set PLU',
        hint: 'SPK selesai yang sudah delivery atau sudah memiliki PLU',
        design: 'Positive' as const,
    },
];

type InventoryBarangJadiIndexProps = {
    items: ItemsPaginator;
    pluSummary: PluSummary;
    skuSummary: SkuSummary;
    skuCompareAvailable: boolean;
    canAssignSku: boolean;
    filters: {
        search: string;
        per_page: number;
        plu_status: PluStatusFilter;
        sku_status: SkuStatusFilter;
        status: string[];
    };
    filterOptions: {
        status: FilterOption[];
        per_page: FilterOption[];
    };
};

const DEFAULT_PER_PAGE = 50;
const SEARCH_DEBOUNCE_MS = 300;

export default function InventoryBarangJadiIndex({
    items,
    pluSummary,
    skuSummary,
    skuCompareAvailable,
    canAssignSku,
    filters,
    filterOptions,
}: InventoryBarangJadiIndexProps) {
    const [assignSkuRow, setAssignSkuRow] =
        useState<SpkAssignSkuDialogRow | null>(null);
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [syncedSearch, setSyncedSearch] = useState(filters.search);

    if (syncedSearch !== filters.search) {
        setSyncedSearch(filters.search);
        setSearchQuery(filters.search);
    }

    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [entriesMenuOpen, setEntriesMenuOpen] = useState(false);
    const [filterDraft, setFilterDraft] = useState(() => ({
        status: filters.status,
    }));
    const [entriesDraft, setEntriesDraft] = useState(String(filters.per_page));

    useEffect(() => {
        setFilterDraft({ status: filters.status });
    }, [filters.status]);

    useEffect(() => {
        setEntriesDraft(String(filters.per_page));
    }, [filters.per_page]);

    const totalPages = useMemo(
        () => Math.max(1, items.last_page),
        [items.last_page],
    );

    const hasActiveFilters = filters.status.length > 0;
    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasFilterDraftChanges = !haveSameFilterValues(
        filterDraft.status,
        filters.status,
    );
    const hasFilterDraftActive = filterDraft.status.length > 0;
    const hasEntriesDraftChanges =
        Number(entriesDraft) !== filters.per_page;
    const hasEntriesDraftCustom = entriesDraft !== String(DEFAULT_PER_PAGE);

    const visitIndex = (params: {
        search?: string;
        status?: string[];
        plu_status?: PluStatusFilter;
        sku_status?: SkuStatusFilter;
        per_page?: number;
        page?: number;
    }) => {
        const nextSearch = params.search ?? filters.search;
        const nextStatus = params.status ?? filters.status;
        const nextPluStatus =
            'plu_status' in params ? params.plu_status : filters.plu_status;
        const nextSkuStatus =
            'sku_status' in params ? params.sku_status : filters.sku_status;
        const nextPerPage = params.per_page ?? filters.per_page;
        const nextPage = params.page ?? 1;

        router.get(
            barangJadiIndex.url({
                query: {
                    search: nextSearch || undefined,
                    status: buildDefaultableFilterQuery(nextStatus, []),
                    plu_status: nextPluStatus ?? undefined,
                    sku_status: nextSkuStatus ?? undefined,
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
            plu_status: null,
            sku_status: null,
            page: 1,
        });
    };

    const togglePluStatusFilter = (pluStatus: 'belum' | 'sudah') => {
        visitIndex({
            plu_status:
                filters.plu_status === pluStatus ? null : pluStatus,
            page: 1,
        });
    };

    const toggleSkuStatusFilter = (skuStatus: 'beda' | 'sesuai') => {
        visitIndex({
            sku_status:
                filters.sku_status === skuStatus ? null : skuStatus,
            page: 1,
        });
    };

    const applyFilters = () => {
        setFilterMenuOpen(false);
        visitIndex({ status: filterDraft.status, page: 1 });
    };

    const clearFilters = () => {
        setFilterDraft({ status: [] });
        setFilterMenuOpen(false);
        visitIndex({ status: [], page: 1 });
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

    const openSpkDetail = (row: BarangJadiRow) => {
        router.visit(spkShow.url(row.rowId));
    };

    return (
        <>
            <Head title="Barang Jadi" />

            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing spkTableHeaderBar--spk">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Barang Jadi
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    SPK produksi dengan status DONE (Rangka) dan
                                    DONE (Barang Jadi).
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableStatusAlerts--finishing"
                                role="group"
                                aria-label="Ringkasan PLU"
                            >
                                {PLU_STATUS_ALERTS.map((alert) => {
                                    const count = pluSummary[alert.key] ?? 0;
                                    const isActive =
                                        filters.plu_status === alert.filter;

                                    return (
                                        <button
                                            key={alert.key}
                                            type="button"
                                            className={[
                                                'spkTableStatusAlertBtn--finishing',
                                                isActive ? 'is-active' : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            onClick={() =>
                                                togglePluStatusFilter(
                                                    alert.filter,
                                                )
                                            }
                                            aria-pressed={isActive}
                                            aria-label={`${count.toLocaleString('id-ID')} ${alert.hint}. Klik untuk filter tabel.`}
                                            title={alert.hint}
                                        >
                                            <MessageStrip
                                                design={alert.design}
                                                hideCloseButton
                                                className="spkTableStatusAlertStrip--finishing"
                                            >
                                                <span className="spkTableStatusAlertLabel--finishing">
                                                    {alert.label}
                                                </span>
                                                <strong className="spkTableStatusAlertCount--finishing">
                                                    {count.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </strong>
                                            </MessageStrip>
                                        </button>
                                    );
                                })}
                            </div>
                            {skuCompareAvailable ? (
                                <>
                                    <span
                                        className="spkTableHeaderDivider--finishing"
                                        aria-hidden="true"
                                    />
                                    <div
                                        className="spkTableStatusAlerts--finishing"
                                        role="group"
                                        aria-label="Ringkasan SKU"
                                    >
                                        {SKU_STATUS_ALERTS.map((alert) => {
                                            const count =
                                                skuSummary[alert.key] ?? 0;
                                            const isActive =
                                                filters.sku_status ===
                                                alert.filter;

                                            return (
                                                <button
                                                    key={alert.key}
                                                    type="button"
                                                    className={[
                                                        'spkTableStatusAlertBtn--finishing',
                                                        isActive
                                                            ? 'is-active'
                                                            : '',
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' ')}
                                                    onClick={() =>
                                                        toggleSkuStatusFilter(
                                                            alert.filter,
                                                        )
                                                    }
                                                    aria-pressed={isActive}
                                                    aria-label={`${count.toLocaleString('id-ID')} ${alert.hint}. Klik untuk filter tabel.`}
                                                    title={alert.hint}
                                                >
                                                    <MessageStrip
                                                        design={alert.design}
                                                        hideCloseButton
                                                        className="spkTableStatusAlertStrip--finishing"
                                                    >
                                                        <span className="spkTableStatusAlertLabel--finishing">
                                                            {alert.label}
                                                        </span>
                                                        <strong className="spkTableStatusAlertCount--finishing">
                                                            {count.toLocaleString(
                                                                'id-ID',
                                                            )}
                                                        </strong>
                                                    </MessageStrip>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </>
                            ) : null}
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
                                    aria-label="Cari SPK"
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
                                            setFilterDraft({
                                                status: filters.status,
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
                                                options={
                                                    filterOptions.status
                                                }
                                                value={filterDraft.status}
                                                onChange={(status) =>
                                                    setFilterDraft({
                                                        status,
                                                    })
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
                            filters.plu_status === 'belum' && {
                                key: 'plu_status',
                                label: 'PLU',
                                value: 'Belum diset',
                                onRemove: () =>
                                    visitIndex({ plu_status: null, page: 1 }),
                            },
                            filters.plu_status === 'sudah' && {
                                key: 'plu_status',
                                label: 'PLU',
                                value: 'Sudah delivery/set PLU',
                                onRemove: () =>
                                    visitIndex({ plu_status: null, page: 1 }),
                            },
                            filters.sku_status === 'beda' && {
                                key: 'sku_status',
                                label: 'SKU',
                                value: 'Tidak sama',
                                onRemove: () =>
                                    visitIndex({ sku_status: null, page: 1 }),
                            },
                            filters.sku_status === 'sesuai' && {
                                key: 'sku_status',
                                label: 'SKU',
                                value: 'Sesuai',
                                onRemove: () =>
                                    visitIndex({ sku_status: null, page: 1 }),
                            },
                        ]}
                        sort={false}
                        onClearAll={clearAllFilters}
                    />

                    <div className="spkTableScroll spkTableScroll--finishing">
                        <table className="spkTable">
                            <thead>
                                <tr>
                                    <th className="spkTableColDoc--spk">
                                        No. SPK
                                    </th>
                                    <th>Item</th>
                                    <th className="spkTableColCenter">PLU</th>
                                    <th>SKU (dari PLU)</th>
                                    <th className="spkTableColCenter">
                                        Berat Emas (g)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Berat Barang Jadi (g)
                                    </th>
                                    <th className="spkTableColCenter">
                                        Proses Terakhir
                                    </th>
                                    <th className="spkTableColCenter">
                                        Tanggal Delivery
                                    </th>
                                    <th className="spkTableColCenter">
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9}>
                                            Tidak ada data SPK.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((row) => {
                                        const reference =
                                            row.orderReference?.trim() ?? '';
                                        const requestStockNo =
                                            row.requestStockNo?.trim() ?? '';
                                        const requestStockDrkNo =
                                            row.requestStockDrkNo?.trim() ??
                                            '';
                                        const customer =
                                            row.customer?.trim() ?? '';
                                        const orderInfo =
                                            reference !== ''
                                                ? reference
                                                : requestStockNo !== ''
                                                  ? requestStockNo
                                                  : requestStockDrkNo !== ''
                                                    ? requestStockDrkNo
                                                    : customer !== '-'
                                                      ? customer
                                                      : '';

                                        const delivered = isDelivered(row);

                                        return (
                                        <tr
                                            key={row.rowId}
                                            className={
                                                delivered
                                                    ? undefined
                                                    : 'is-inventory-barang-jadi-belum-delivery'
                                            }
                                        >
                                            <td className="spkTableColDoc spkTableColDoc--spk">
                                                <div className="spkTableDocRow">
                                                    <div className="spkTableRowActions">
                                                        <SpkRowPrintButton
                                                            rowId={row.rowId}
                                                            spkNo={row.spkNo}
                                                        />
                                                    </div>
                                                    <div className="spkTableDocMetaBlock">
                                                        <button
                                                            type="button"
                                                            className="spkProduksiLink"
                                                            onClick={() =>
                                                                openSpkDetail(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            {row.spkNo}
                                                        </button>
                                                        <div className="flex flex-nowrap items-center gap-1">
                                                            <span
                                                                className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
                                                            >
                                                                {
                                                                    row.tipeProduksi
                                                                }
                                                            </span>
                                                            {row.orderType ? (
                                                                <span className="spkTableBadge spkTableBadge--orderType">
                                                                    {
                                                                        row.orderType
                                                                    }
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                        {orderInfo !== '' ? (
                                                            <span className="spkTableDocMeta">
                                                                {orderInfo}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="flex items-start gap-3">
                                                    <SpkItemThumbnail
                                                        imageUrl={
                                                            row.spkImageUrl
                                                        }
                                                        spkNo={row.spkNo}
                                                    />
                                                    <SpkTableDescriptionCell
                                                        row={row}
                                                        onMissingSkuClick={
                                                            canAssignSku &&
                                                            row.skuAssigned ===
                                                                false
                                                                ? () =>
                                                                      setAssignSkuRow(
                                                                          {
                                                                              rowId: row.rowId,
                                                                              spkNo: row.spkNo,
                                                                              itemDescription:
                                                                                  row.itemDescription,
                                                                          },
                                                                      )
                                                                : undefined
                                                        }
                                                    />
                                                </div>
                                            </td>
                                            <td className="spkTableColCenter whitespace-nowrap">
                                                {row.plu ?? '—'}
                                            </td>
                                            <td>
                                                {row.skuFromPlu ? (
                                                    <SpkTableDescriptionCell
                                                        row={row.skuFromPlu}
                                                    />
                                                ) : (
                                                    <span>—</span>
                                                )}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums whitespace-nowrap">
                                                {formatWeightGram(
                                                    row.goldWeight,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter tabular-nums whitespace-nowrap">
                                                {formatWeightGram(
                                                    row.lastWeight,
                                                )}
                                            </td>
                                            <td className="spkTableColCenter">
                                                <SpkTableLastProcessCell
                                                    row={row}
                                                />
                                            </td>
                                            <td className="spkTableColCenter whitespace-nowrap">
                                                {delivered ? (
                                                    displayListDate(
                                                        row.deliveryDate,
                                                    )
                                                ) : (
                                                    <span className="spkTableBadge spkTableBadge--deliveryPending">
                                                        Belum delivery
                                                    </span>
                                                )}
                                            </td>
                                            <td className="spkTableColCenter whitespace-nowrap">
                                                {row.keterangan ?? '—'}
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
                            Total {items.total.toLocaleString('id-ID')} SPK
                        </div>
                        <div className="spkPagination">
                            <button
                                type="button"
                                className="spkPageBtn"
                                disabled={items.current_page <= 1}
                                onClick={() =>
                                    visitIndex({
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
                                disabled={
                                    items.current_page >= totalPages
                                }
                                onClick={() =>
                                    visitIndex({
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

            <SpkAssignSkuDialog
                row={assignSkuRow}
                open={assignSkuRow !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssignSkuRow(null);
                    }
                }}
                reloadOnly={['items', 'pluSummary', 'skuSummary']}
                onAssigned={(message) => {
                    toast.success(message);
                }}
            />
        </>
    );
}