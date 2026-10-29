import { Head, Link, router } from '@inertiajs/react';
import navBackIcon from '@ui5/webcomponents-icons/dist/nav-back.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { index as diamondDossiersIndex } from '@/routes/inventory/diamond-dossiers';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

type DiamondStatus = 'available' | 'used';

type DossierDiamondDetail = {
    id: number;
    code: string | null;
    diamondType: string | null;
    shape: string | null;
    color: string | null;
    crt: string | null;
    certificate: string | null;
    supplier: string | null;
    entryDate: string | null;
    outDate: string | null;
    status: DiamondStatus;
    mountingDocumentNo: string | null;
    mountingDocumentId: number | null;
    spkNo: string | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
};

type PageProps = {
    diamond: DossierDiamondDetail;
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

function formatCrt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number(value));
}

function formatAudit(date: string | null, actor: string | null): string {
    const dateLabel = formatDateDisplay(date);

    if (dateLabel === '—' && !actor) {
        return '—';
    }

    if (dateLabel === '—') {
        return actor ?? '—';
    }

    if (!actor) {
        return dateLabel;
    }

    return `${dateLabel} · ${actor}`;
}

function statusClass(status: DiamondStatus): string {
    if (status === 'used') {
        return 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
    }

    return 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
}

export default function DiamondDossierShow({ diamond }: PageProps) {
    const title = diamond.code ?? `Batu #${diamond.id}`;

    return (
        <>
            <Head title={`Batu Dossier · ${title}`} />
            <div className="spkTableShell">
                <div className="spkTableCard">
                    <div className="spkTableToolbar spkTableHeaderBar--finishing">
                        <div className="spkTableToolbarLeft">
                            <div className="spkTableTitleBlock spkTableTitleBlock--finishing">
                                <h2 className="spkTableHeaderTitle--finishing">
                                    {title}
                                </h2>
                                <p className="spkTableHeaderSubtitle--finishing text-muted-foreground">
                                    Detail batu dossier
                                </p>
                            </div>
                        </div>
                        <div className="spkTableToolbarRight">
                            <button
                                type="button"
                                className="spkCreateBtn spkCreateBtn--outline"
                                aria-label="Kembali ke stok batu dossier"
                                onClick={() =>
                                    router.visit(diamondDossiersIndex.url())
                                }
                            >
                                <Icon
                                    name={navBackIcon}
                                    mode="Decorative"
                                />
                                <span>Kembali ke Stok</span>
                            </button>
                        </div>
                    </div>

                    <div className="fioriVarianceDetailCard mx-4 mb-4">
                        <span className="fioriVarianceDetailTitle">
                            Informasi Batu
                        </span>
                        <dl className="fioriVarianceDetailGrid">
                            <div className="fioriVarianceDetailRow">
                                <dt>Kode</dt>
                                <dd className="font-medium">
                                    {diamond.code ?? '—'}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Tipe</dt>
                                <dd>{diamond.diamondType ?? '—'}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Bentuk</dt>
                                <dd>{diamond.shape ?? '—'}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Warna</dt>
                                <dd>{diamond.color ?? '—'}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>CRT</dt>
                                <dd className="tabular-nums">
                                    {formatCrt(diamond.crt)}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>No. Sertifikat</dt>
                                <dd>{diamond.certificate ?? '—'}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Supplier</dt>
                                <dd>{diamond.supplier ?? '—'}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Status</dt>
                                <dd>
                                    <span className={statusClass(diamond.status)}>
                                        {diamond.status === 'used'
                                            ? 'Terpakai'
                                            : 'Tersedia'}
                                    </span>
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Tanggal masuk</dt>
                                <dd>
                                    {formatDateDisplay(diamond.entryDate)}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Tanggal keluar</dt>
                                <dd>{formatDateDisplay(diamond.outDate)}</dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Dokumen Pasang Batu</dt>
                                <dd>
                                    {diamond.mountingDocumentId !== null &&
                                    diamond.mountingDocumentNo !== null ? (
                                        <Link
                                            href={pasangBatuShow.url(
                                                diamond.mountingDocumentId,
                                            )}
                                            className="spkProduksiLink"
                                        >
                                            {diamond.mountingDocumentNo}
                                        </Link>
                                    ) : (
                                        (diamond.mountingDocumentNo ?? '—')
                                    )}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>No. SPK</dt>
                                <dd>
                                    {diamond.spkNo !== null &&
                                    diamond.spkNo !== '' ? (
                                        <Link
                                            href={spkShow.url(diamond.spkNo)}
                                            className="spkProduksiLink"
                                        >
                                            {diamond.spkNo}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Dibuat</dt>
                                <dd>
                                    {formatAudit(
                                        diamond.createdDate,
                                        diamond.createdBy,
                                    )}
                                </dd>
                            </div>
                            <div className="fioriVarianceDetailRow">
                                <dt>Diubah</dt>
                                <dd>
                                    {formatAudit(
                                        diamond.modifiedDate,
                                        diamond.modifiedBy,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </>
    );
}

DiamondDossierShow.layout = {
    activeMenu: 'Stok Batu Dossier',
    pageTitle: 'Detail Batu Dossier',
};
