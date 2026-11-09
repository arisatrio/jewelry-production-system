import { Head, router } from '@inertiajs/react';
import excelAttachmentIcon from '@ui5/webcomponents-icons/dist/excel-attachment.js';
import filterIcon from '@ui5/webcomponents-icons/dist/filter.js';
import listIcon from '@ui5/webcomponents-icons/dist/list.js';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import { ActiveFilterSummary } from '@/components/active-filter-summary';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    DiamondDossierStockListDialog,
    type StockListDialogRequest,
} from '@/pages/inventory/batu-dossier/diamond-dossier-stock-list-dialog';
import {
    index as diamondDossiersIndex,
    stockReport as diamondDossierStockReport,
    stockReportExport as diamondDossierStockReportExport,
} from '@/routes/inventory/diamond-dossiers';

type StockMetric = {
    pcs: number;
    crt: string;
};

type StockRow = {
    rangeNo: number;
    crtMin: string;
    crtMax: string;
    available: StockMetric;
    reserved: StockMetric;
    used: StockMetric;
    actualAvailable: StockMetric;
    total: StockMetric;
};

type StockSection = {
    shapeId: number;
    shapeLabel: string;
    shapeCode: string | null;
    rows: StockRow[];
    totals: {
        available: StockMetric;
        reserved: StockMetric;
        used: StockMetric;
        actualAvailable: StockMetric;
        total: StockMetric;
    };
};

type DiamondStatus = 'available' | 'reserved' | 'used';

type StockReportProps = {
    sections: StockSection[];
    grandTotals: StockSection['totals'];
    unmapped: StockMetric;
    filters: {
        status: DiamondStatus | null;
        diamond_type: string | null;
    };
    diamondTypeOptions: { value: string; label: string }[];
    lastUpdatedAt: string | null;
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

    return `${dateLabel} ${timePart}`;
}

const STATUS_OPTIONS: { value: DiamondStatus | ''; label: string }[] = [
    { value: '', label: 'Semua status' },
    { value: 'reserved', label: 'Stok Dialokasikan (Reserved)' },
    { value: 'available', label: 'Stok Tersedia (Ready)' },
    { value: 'used', label: 'Terpakai (Used)' },
];

