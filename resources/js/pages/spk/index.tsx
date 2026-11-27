import { Head, router } from '@inertiajs/react';
import addIcon from '@ui5/webcomponents-icons/dist/add.js';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import documentIcon from '@ui5/webcomponents-icons/dist/document.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import printIcon from '@ui5/webcomponents-icons/dist/print.js';
import retailStoreIcon from '@ui5/webcomponents-icons/dist/retail-store.js';
import salesOrderIcon from '@ui5/webcomponents-icons/dist/sales-order.js';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import sortIcon from '@ui5/webcomponents-icons/dist/sort.js';
import wrenchIcon from '@ui5/webcomponents-icons/dist/wrench.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    ActiveFilterSummary,
    describeDateRange,
    describeFilterOptions,
    describeSort,
} from '@/components/active-filter-summary';
import {
    FilterMultiSelect,
    haveSameFilterValues,
} from '@/components/filter-multi-select';
import { SpkFotoBrjCell } from '@/components/spk/spk-foto-brj-cell';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    SpkPaymentStatusBadge,
    SpkTableCreatedByCell,
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    SpkTableStoneStatusCell,
    SpkTableStatusCell,
    displayListDate,
    displaySpkDeliveryStatus,
    displaySpkLocation,
    isListRowIncomplete,
    isPendingManagerApproval,
    targetDaysLeftHint,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import { FakturBarangJadiReceiptPrintDialog } from '@/components/spk/faktur-barang-jadi-receipt-print-dialog';
