import { Head, Link, router } from '@inertiajs/react';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { SpkTableDescriptionCell } from '@/components/spk/spk-list-cells';
import { index as stokRangkaIndex } from '@/routes/inventory/frame-stocks';
import { show as spkShow } from '@/routes/spk';

type StokRangkaDetail = {
    id: number;
    docNo: string | null;
    skuCode: string | null;
    skuBreakdownName: string | null;
    category: string | null;
    itemName: string | null;
    variant: string | null;
    spkNo: string | null;
    dateIn: string | null;
    dateOut: string | null;
    goldWeight: string | null;
    goldColor: string | null;
    status: string | null;
    createdBy: string | null;
    updatedBy: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

type StokRangkaShowProps = {
    item: StokRangkaDetail;
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

function formatWeight(value: string | null): string {
    if (value === null || value === '') {
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

function statusBadgeClass(status: string | null): string {
    if (status === 'READY') {
        return 'spkStoneStockBadge is-available';
    }

    if (status === 'USED') {
        return 'spkStoneStockBadge is-unavailable';
    }

    if (status !== null && status !== '') {
        return 'inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800';
    }

    return 'spkStoneStockBadge is-unavailable';
}

function recordTitle(item: StokRangkaDetail): string {
    if (item.docNo) {
        return item.docNo;
    }

    return `Rangka #${item.id}`;
}

export default function StokRangkaShow({ item }: StokRangkaShowProps) {
    const title = recordTitle(item);

    return (
        <>
            <Head title={`Stok Rangka · ${title}`} />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    {title}
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Detail stok rangka
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke daftar stok rangka"
                                onClick={() =>
                                    router.visit(stokRangkaIndex.url())
                                }
                            >
                                <Icon
                                    name={navBackIcon}
                                    mode="Decorative"
                                />
                                <span>Kembali</span>
                            </button>
                        </div>
                    </div>

                    <div className="spkChainDetailSummary">
                        <span className="spkDetailFieldLabel">
                            Informasi stok
                        </span>
                        <dl className="spkDetailGrid">
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">ID</dt>
                                <dd className="spkDetailValue font-medium">
                                    {item.docNo ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">No. SPK</dt>
                                <dd className="spkDetailValue">
                                    {item.spkNo !== null
                                    && item.spkNo !== '' ? (
                                        <Link
                                            href={spkShow.url(item.spkNo)}
                                            className="spkProduksiLink"
                                        >
                                            {item.spkNo}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Kategori
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.category ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">SKU</dt>
                                <dd className="spkDetailValue">
                                    <SpkTableDescriptionCell
                                        row={{
                                            typeSkuLabel: item.skuCode,
                                            itemDescription:
                                                item.skuBreakdownName,
                                            skuAssigned: Boolean(
                                                item.skuCode?.trim()
                                                || item.skuBreakdownName?.trim(),
                                            ),
                                        }}
                                    />
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Nama item
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.itemName ?? '—'}
                                </dd>
                            </div>
                            {item.variant ? (
                                <div className="spkDetailField">
                                    <dt className="spkDetailFieldLabel">
                                        Varian
                                    </dt>
                                    <dd className="spkDetailValue">
                                        {item.variant}
                                    </dd>
                                </div>
                            ) : null}
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Tanggal masuk
                                </dt>
                                <dd className="spkDetailValue">
                                    {formatDateDisplay(item.dateIn)}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Tanggal keluar
                                </dt>
                                <dd className="spkDetailValue">
                                    {formatDateDisplay(item.dateOut)}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Warna emas
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.goldColor ?? '—'}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Berat (g)
                                </dt>
                                <dd className="spkDetailValue tabular-nums">
                                    {formatWeight(item.goldWeight)}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">Status</dt>
                                <dd className="spkDetailValue">
                                    <span
                                        className={statusBadgeClass(
                                            item.status,
                                        )}
                                    >
                                        {item.status ?? '—'}
                                    </span>
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Dibuat oleh
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.createdBy ?? '—'}
                                    {item.createdAt ? (
                                        <span className="block text-sm text-muted-foreground">
                                            {formatDateTimeDisplay(
                                                item.createdAt,
                                            )}
                                        </span>
                                    ) : null}
                                </dd>
                            </div>
                            <div className="spkDetailField">
                                <dt className="spkDetailFieldLabel">
                                    Diubah oleh
                                </dt>
                                <dd className="spkDetailValue">
                                    {item.updatedBy ?? '—'}
                                    {item.updatedAt ? (
                                        <span className="block text-sm text-muted-foreground">
                                            {formatDateTimeDisplay(
                                                item.updatedAt,
                                            )}
                                        </span>
                                    ) : null}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </>
    );
}

StokRangkaShow.layout = {
    activeMenu: 'Stok Rangka',
    pageTitle: 'Detail Stok Rangka',
};