function statusFilterLabel(value: DiamondStatus | null): string | null {
    if (value === null) {
        return null;
    }

    return STATUS_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function optionFilterLabel(
    value: string | null,
    options: { value: string; label: string }[],
): string | null {
    if (value === null || value === '') {
        return null;
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

function formatCrt(value: string): string {
    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return value;
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function formatPcs(value: number): string {
    return value.toLocaleString('id-ID');
}

function formatCrtRange(crtMin: string, crtMax: string): string {
    return `Min: ${formatCrt(crtMin)}; Max: ${formatCrt(crtMax)}`;
}

function CrtRangeCell({
    crtMin,
    crtMax,
}: {
    crtMin: string;
    crtMax: string;
}) {
    return (
        <span className="spkDossierStockReportCrtRange tabular-nums">
            {formatCrt(crtMin)}–{formatCrt(crtMax)}
        </span>
    );
}

type MetricBucket =
    | 'available'
    | 'reserved'
    | 'actualAvailable'
    | 'total';

const METRIC_BUCKET_LABELS: Record<MetricBucket, string> = {
    available: 'Stok Tersedia (Ready)',
    reserved: 'Stok Dialokasikan (Reserved)',
    actualAvailable: 'Aktual Tersedia',
    total: 'Total',
};

const SHAPE_BAND_COUNT = 6;

function shapeBandClass(sectionIndex: number): string {
    const band = sectionIndex % SHAPE_BAND_COUNT;

    return `spkDossierStockReportShapeBand spkDossierStockReportShapeBand--${band}`;
}

function metricStatusForBucket(
    bucket: MetricBucket,
): StockListDialogRequest['status'] {
    if (bucket === 'total') {
        return null;
    }

    if (bucket === 'actualAvailable') {
        return 'actual_available';
    }

    return bucket;
}

function actualAvailableMetric(metric: StockMetric | undefined): StockMetric {
    if (metric !== undefined) {
        return metric;
    }

    return { pcs: 0, crt: '0.000' };
}

function isStockRowEmpty(row: StockRow): boolean {
    const actual = actualAvailableMetric(row.actualAvailable);

    return (
        row.reserved.pcs === 0
        && row.available.pcs === 0
        && actual.pcs === 0
    );
}

type StockMetricCellProps = {
    metric: StockMetric;
    bucket: MetricBucket;
    shapeId: number | null;
    shapeLabel: string | null;
    crtMin: string | null;
    crtMax: string | null;
    crtRangeLabel: string | null;
    diamondType: string;
    listStatus?: StockListDialogRequest['status'];
    onOpenList: (request: StockListDialogRequest) => void;
};

function StockMetricCell({
    metric,
    bucket,
    shapeId,
    shapeLabel,
    crtMin,
    crtMax,
    crtRangeLabel,
    diamondType,
    listStatus,
    onOpenList,
}: StockMetricCellProps) {
    if (metric.pcs === 0) {
        return (
            <div className="spkDossierStockMetricCell spkDossierStockMetricCell--empty">
                <span className="spkDossierStockMetricEmpty">—</span>
            </div>
        );
    }

    const shapePart = shapeLabel ?? 'Semua shape';
    const rangePart = crtRangeLabel ?? 'Semua range CRT';
    const bucketLabel = METRIC_BUCKET_LABELS[bucket];

    return (
        <div className="spkDossierStockMetricCell">
            <span className="spkDossierStockMetricCount tabular-nums">
                {formatPcs(metric.pcs)}
            </span>
            <button
                type="button"
                className="spkRowActionBtn spkRowActionBtn--list"
                aria-label={`Lihat daftar batu ${bucketLabel} — ${shapePart}, ${rangePart}`}
                title="Lihat daftar batu"
                onClick={() =>
                    onOpenList({
                        shapeId,
                        crtMin,
                        crtMax,
                        status:
                            listStatus ?? metricStatusForBucket(bucket),
                        diamondType,
                        title: `Daftar batu — ${bucketLabel}`,
                        subtitle: [shapePart, rangePart, bucketLabel].join(
                            ' · ',
                        ),
                    })
                }
            >
                <Icon name={listIcon} mode="Decorative" />
            </button>
        </div>
    );
}

function DossierStockReportMetricHead() {
    return (
        <thead>
            <tr>
                <th scope="col" className="spkDossierStockReportColCrt">
                    CRT
                </th>
                <th
                    scope="col"
                    className="spkDossierStockReportMetricCol"
                >
                    <span className="spkDossierStockReportColHead">
                        Dialokasikan
                    </span>
                    <span className="spkDossierStockReportColHeadSub">
                        Reserved
                    </span>
                </th>
                <th
                    scope="col"
                    className="spkDossierStockReportMetricCol"
                >
                    <span className="spkDossierStockReportColHead">
                        Tersedia
                    </span>
                    <span className="spkDossierStockReportColHeadSub">
                        Ready
                    </span>
                </th>
                <th
                    scope="col"
                    className="spkDossierStockReportMetricCol"
                >
                    <span className="spkDossierStockReportColHead">
                        Aktual Tersedia
                    </span>
                    <span className="spkDossierStockReportColHeadSub">
                        Ready + Reserved
                    </span>
                </th>
            </tr>
        </thead>
    );
}

type DossierShapeStockTableProps = {
    section: StockSection;
    sectionIndex: number;
    pageDiamondType: string;
    onOpenList: (request: StockListDialogRequest) => void;
};

function DossierShapeStockTable({
    section,
    sectionIndex,
    pageDiamondType,
    onOpenList,
}: DossierShapeStockTableProps) {
    const bandClass = shapeBandClass(sectionIndex);

    return (
        <article
            role="listitem"
            className={`spkDossierStockReportShapeCard ${bandClass}`}
            aria-label={`Stok ${section.shapeLabel}`}
        >
            <h3 className="spkDossierStockReportShapeTitle">
                {section.shapeLabel}
            </h3>
            <table className="spkTable spkDossierStockReportTable">
                <DossierStockReportMetricHead />
                <tbody>
                    {section.rows.map((row) => (
                        <tr
                            key={`${section.shapeId}-${row.rangeNo}`}
                            className={
                                isStockRowEmpty(row)
                                    ? 'spkDossierStockReportRow--empty'
                                    : undefined
                            }
                        >
                            <td className="spkDossierStockReportColCrt">
                                <CrtRangeCell
                                    crtMin={row.crtMin}
                                    crtMax={row.crtMax}
                                />
                            </td>
                            <td className="spkDossierStockReportMetricCol">
                                <StockMetricCell
                                    metric={row.reserved}
                                    bucket="reserved"
                                    shapeId={section.shapeId}
                                    shapeLabel={section.shapeLabel}
                                    crtMin={row.crtMin}
                                    crtMax={row.crtMax}
                                    crtRangeLabel={formatCrtRange(
                                        row.crtMin,
                                        row.crtMax,
                                    )}
                                    diamondType={pageDiamondType}
                                    onOpenList={onOpenList}
                                />
                            </td>
                            <td className="spkDossierStockReportMetricCol">
                                <StockMetricCell
                                    metric={row.available}
                                    bucket="available"
                                    shapeId={section.shapeId}
                                    shapeLabel={section.shapeLabel}
                                    crtMin={row.crtMin}
                                    crtMax={row.crtMax}
                                    crtRangeLabel={formatCrtRange(
                                        row.crtMin,
                                        row.crtMax,
                                    )}
                                    diamondType={pageDiamondType}
                                    onOpenList={onOpenList}
                                />
                            </td>
                            <td className="spkDossierStockReportMetricCol">
                                <StockMetricCell
                                    metric={actualAvailableMetric(
                                        row.actualAvailable,
                                    )}
                                    bucket="actualAvailable"
                                    shapeId={section.shapeId}
                                    shapeLabel={section.shapeLabel}
                                    crtMin={row.crtMin}
                                    crtMax={row.crtMax}
                                    crtRangeLabel={formatCrtRange(
                                        row.crtMin,
                                        row.crtMax,
                                    )}
                                    diamondType={pageDiamondType}
                                    onOpenList={onOpenList}
                                />
                            </td>
                        </tr>
                    ))}
                    <tr className="font-semibold spkDossierStockReportShapeSubtotal">
                        <td className="spkDossierStockReportSubtotalLabel text-right">
                            Subtotal
                        </td>
                        <td className="spkDossierStockReportMetricCol">
                            <StockMetricCell
                                metric={section.totals.reserved}
                                bucket="reserved"
                                shapeId={section.shapeId}
                                shapeLabel={section.shapeLabel}
                                crtMin={null}
                                crtMax={null}
                                crtRangeLabel="Semua range CRT"
                                diamondType={pageDiamondType}
                                onOpenList={onOpenList}
                            />
                        </td>
                        <td className="spkDossierStockReportMetricCol">
                            <StockMetricCell
                                metric={section.totals.available}
                                bucket="available"
                                shapeId={section.shapeId}
                                shapeLabel={section.shapeLabel}
                                crtMin={null}
                                crtMax={null}
                                crtRangeLabel="Semua range CRT"
                                diamondType={pageDiamondType}
                                onOpenList={onOpenList}
                            />
                        </td>
                        <td className="spkDossierStockReportMetricCol">
                            <StockMetricCell
                                metric={actualAvailableMetric(
                                    section.totals.actualAvailable,
                                )}
                                bucket="actualAvailable"
                                shapeId={section.shapeId}
                                shapeLabel={section.shapeLabel}
                                crtMin={null}
                                crtMax={null}
                                crtRangeLabel="Semua range CRT"
                                diamondType={pageDiamondType}
                                onOpenList={onOpenList}
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </article>
    );
}

export default function DiamondDossierStockReport({
    sections,
    grandTotals,
    filters,
    diamondTypeOptions,
    lastUpdatedAt,
}: StockReportProps) {
    const pageDiamondType = filters.diamond_type ?? '';

    const [listDialogOpen, setListDialogOpen] = useState(false);
    const [listDialogRequest, setListDialogRequest] =
        useState<StockListDialogRequest | null>(null);
    const [filterMenuOpen, setFilterMenuOpen] = useState(false);
    const [statusDraft, setStatusDraft] = useState(filters.status ?? '');
    const [diamondTypeDraft, setDiamondTypeDraft] = useState(
        filters.diamond_type ?? '',
    );

    const openDiamondList = (request: StockListDialogRequest) => {
        setListDialogRequest(request);
        setListDialogOpen(true);
    };

    useEffect(() => {
        setStatusDraft(filters.status ?? '');
    }, [filters.status]);

    useEffect(() => {
        setDiamondTypeDraft(filters.diamond_type ?? '');
    }, [filters.diamond_type]);

    const visit = (params: {
        status?: DiamondStatus | null;
        diamond_type?: string | null;
    }) => {
        router.get(
            diamondDossierStockReport.url({
                query: {
                    status:
                        ('status' in params ? params.status : filters.status)
                        || undefined,
                    diamond_type:
                        ('diamond_type' in params
                            ? params.diamond_type
                            : filters.diamond_type) || undefined,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const hasActiveStatusFilter = filters.status !== null;
    const hasActiveDiamondTypeFilter = filters.diamond_type !== null;
    const hasActiveTableFilters =
        hasActiveStatusFilter || hasActiveDiamondTypeFilter;

    const hasFilterDraftChanges =
        statusDraft !== (filters.status ?? '')
        || diamondTypeDraft !== (filters.diamond_type ?? '');

    const hasFilterDraftCustom =
        statusDraft !== '' || diamondTypeDraft !== '';

    const applyTableFilters = () => {
        setFilterMenuOpen(false);
        visit({
            status: (statusDraft as DiamondStatus) || null,
            diamond_type: diamondTypeDraft || null,
        });
    };

    const clearTableFilters = () => {
        setStatusDraft('');
        setDiamondTypeDraft('');
        setFilterMenuOpen(false);
        visit({
            status: null,
            diamond_type: null,
        });
    };

    const clearAllFilters = () => {
        setStatusDraft('');
        setDiamondTypeDraft('');
        visit({
            status: null,
            diamond_type: null,
        });
    };

    const downloadExcel = () => {
        window.location.href = diamondDossierStockReportExport.url({
            query: {
                status: filters.status ?? undefined,
                diamond_type: filters.diamond_type ?? undefined,
            },
        });
    };

    return (
        <>
            <Head title="Laporan Stok Batu Dossier" />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    Laporan Stok
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Rekap stok per matrix CRT dossier · Terakhir
                                    diperbaharui:{' '}
                                    {formatDateTimeDisplay(lastUpdatedAt)}
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <div
                                className="spkTableHeaderActions--finishing"
                                role="group"
                                aria-label="Aksi laporan stok"
                            >
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
                                                                    option.value ||
                                                                    'all'
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
                            </div>
                            <span
                                className="spkTableHeaderDivider--finishing"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Export laporan stok ke Excel"
                                onClick={downloadExcel}
                            >
                                <Icon
                                    name={excelAttachmentIcon}
                                    mode="Decorative"
                                />
                                <span>Export Excel</span>
                            </button>
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke stok batu dossier"
                                onClick={() =>
                                    router.visit(diamondDossiersIndex.url())
                                }
                            >
                                <Icon name={navBackIcon} mode="Decorative" />
                                <span>Kembali ke Stok</span>
                            </button>
                        </div>
                    </div>

                    <ActiveFilterSummary
                        sort={null}
                        filters={[
                            hasActiveStatusFilter && {
                                key: 'status',
                                label: 'Status',
                                value:
                                    statusFilterLabel(filters.status) ?? '—',
                                onRemove: () => {
                                    visit({ status: null });
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
                                    visit({ diamond_type: null });
                                },
                            },
                        ]}
                        onClearAll={clearAllFilters}
                    />

                    {sections.length === 0 ? (
                        <div className="spkPlaceholderPanel px-4 pb-6">
                            Matrix CRT dossier belum dikonfigurasi. Atur di Master
                            Data → Matrix CRT Dossier.
                        </div>
                    ) : (
                        <div className="spkTableScroll spkTableScroll--finishing spkTableScroll--dossierStockReport">
                            <div
                                className="spkDossierStockReportGrid"
                                role="list"
                                aria-label="Laporan stok per shape"
                            >
                                {sections.map((section, sectionIndex) => (
                                    <DossierShapeStockTable
                                        key={section.shapeId}
                                        section={section}
                                        sectionIndex={sectionIndex}
                                        pageDiamondType={pageDiamondType}
                                        onOpenList={openDiamondList}
                                    />
                                ))}
                            </div>
                            <div
                                className="spkDossierStockReportGrandTotalWrap"
                                role="region"
                                aria-label="Grand total laporan stok"
                            >
                                <table className="spkTable spkDossierStockReportTable spkDossierStockReportTable--grandTotal">
                                    <tbody>
                                        <tr className="font-semibold spkDossierStockReportGrandTotal">
                                            <td className="spkDossierStockReportGrandTotalLabel">
                                                Grand total
                                            </td>
                                            <td className="spkDossierStockReportMetricCol">
                                                <StockMetricCell
                                                    metric={
                                                        grandTotals.reserved
                                                    }
                                                    bucket="reserved"
                                                    shapeId={null}
                                                    shapeLabel={null}
                                                    crtMin={null}
                                                    crtMax={null}
                                                    crtRangeLabel={null}
                                                    diamondType={
                                                        pageDiamondType
                                                    }
                                                    onOpenList={
                                                        openDiamondList
                                                    }
                                                />
                                            </td>
                                            <td className="spkDossierStockReportMetricCol">
                                                <StockMetricCell
                                                    metric={
                                                        grandTotals.available
                                                    }
                                                    bucket="available"
                                                    shapeId={null}
                                                    shapeLabel={null}
                                                    crtMin={null}
                                                    crtMax={null}
                                                    crtRangeLabel={null}
                                                    diamondType={
                                                        pageDiamondType
                                                    }
                                                    onOpenList={
                                                        openDiamondList
                                                    }
                                                />
                                            </td>
                                            <td className="spkDossierStockReportMetricCol">
                                                <StockMetricCell
                                                    metric={actualAvailableMetric(
                                                        grandTotals.actualAvailable,
                                                    )}
                                                    bucket="actualAvailable"
                                                    shapeId={null}
                                                    shapeLabel={null}
                                                    crtMin={null}
                                                    crtMax={null}
                                                    crtRangeLabel={null}
                                                    diamondType={
                                                        pageDiamondType
                                                    }
                                                    onOpenList={
                                                        openDiamondList
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
            <DiamondDossierStockListDialog
                open={listDialogOpen}
                request={listDialogRequest}
                onOpenChange={setListDialogOpen}
            />
        </>
    );
}

DiamondDossierStockReport.layout = {
    activeMenu: 'Stok Batu Dossier',
    pageTitle: 'Laporan Stok Batu Dossier',
};