import { FakturBarangJadiStatusBadge } from '@/components/spk/faktur-barang-jadi-status-badge';
import { SpkReceiptHistoryDialog } from '@/components/spk/spk-receipt-history-dialog';
import { SpkReceiptPrintDialog } from '@/components/spk/spk-receipt-print-dialog';
import {
    shouldShowSpkRowManagerApproveButton,
    shouldShowSpkRowSendButton,
    SpkRowManagerApproveButton,
    SpkRowSendButton,
} from '@/components/spk/spk-row-approval-buttons';
import {
    SpkAssignSkuDialog,
    type SpkAssignSkuDialogRow,
} from '@/components/spk/spk-assign-sku-dialog';
import { SpkRowPrintButton } from '@/components/spk/spk-row-print-button';
import { SpkStatusListDialog } from '@/components/spk/spk-status-list-dialog';
import { SpkStoreOrderRequestDialog } from '@/components/spk/spk-store-order-request-dialog';
import { SpkStoreStockRequestDialog } from '@/components/spk/spk-store-stock-request-dialog';
import type { SpkIndexRow } from '@/components/spk/types';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    create as fakturBarangJadiCreate,
    index as fakturBarangJadiIndex,
} from '@/routes/faktur-barang-jadi';
import { index as reparasiIndex } from '@/routes/reparasi';
import {
    bulkStatus,
    create as spkCreate,
    index as spkIndex,
    show as spkShow,
    statusList,
} from '@/routes/spk';
type ProductionsPaginator = {
    data: SpkIndexRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FilterOption = {
    value: string;
    label: string;
};

type StatusCountKey =
    | 'draft'
    | 'pendingManager'
    | 'confirmed'
    | 'inProgress'
    | 'done'
    | 'doneRangka'
    | 'doneBarangJadi'
    | 'belumDibuatFaktur';

type StatusAlertKey =
    | 'draft'
    | 'pendingManager'
    | 'inProgress'
    | 'done'
    | 'doneRangka'
    | 'doneBarangJadi'
    | 'belumDibuatFaktur';

type BulkAction = 'submit' | 'approve' | 'manager_approve' | 'delete';

type SpkIndexContext = 'spk' | 'reparasi' | 'fakturBarangJadi';

type SpkIndexProps = {
    indexContext?: SpkIndexContext;
    productions: ProductionsPaginator;
    types: string[];
    typeCounts: {
        all: number;
        byType: Record<string, number>;
    };
    statusCounts: Record<StatusCountKey, number>;
    statusLabels: Record<
        | 'draft'
        | 'pendingManager'
        | 'confirmed'
        | 'inProgress'
        | 'doneRangka'
        | 'doneBarangJadi',
        string
    >;
    statuses: string[];
    filters: {
        search: string;
        type: string[];
        status: string[];
        sort: string;
        direction: string;
        date_from: string | null;
        date_to: string | null;
        target_period: string;
        target_from: string | null;
        target_to: string | null;
        per_page: number;
        faktur_belum?: boolean;
    };
    filterOptions: {
        target_period: FilterOption[];
        per_page: FilterOption[];
        sort: FilterOption[];
        direction: FilterOption[];
    };
    receiptEmployeeOptions: string[];
    storeStockRequestCount?: number | null;
    storeOrderRequestCount?: number;
    bulkActions: {
        canSubmit: boolean;
        canApprove: boolean;
        canManagerApprove: boolean;
        canDelete: boolean;
        canAssignSku?: boolean;
    };
    fakturBarangJadiTableAvailable?: boolean | null;
    fakturBarangJadiFotoBrjAvailable?: boolean | null;
    fakturBarangJadiReceiptCount?: number | null;
};

const DEFAULT_PER_PAGE = 50;

const DONE_STATUS_FILTER = 'Done';

const TARGET_PERIOD_CUSTOM = 'custom';

const STATUS_ALERTS = [
    {
        key: 'draft',
        hint: 'SPK menunggu dikirim ke Produksi',
        design: 'Information',
    },
    {
        key: 'pendingManager',
        hint: 'SPK menunggu approval Manager Produksi',
        design: 'Critical',
    },
    {
        key: 'inProgress',
        hint: 'SPK sedang dalam proses produksi',
        design: 'Critical',
    },
    {
        key: 'done',
        hint: 'SPK sudah selesai produksi (Rangka / Barang Jadi)',
        design: 'Positive',
    },
] as const satisfies ReadonlyArray<{
    key: StatusAlertKey;
    hint: string;
    design: 'Information' | 'Critical' | 'Positive';
}>;

const FAKTUR_STATUS_ALERTS = [
    {
        key: 'belumDibuatFaktur',
        hint: 'SPK selesai yang belum masuk faktur barang jadi',
        design: 'Information',
    },
    {
        key: 'doneRangka',
        hint: 'SPK selesai di tahap Poles Rangka (tanpa Pasang Batu / Poles Chrome)',
        design: 'Positive',
    },
    {
        key: 'doneBarangJadi',
        hint: 'SPK selesai barang jadi',
        design: 'Positive',
    },
] as const satisfies ReadonlyArray<{
    key: StatusAlertKey;
    hint: string;
    design: 'Information' | 'Critical' | 'Positive';
}>;

const FAKTUR_BARANG_JADI_INDEX_INFO =
    'Hanya SPK produksi selesai — DONE (Rangka) atau DONE (Barang Jadi). Reparasi dan SPK yang belum selesai tidak ditampilkan.';

const BULK_ACTION_CONFIRM: Record<BulkAction, string> = {
    submit: 'Kirim {count} SPK terpilih ke Manager?',
    approve: 'Kirim {count} SPK terpilih ke Produksi?',
    manager_approve: 'Approve {count} SPK terpilih?',
    delete: 'Hapus {count} SPK terpilih?',
};

function buildFilterDraft(filters: SpkIndexProps['filters']) {
    return {
        type: filters.type,
        status: filters.status,
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        target_period: filters.target_period,
        target_from: filters.target_from ?? '',
        target_to: filters.target_to ?? '',
    };
}

export default function SpkIndex({
    indexContext = 'spk',
    productions,
    types,
    typeCounts,
    statusCounts,
    statusLabels,
    statuses,
    filters,
    filterOptions,
    receiptEmployeeOptions,
    storeStockRequestCount,
    storeOrderRequestCount,
    bulkActions,
    fakturBarangJadiTableAvailable,
    fakturBarangJadiFotoBrjAvailable,
    fakturBarangJadiReceiptCount,
}: SpkIndexProps) {
    const isReparasiIndex = indexContext === 'reparasi';
    const isFakturBarangJadiIndex = indexContext === 'fakturBarangJadi';
    const listIndexRoute = isReparasiIndex
        ? reparasiIndex
        : isFakturBarangJadiIndex
          ? fakturBarangJadiCreate
          : spkIndex;
    const statusAlerts = isFakturBarangJadiIndex
        ? FAKTUR_STATUS_ALERTS
        : STATUS_ALERTS;
    const hideSpkManagementToolbar =
        isReparasiIndex || isFakturBarangJadiIndex;
    const pageTitle = isReparasiIndex
        ? 'Reparasi'
        : isFakturBarangJadiIndex
          ? 'Buat Faktur Barang Jadi'
          : 'SPK';
    const pageSubtitle = isReparasiIndex
        ? 'Data surat perintah kerja reparasi'
        : isFakturBarangJadiIndex
          ? 'Pilih SPK selesai (DONE Rangka & DONE Barang Jadi), lalu simpan sebagai faktur'
          : 'Data surat perintah kerja produksi';
    const showSpkScheduleColumns = !isFakturBarangJadiIndex;
    const showStoneStatusColumn = !isFakturBarangJadiIndex;
    const showFakturIssuedColumn = isFakturBarangJadiIndex;
    const showLocationColumn = isFakturBarangJadiIndex;
    const showFotoBrjColumn = isFakturBarangJadiIndex;
    const showDeliveryStatusColumn =
        !isFakturBarangJadiIndex && !isReparasiIndex;
    const tableColSpan =
        5 +
        (showSpkScheduleColumns ? 3 : 0) +
        (showStoneStatusColumn ? 1 : 0) +
        (showDeliveryStatusColumn ? 1 : 0) +
        (showLocationColumn ? 1 : 0) +
        (showFakturIssuedColumn ? 1 : 0) +
        (showFotoBrjColumn ? 1 : 0);

    const isRowSelectableForFaktur = (row: SpkIndexRow): boolean =>
        !isFakturBarangJadiIndex || row.fakturBarangJadiIssued !== true;
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);
    const [rowActionProcessing, setRowActionProcessing] = useState(false);
    const [statusListDialog, setStatusListDialog] = useState<{
        key: StatusAlertKey;
        requestId: number;
    } | null>(null);
    const [statusListDialogOpen, setStatusListDialogOpen] = useState(false);
    const [receiptDialogOpen, setReceiptDialogOpen] = useState(false);
    const [receiptHistoryDialogOpen, setReceiptHistoryDialogOpen] =
        useState(false);
    const [fakturReceiptDialogOpen, setFakturReceiptDialogOpen] = useState(false);
    const [storeStockRequestDialogOpen, setStoreStockRequestDialogOpen] =
        useState(false);
    const storeStockRequestCountLabel =
        typeof storeStockRequestCount === 'number'
            ? storeStockRequestCount.toLocaleString('id-ID')
            : '–';
    const [storeOrderRequestDialogOpen, setStoreOrderRequestDialogOpen] =
        useState(false);
    const [assignSkuRow, setAssignSkuRow] =
        useState<SpkAssignSkuDialogRow | null>(null);
    const canAssignSku =
        bulkActions.canAssignSku === true && !isFakturBarangJadiIndex;
    const storeOrderRequestCountLabel =
        typeof storeOrderRequestCount === 'number'
            ? storeOrderRequestCount.toLocaleString('id-ID')
            : '…';
    const singleStatusFilter =
        filters.status.length === 1 ? filters.status[0] : null;
    const activeStatusKey =
        singleStatusFilter === DONE_STATUS_FILTER
            ? 'done'
            : Object.entries(statusLabels).find(
                  ([, label]) => label === singleStatusFilter,
              )?.[0];

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    const buildIndexQuery = (params: {
        search?: string;
        type?: string[];
        status?: string[];
        sort?: string;
        direction?: string;
        date_from?: string | null;
        date_to?: string | null;
        target_period?: string;
        target_from?: string | null;
        target_to?: string | null;
        per_page?: number;
        faktur_belum?: boolean;
        page?: number;
    }) => {
        const nextSearch = params.search ?? searchQuery;
        const nextType = params.type ?? filters.type;
        const nextStatus = params.status ?? filters.status;
        const nextSort = params.sort ?? filters.sort;
        const nextDirection = params.direction ?? filters.direction;
        const nextDateFrom =
            params.date_from !== undefined
                ? params.date_from
                : filters.date_from;
        const nextDateTo =
            params.date_to !== undefined ? params.date_to : filters.date_to;
        const nextTargetPeriod = params.target_period ?? filters.target_period;
        const nextTargetFrom =
            params.target_from !== undefined
                ? params.target_from
                : filters.target_from;
        const nextTargetTo =
            params.target_to !== undefined
                ? params.target_to
                : filters.target_to;
        const isCustomTarget = nextTargetPeriod === TARGET_PERIOD_CUSTOM;
        const nextPerPage = params.per_page ?? filters.per_page;
        const nextFakturBelum =
            params.faktur_belum !== undefined
                ? params.faktur_belum
                : (filters.faktur_belum ?? false);

        return {
            search: nextSearch || undefined,
            type: nextType.length > 0 ? nextType : undefined,
            status: nextStatus.length > 0 ? nextStatus : undefined,
            sort: nextSort !== 'id' ? nextSort : undefined,
            direction: nextDirection !== 'desc' ? nextDirection : undefined,
            date_from: nextDateFrom || undefined,
            date_to: nextDateTo || undefined,
            target_period: nextTargetPeriod || undefined,
            target_from: (isCustomTarget && nextTargetFrom) || undefined,
            target_to: (isCustomTarget && nextTargetTo) || undefined,
            per_page:
                nextPerPage !== DEFAULT_PER_PAGE ? nextPerPage : undefined,
            faktur_belum: nextFakturBelum ? true : undefined,
            page: params.page ?? 1,
        };
    };

    const visitIndex = (params: Parameters<typeof buildIndexQuery>[0]) => {
        router.get(
            listIndexRoute.url({ query: buildIndexQuery(params) }),
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

            router.get(
                listIndexRoute.url({
                    query: {
                        search: searchQuery || undefined,
                        type:
                            filters.type.length > 0 ? filters.type : undefined,
                        status:
                            filters.status.length > 0
                                ? filters.status
                                : undefined,
                        sort: filters.sort !== 'id' ? filters.sort : undefined,
                        direction:
                            filters.direction !== 'desc'
                                ? filters.direction
                                : undefined,
                        date_from: filters.date_from || undefined,
                        date_to: filters.date_to || undefined,
                        target_period: filters.target_period || undefined,
                        target_from: filters.target_from || undefined,
                        target_to: filters.target_to || undefined,
                        per_page:
                            filters.per_page !== DEFAULT_PER_PAGE
                                ? filters.per_page
                                : undefined,
                        page: 1,
                    },
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [
        searchQuery,
        filters.search,
        filters.type,
        filters.status,
        filters.sort,
        filters.direction,
        filters.date_from,
        filters.date_to,
        filters.target_period,
        filters.target_from,
        filters.target_to,
        filters.per_page,
        listIndexRoute,
    ]);

    const totalPages = useMemo(
        () => Math.max(1, productions.last_page),
        [productions.last_page],
    );

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
    }, [productions.data]);

    const pageIds = useMemo(
        () => productions.data.map((row) => row.rowId),
        [productions.data],
    );
    const selectablePageIds = useMemo(
        () =>
            productions.data
                .filter((row) => isRowSelectableForFaktur(row))
                .map((row) => row.rowId),
        [productions.data, isFakturBarangJadiIndex],
    );
    const bulkSelectIds = isFakturBarangJadiIndex
        ? selectablePageIds
        : pageIds;
    const allPageSelected =
        bulkSelectIds.length > 0 &&
        bulkSelectIds.every((id) => selectedIds.includes(id));
    const somePageSelected = bulkSelectIds.some((id) =>
        selectedIds.includes(id),
    );

    const toggleSelectAll = (checked: boolean | 'indeterminate') => {
        if (checked === true) {
            setSelectedIds(bulkSelectIds);

            return;
        }

        setSelectedIds([]);
    };

    const toggleSelectRow = (
        id: number,
        checked: boolean | 'indeterminate',
    ) => {
        const row = productions.data.find((item) => item.rowId === id);

        if (row !== undefined && !isRowSelectableForFaktur(row)) {
            return;
        }

        setSelectedIds((current) => {
            if (checked === true) {
                return current.includes(id) ? current : [...current, id];
            }

            return current.filter((rowId) => rowId !== id);
        });
    };

    const openReceiptPrintDialog = (): void => {
        if (selectedIds.length === 0) {
            window.alert('Pilih SPK terlebih dahulu.');
            return;
        }

        setReceiptDialogOpen(true);
    };

    const openFakturReceiptPrintDialog = (): void => {
        if (selectedIds.length === 0) {
            window.alert('Pilih SPK selesai terlebih dahulu.');
            return;
        }

        setFakturReceiptDialogOpen(true);
    };

    const runBulkStatus = (action: BulkAction) => {
        if (selectedIds.length === 0 || bulkSubmitting) {
            return;
        }

        const confirmMessage = BULK_ACTION_CONFIRM[action].replace(
            '{count}',
            String(selectedIds.length),
        );

        if (!window.confirm(confirmMessage)) {
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

    const statusAlertFilterValue = (key: StatusAlertKey): string => {
        if (key === 'done') {
            return DONE_STATUS_FILTER;
        }

        if (key === 'belumDibuatFaktur') {
            return 'Belum Dibuat Faktur';
        }

        return statusLabels[key];
    };

    const statusListAlert = statusAlerts.find(
        (alert) => alert.key === statusListDialog?.key,
    );

    const openStatusList = (key: StatusAlertKey) => {
        if (isFakturBarangJadiIndex && key === 'belumDibuatFaktur') {
            visitIndex({
                faktur_belum: !(filters.faktur_belum ?? false),
                page: 1,
            });

            return;
        }

        setStatusListDialog((current) => ({
            key,
            requestId: (current?.requestId ?? 0) + 1,
        }));
        setStatusListDialogOpen(true);
    };

    const openDetail = (row: SpkIndexRow) => {
        router.visit(
            spkShow.url(row.produksiNo, {
                query: {
                    status: activeStatusKey || undefined,
                },
            }),
        );
    };

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
        const isCustomTarget =
            filterDraft.target_period === TARGET_PERIOD_CUSTOM;
        const nextTargetFrom =
            isCustomTarget && filterDraft.target_from !== ''
                ? filterDraft.target_from
                : null;
        const nextTargetTo =
            isCustomTarget && filterDraft.target_to !== ''
                ? filterDraft.target_to
                : null;
        const nextTargetPeriod =
            isCustomTarget && nextTargetFrom === null && nextTargetTo === null
                ? ''
                : filterDraft.target_period;

        const unchanged =
            haveSameFilterValues(filterDraft.type, filters.type) &&
            haveSameFilterValues(filterDraft.status, filters.status) &&
            nextDateFrom === filters.date_from &&
            nextDateTo === filters.date_to &&
            nextTargetPeriod === filters.target_period &&
            nextTargetFrom === filters.target_from &&
            nextTargetTo === filters.target_to;

        if (unchanged) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            type: filterDraft.type,
            status: filterDraft.status,
            date_from: nextDateFrom,
            date_to: nextDateTo,
            target_period: nextTargetPeriod,
            target_from: nextTargetFrom,
            target_to: nextTargetTo,
            page: 1,
        });
        setFilterMenuOpen(false);
    };

    const clearFilters = () => {
        setFilterDraft({
            type: [],
            status: [],
            date_from: '',
            date_to: '',
            target_period: '',
            target_from: '',
            target_to: '',
        });

        if (
            filters.type.length === 0 &&
            filters.status.length === 0 &&
            filters.date_from === null &&
            filters.date_to === null &&
            filters.target_period === ''
        ) {
            setFilterMenuOpen(false);

            return;
        }

        visitIndex({
            type: [],
            status: [],
            date_from: null,
            date_to: null,
            target_period: '',
            target_from: null,
            target_to: null,
            page: 1,
        });
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
        setEntriesDraft(String(DEFAULT_PER_PAGE));

        if (filters.per_page === DEFAULT_PER_PAGE) {
            setEntriesMenuOpen(false);

            return;
        }

        visitIndex({ per_page: DEFAULT_PER_PAGE, page: 1 });
        setEntriesMenuOpen(false);
    };

    const hasActiveFilters =
        filters.type.length > 0 ||
        filters.status.length > 0 ||
        filters.date_from !== null ||
        filters.date_to !== null ||
        filters.target_period !== '';
    const hasCustomSort = filters.sort !== 'id' || filters.direction !== 'desc';
    const hasCustomEntries = filters.per_page !== DEFAULT_PER_PAGE;
    const hasSortDraftChanges =
        sortDraft.sort !== filters.sort ||
        sortDraft.direction !== filters.direction;
    const hasFilterDraftChanges =
        !haveSameFilterValues(filterDraft.type, filters.type) ||
        !haveSameFilterValues(filterDraft.status, filters.status) ||
        filterDraft.date_from !== (filters.date_from ?? '') ||
        filterDraft.date_to !== (filters.date_to ?? '') ||
        filterDraft.target_period !== filters.target_period ||
        filterDraft.target_from !== (filters.target_from ?? '') ||
        filterDraft.target_to !== (filters.target_to ?? '');
    const hasEntriesDraftChanges = Number(entriesDraft) !== filters.per_page;
    const hasSortDraftCustom =
        sortDraft.sort !== 'id' || sortDraft.direction !== 'desc';
    const hasFilterDraftActive =
        filterDraft.type.length > 0 ||
        filterDraft.status.length > 0 ||
        filterDraft.date_from !== '' ||
        filterDraft.date_to !== '' ||
        filterDraft.target_period !== '';
    const hasEntriesDraftCustom = entriesDraft !== String(DEFAULT_PER_PAGE);
    const typeOptions = types.map((type) => ({
        value: type,
        label: `${type} (${typeCounts.byType[type] ?? 0})`,
    }));
    const statusOptions = statuses.map((status) => ({
        value: status,
        label: status,
    }));

    return (
        <>
            <Head title={pageTitle} />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing spkTableHeaderBar--spk">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    {pageTitle}
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    {pageSubtitle}
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableStatusAlerts--finishing"
                                role="group"
                                aria-label="Ringkasan status SPK"
                            >
                                {statusAlerts.map((alert) => {
                                    const count = statusCounts[alert.key] ?? 0;
                                    const filterValue = statusAlertFilterValue(
                                        alert.key,
                                    );
                                    const isBelumFakturFilter =
                                        alert.key === 'belumDibuatFaktur' &&
                                        (filters.faktur_belum ?? false);

                                    return (
                                        <button
                                            key={alert.key}
                                            type="button"
                                            className={[
                                                'spkTableStatusAlertBtn--finishing',
                                                isBelumFakturFilter
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            onClick={() =>
                                                openStatusList(alert.key)
                                            }
                                            aria-haspopup={
                                                alert.key === 'belumDibuatFaktur'
                                                    ? undefined
                                                    : 'dialog'
                                            }
                                            aria-pressed={
                                                alert.key === 'belumDibuatFaktur'
                                                    ? isBelumFakturFilter
                                                    : undefined
                                            }
                                            aria-label={`${count.toLocaleString('id-ID')} ${alert.hint}. Klik untuk ${alert.key === 'belumDibuatFaktur' ? 'filter tabel' : 'melihat daftar'}.`}
                                            title={alert.hint}
                                        >
                                            <MessageStrip
                                                design={alert.design}
                                                hideCloseButton
                                                className="spkTableStatusAlertStrip--finishing"
                                            >
                                                <span className="spkTableStatusAlertLabel--finishing">
                                                    {filterValue}
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
                                            <label className="spkTableHeaderSortField--finishing">
                                                <span>Target selesai</span>
                                                <select
                                                    value={
                                                        filterDraft.target_period
                                                    }
                                                    aria-label="Target selesai"
                                                    onChange={(event) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                target_period:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <option value="">
                                                        Semua
                                                    </option>
                                                    {filterOptions.target_period.map(
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
                                            {filterDraft.target_period ===
                                            TARGET_PERIOD_CUSTOM ? (
                                                <>
                                                    <label className="spkTableHeaderSortField--finishing">
                                                        <span>
                                                            Target selesai dari
                                                        </span>
                                                        <input
                                                            type="date"
                                                            value={
                                                                filterDraft.target_from
                                                            }
                                                            aria-label="Target selesai dari"
                                                            onChange={(event) =>
                                                                setFilterDraft(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        target_from:
                                                                            event
                                                                                .target
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
                                                        <span>
                                                            Target selesai
                                                            sampai
                                                        </span>
                                                        <input
                                                            type="date"
                                                            value={
                                                                filterDraft.target_to
                                                            }
                                                            aria-label="Target selesai sampai"
                                                            onChange={(event) =>
                                                                setFilterDraft(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        target_to:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                            onClick={(event) =>
                                                                event.stopPropagation()
                                                            }
                                                        />
                                                    </label>
                                                </>
                                            ) : null}
                                            {!isReparasiIndex ? (
                                                <FilterMultiSelect
                                                    label="Tipe"
                                                    allLabel={`Semua (${typeCounts.all})`}
                                                    options={typeOptions}
                                                    value={filterDraft.type}
                                                    onChange={(type) =>
                                                        setFilterDraft(
                                                            (current) => ({
                                                                ...current,
                                                                type,
                                                            }),
                                                        )
                                                    }
                                                />
                                            ) : null}
                                            <FilterMultiSelect
                                                label="Status"
                                                options={statusOptions}
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
                            {!isFakturBarangJadiIndex ? (
                                <>
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
                                </>
                            ) : null}
                            {isFakturBarangJadiIndex &&
                            fakturBarangJadiTableAvailable !== false ? (
                                <>
                                    <span
                                        className="spkTableHeaderDivider--finishing"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        className="spkReceiptPrintBtn"
                                        aria-label={
                                            selectedIds.length > 0
                                                ? `Buat Faktur Barang Jadi (${selectedIds.length} SPK)`
                                                : 'Buat Faktur Barang Jadi'
                                        }
                                        title={
                                            selectedIds.length > 0
                                                ? `Buat Faktur Barang Jadi (${selectedIds.length} SPK)`
                                                : 'Pilih SPK selesai terlebih dahulu'
                                        }
                                        onClick={openFakturReceiptPrintDialog}
                                    >
                                        <Icon
                                            name={printIcon}
                                            mode="Decorative"
                                        />
                                        <span>Buat Faktur Barang Jadi</span>
                                        {selectedIds.length > 0 ? (
                                            <span
                                                className="spkReceiptPrintCount"
                                                aria-hidden="true"
                                            >
                                                {selectedIds.length}
                                            </span>
                                        ) : null}
                                    </button>
                                    <button
                                        type="button"
                                        className="spkReceiptPrintBtn"
                                        aria-label="Kembali ke daftar faktur"
                                        title="Daftar faktur barang jadi"
                                        onClick={() =>
                                            router.visit(
                                                fakturBarangJadiIndex.url(),
                                            )
                                        }
                                    >
                                        <Icon
                                            name={documentIcon}
                                            mode="Decorative"
                                        />
                                        <span>Daftar Faktur</span>
                                    </button>
                                </>
                            ) : null}
                            {!hideSpkManagementToolbar ? (
                                <>
                                    <Button
                                        design="Default"
                                        icon={documentIcon}
                                        accessibleName="Riwayat Tanda Terima"
                                        tooltip="Riwayat Tanda Terima"
                                        className="spkTableHeaderExportBtn--finishing"
                                        onClick={() =>
                                            setReceiptHistoryDialogOpen(true)
                                        }
                                    />
                                    <span
                                        className="spkTableHeaderDivider--finishing"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        className="spkReceiptPrintBtn"
                                        aria-label={
                                            selectedIds.length > 0
                                                ? `Print Tanda Terima (${selectedIds.length} SPK)`
                                                : 'Print Tanda Terima'
                                        }
                                        title={
                                            selectedIds.length > 0
                                                ? `Print Tanda Terima (${selectedIds.length} SPK)`
                                                : 'Print Tanda Terima'
                                        }
                                        onClick={openReceiptPrintDialog}
                                    >
                                        <Icon
                                            name={printIcon}
                                            mode="Decorative"
                                        />
                                        <span>Tanda Terima</span>
                                        {selectedIds.length > 0 ? (
                                            <span
                                                className="spkReceiptPrintCount"
                                                aria-hidden="true"
                                            >
                                                {selectedIds.length}
                                            </span>
                                        ) : null}
                                    </button>
                                    <button
                                        type="button"
                                        className="spkReceiptPrintBtn"
                                        aria-label="Buka daftar SPK Reparasi"
                                        title="Buka daftar SPK Reparasi"
                                        onClick={() =>
                                            router.visit(reparasiIndex.url())
                                        }
                                    >
                                        <Icon
                                            name={wrenchIcon}
                                            mode="Decorative"
                                        />
                                        <span>SPK Reparasi</span>
                                    </button>
                                    <button
                                        type="button"
                                        className="spkCreateBtn"
                                        aria-label="Tambah SPK"
                                        onClick={() =>
                                            router.visit(spkCreate.url())
                                        }
                                    >
                                        <Icon
                                            name={addIcon}
                                            mode="Decorative"
                                        />
                                        <span>Tambah</span>
                                    </button>
                                </>
                            ) : null}
                        </div>
                    </div>

                    {isFakturBarangJadiIndex &&
                    fakturBarangJadiTableAvailable === false ? (
                        <MessageStrip design="Information" hideCloseButton>
                            Penyimpanan faktur barang jadi belum tersedia di
                            database.
                        </MessageStrip>
                    ) : null}

                    {isFakturBarangJadiIndex ? (
                        <div className="spkFakturBarangJadiInfoBar">
                            <MessageStrip design="Information" hideCloseButton>
                                {FAKTUR_BARANG_JADI_INDEX_INFO}
                            </MessageStrip>
                        </div>
                    ) : null}

                    {!hideSpkManagementToolbar ? (
                        <div className="spkStoreStockRequestBar">
                            <Button
                                className="spkStoreStockRequestBtn"
                                design="Default"
                                icon={retailStoreIcon}
                                accessibleName={
                                    typeof storeStockRequestCount ===
                                        'number' &&
                                    storeStockRequestCount > 0
                                        ? `Permintaan Stok Toko: ${storeStockRequestCountLabel} permintaan approved`
                                        : 'Permintaan Stok Toko'
                                }
                                tooltip="Permintaan stok toko yang sudah di-approve"
                                onClick={() =>
                                    setStoreStockRequestDialogOpen(true)
                                }
                            >
                                Permintaan Stok Toko
                                {typeof storeStockRequestCount === 'number' &&
                                    storeStockRequestCount > 0 && (
                                        <span
                                            className="spkStoreStockRequestCount"
                                            aria-hidden="true"
                                        >
                                            {storeStockRequestCountLabel}
                                        </span>
                                    )}
                            </Button>
                            <Button
                                className="spkStoreStockRequestBtn"
                                design="Default"
                                icon={salesOrderIcon}
                                accessibleName={
                                    storeOrderRequestCount !== undefined &&
                                    storeOrderRequestCount > 0
                                        ? `Permintaan Pesanan Toko: ${storeOrderRequestCountLabel} pesanan belum dibuatkan SPK`
                                        : 'Permintaan Pesanan Toko'
                                }
                                tooltip="Pesanan toko yang belum dibuatkan SPK"
                                onClick={() =>
                                    setStoreOrderRequestDialogOpen(true)
                                }
                            >
                                Permintaan Pesanan Toko
                                {storeOrderRequestCount !== undefined &&
                                    storeOrderRequestCount > 0 && (
                                        <span
                                            className="spkStoreStockRequestCount"
                                            aria-hidden="true"
                                        >
                                            {storeOrderRequestCountLabel}
                                        </span>
                                    )}
                            </Button>
                        </div>
                    ) : null}

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
                            filters.target_period !== '' && {
                                key: 'target',
                                label: 'Target selesai',
                                value:
                                    filters.target_period ===
                                    TARGET_PERIOD_CUSTOM
                                        ? describeDateRange(
                                              filters.target_from,
                                              filters.target_to,
                                          )
                                        : describeFilterOptions(
                                              [filters.target_period],
                                              filterOptions.target_period,
                                          ),
                                onRemove: () =>
                                    visitIndex({
                                        target_period: '',
                                        target_from: null,
                                        target_to: null,
                                        page: 1,
                                    }),
                            },
                            !isReparasiIndex &&
                                filters.type.length > 0 && {
                                    key: 'type',
                                    label: 'Tipe',
                                    value: filters.type.join(', '),
                                    onRemove: () =>
                                        visitIndex({ type: [], page: 1 }),
                                },
                            filters.status.length > 0 && {
                                key: 'status',
                                label: 'Status',
                                value: filters.status.join(', '),
                                onRemove: () =>
                                    visitIndex({ status: [], page: 1 }),
                            },
                            isFakturBarangJadiIndex &&
                                filters.faktur_belum && {
                                    key: 'faktur_belum',
                                    label: 'Faktur',
                                    value: 'Belum dibuat',
                                    onRemove: () =>
                                        visitIndex({
                                            faktur_belum: false,
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
                                type: [],
                                status: [],
                                date_from: null,
                                date_to: null,
                                target_period: '',
                                target_from: null,
                                target_to: null,
                                faktur_belum: false,
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
                                {bulkActions.canApprove ? (
                                    <Button
                                        design="Positive"
                                        disabled={bulkSubmitting}
                                        onClick={() => runBulkStatus('approve')}
                                    >
                                        {bulkSubmitting
                                            ? 'Memproses...'
                                            : 'Kirim ke Produksi'}
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
                                            disabled={bulkSelectIds.length === 0}
                                        />
                                    </th>
                                    <th className="spkTableColDoc--spk">ID</th>
                                    <th>Item</th>
                                    {showSpkScheduleColumns ? (
                                        <>
                                            <th className="spkTableColCenter">
                                                Dibuat Oleh
                                            </th>
                                            <th className="spkTableColCenter">
                                                Tanggal Permintaan
                                            </th>
                                            <th className="spkTableColCenter">
                                                Target Selesai
                                            </th>
                                        </>
                                    ) : null}
                                    <th className="spkTableColCenter">
                                        Proses Terakhir
                                    </th>
                                    <th className="spkTableColStatus spkTableColCenter">
                                        Status
                                    </th>
                                    {showFotoBrjColumn ? (
                                        <th className="spkTableColCenter spkTableColFotoBrj">
                                            Foto Barang Jadi
                                        </th>
                                    ) : null}
                                    {showStoneStatusColumn ? (
                                        <th className="spkTableColCenter spkTableColMaterialStatus">
                                            Status Batu
                                        </th>
                                    ) : null}
                                    {showDeliveryStatusColumn ? (
                                        <th className="spkTableColCenter">
                                            Status Delivery
                                        </th>
                                    ) : null}
                                    {showLocationColumn ? (
                                        <th className="spkTableColCenter spkTableColLocation">
                                            Lokasi
                                        </th>
                                    ) : null}
                                    {showFakturIssuedColumn ? (
                                        <th className="spkTableColCenter">
                                            Status Faktur
                                        </th>
                                    ) : null}
                                </tr>
                            </thead>
                            <tbody>
                                {productions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={tableColSpan}>
                                            Tidak ada data SPK.
                                        </td>
                                    </tr>
                                ) : (
                                    productions.data.map((row) => {
                                        const reference =
                                            row.orderReference?.trim() ?? '';
                                        const requestStockNo =
                                            row.requestStockNo?.trim() ?? '';
                                        const requestStockDrkNo =
                                            row.requestStockDrkNo?.trim() ?? '';
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
                                        const targetHint =
                                            row.targetDaysLeft !== null &&
                                            isListRowIncomplete(row.status)
                                                ? targetDaysLeftHint(
                                                      row.targetDaysLeft,
                                                  )
                                                : null;
                                        const showRowSend =
                                            shouldShowSpkRowSendButton(
                                                row.status,
                                                bulkActions.canSubmit,
                                                bulkActions.canApprove,
                                            );
                                        const showRowManagerApprove =
                                            shouldShowSpkRowManagerApproveButton(
                                                row.status,
                                                bulkActions.canManagerApprove,
                                            );
                                        const rowActionsDisabled =
                                            rowActionProcessing ||
                                            bulkSubmitting;
                                        const rowSendAction =
                                            bulkActions.canApprove
                                                ? 'approve'
                                                : 'submit';
                                        const fakturStatus =
                                            row.fakturBarangJadiStatus ?? null;
                                        const fakturRowClass =
                                            showFakturIssuedColumn
                                                ? (fakturStatus === 'received'
                                                      ? 'is-faktur-barang-jadi-received'
                                                      : fakturStatus === 'sent'
                                                        ? 'is-faktur-barang-jadi-sent'
                                                        : fakturStatus ===
                                                            'created'
                                                          ? 'is-faktur-barang-jadi-created'
                                                          : 'is-faktur-barang-jadi-pending')
                                                : '';
                                        const rowSelectable =
                                            isRowSelectableForFaktur(row);

                                        return (
                                            <tr
                                                key={row.rowId}
                                                className={
                                                    [
                                                        selectedIds.includes(
                                                            row.rowId,
                                                        )
                                                            ? 'is-selected'
                                                            : '',
                                                        isPendingManagerApproval(
                                                            row.status,
                                                        )
                                                            ? 'is-incomplete'
                                                            : '',
                                                        fakturRowClass,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' ') || undefined
                                                }
                                            >
                                                <td className="spkTableColCheck--finishing">
                                                    <Checkbox
                                                        checked={selectedIds.includes(
                                                            row.rowId,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleSelectRow(
                                                                row.rowId,
                                                                checked,
                                                            )
                                                        }
                                                        aria-label={`Pilih ${row.produksiNo}`}
                                                        disabled={!rowSelectable}
                                                    />
                                                </td>
                                                <td className="spkTableColDoc spkTableColDoc--spk">
                                                    <div className="spkTableDocRow">
                                                        <div className="spkTableRowActions">
                                                            {showRowSend ? (
                                                                <SpkRowSendButton
                                                                    rowId={
                                                                        row.rowId
                                                                    }
                                                                    spkNo={
                                                                        row.produksiNo
                                                                    }
                                                                    action={
                                                                        rowSendAction
                                                                    }
                                                                    disabled={
                                                                        rowActionsDisabled
                                                                    }
                                                                    onProcessingChange={
                                                                        setRowActionProcessing
                                                                    }
                                                                />
                                                            ) : null}
                                                            {showRowManagerApprove ? (
                                                                <SpkRowManagerApproveButton
                                                                    rowId={
                                                                        row.rowId
                                                                    }
                                                                    spkNo={
                                                                        row.produksiNo
                                                                    }
                                                                    disabled={
                                                                        rowActionsDisabled
                                                                    }
                                                                    onProcessingChange={
                                                                        setRowActionProcessing
                                                                    }
                                                                />
                                                            ) : null}
                                                            <SpkRowPrintButton
                                                                rowId={
                                                                    row.rowId
                                                                }
                                                                spkNo={
                                                                    row.produksiNo
                                                                }
                                                            />
                                                        </div>
                                                        <div className="spkTableDocMetaBlock">
                                                            <button
                                                                type="button"
                                                                className="spkProduksiLink"
                                                                onClick={() =>
                                                                    openDetail(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                {
                                                                    row.produksiNo
                                                                }
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
                                                                <SpkPaymentStatusBadge
                                                                    status={
                                                                        row.paymentStatus
                                                                    }
                                                                />
                                                            </div>
                                                            {orderInfo !==
                                                            '' ? (
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
                                                            spkNo={
                                                                row.produksiNo
                                                            }
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
                                                                                  spkNo: row.produksiNo,
                                                                                  itemDescription:
                                                                                      row.itemDescription,
                                                                              },
                                                                          )
                                                                    : undefined
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                                {showSpkScheduleColumns ? (
                                                    <>
                                                        <td>
                                                            <SpkTableCreatedByCell
                                                                createdBy={
                                                                    row.createdBy
                                                                }
                                                                createdDate={
                                                                    row.createdDate
                                                                }
                                                            />
                                                        </td>
                                                        <td className="spkTableColCenter">
                                                            {displayListDate(
                                                                row.orderDate,
                                                            )}
                                                        </td>
                                                        <td className="spkTableColCenter">
                                                            <div className="flex flex-col">
                                                                <span>
                                                                    {displayListDate(
                                                                        row.estimatedDelivery,
                                                                    )}
                                                                </span>
                                                                {targetHint ? (
                                                                    <span
                                                                        className={`text-xs ${targetHint.className}`}
                                                                    >
                                                                        {
                                                                            targetHint.label
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </td>
                                                    </>
                                                ) : null}
                                                <td className="spkTableColCenter">
                                                    <SpkTableLastProcessCell
                                                        row={row}
                                                    />
                                                </td>
                                                <td className="spkTableColStatus spkTableColCenter">
                                                    <SpkTableStatusCell
                                                        row={row}
                                                    />
                                                </td>
                                                {showFotoBrjColumn ? (
                                                    <td className="spkTableColCenter spkTableColFotoBrj">
                                                        <SpkFotoBrjCell
                                                            rowId={row.rowId}
                                                            spkNo={
                                                                row.produksiNo
                                                            }
                                                            imageUrl={
                                                                row.fotoBrjImageUrl ??
                                                                null
                                                            }
                                                            uploadEnabled={
                                                                fakturBarangJadiFotoBrjAvailable !==
                                                                false
                                                            }
                                                        />
                                                    </td>
                                                ) : null}
                                                {showStoneStatusColumn ? (
                                                    <td className="spkTableColCenter spkTableColMaterialStatus">
                                                        <SpkTableStoneStatusCell
                                                            stoneIndexStatus={
                                                                row.stoneIndexStatus
                                                            }
                                                        />
                                                    </td>
                                                ) : null}
                                                {showDeliveryStatusColumn ? (
                                                    <td className="spkTableColCenter">
                                                        {displaySpkDeliveryStatus(
                                                            row,
                                                        )}
                                                    </td>
                                                ) : null}
                                                {showLocationColumn ? (
                                                    <td className="spkTableColCenter spkTableColLocation">
                                                        {displaySpkLocation(
                                                            row.location,
                                                        )}
                                                    </td>
                                                ) : null}
                                                {showFakturIssuedColumn ? (
                                                    <td className="spkTableColCenter">
                                                        <FakturBarangJadiStatusBadge
                                                            status={fakturStatus}
                                                        />
                                                    </td>
                                                ) : null}
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
                            {pageTitle}
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

            <SpkStatusListDialog
                open={statusListDialogOpen}
                onOpenChange={setStatusListDialogOpen}
                listUrl={
                    statusListAlert
                        ? statusList.url(statusListAlert.key, {
                              query: isReparasiIndex
                                  ? { scope: 'reparasi' }
                                  : isFakturBarangJadiIndex
                                    ? { scope: 'faktur-barang-jadi' }
                                    : undefined,
                          })
                        : null
                }
                requestId={statusListDialog?.requestId ?? 0}
                title={
                    statusListAlert
                        ? `SPK ${statusAlertFilterValue(statusListAlert.key)}`
                        : ''
                }
                hint={statusListAlert?.hint ?? ''}
                detailStatus={statusListAlert?.key}
            />

            <SpkReceiptPrintDialog
                open={receiptDialogOpen}
                onOpenChange={setReceiptDialogOpen}
                selectedIds={selectedIds}
                employeeOptions={receiptEmployeeOptions}
            />

            <SpkReceiptHistoryDialog
                open={receiptHistoryDialogOpen}
                onOpenChange={setReceiptHistoryDialogOpen}
            />

            <SpkStoreOrderRequestDialog
                open={storeOrderRequestDialogOpen}
                onOpenChange={setStoreOrderRequestDialogOpen}
            />
            <SpkStoreStockRequestDialog
                open={storeStockRequestDialogOpen}
                onOpenChange={setStoreStockRequestDialogOpen}
            />

            <SpkAssignSkuDialog
                row={assignSkuRow}
                open={assignSkuRow !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssignSkuRow(null);
                    }
                }}
                onAssigned={(message) => {
                    toast.success(message);
                }}
            />

            {isFakturBarangJadiIndex ? (
                <>
                    <FakturBarangJadiReceiptPrintDialog
                        open={fakturReceiptDialogOpen}
                        onOpenChange={setFakturReceiptDialogOpen}
                        selectedIds={selectedIds}
                        employeeOptions={receiptEmployeeOptions}
                        onSaved={() => {
                            setSelectedIds([]);
                            router.visit(fakturBarangJadiIndex.url());
                        }}
                    />
                </>
            ) : null}
        </>
    );
}

SpkIndex.layout = {
    activeMenu: 'SPK',
};
