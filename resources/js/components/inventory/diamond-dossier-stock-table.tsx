import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { show as diamondDossierShow } from '@/routes/inventory/diamond-dossiers';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

export type DossierDiamondRow = {
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
    status: 'available' | 'reserved' | 'used';
    reservedSpkNo: string | null;
    spkNo: string | null;
    mountingDocumentNo: string | null;
    mountingDocumentId: number | null;
    createdBy: string | null;
    createdDate: string | null;
    modifiedBy: string | null;
    modifiedDate: string | null;
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

function statusLabel(
    status: DossierDiamondRow['status'],
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

function statusBadgeClass(status: DossierDiamondRow['status']): string {
    if (status === 'used') {
        return 'spkStoneStockBadge is-unavailable';
    }

    if (status === 'reserved') {
        return 'inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800';
    }

    return 'spkStoneStockBadge is-available';
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

    if (timePart) {
        return `${day} ${monthLabel} ${year} ${timePart}`;
    }

    return `${day} ${monthLabel} ${year}`;
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

type DiamondDossierStockTableProps = {
    diamonds: DossierDiamondRow[];
    emptyMessage?: string;
};

export function DiamondDossierStockTable({
    diamonds,
    emptyMessage = 'Tidak ada batu dossier.',
}: DiamondDossierStockTableProps) {
    return (
        <div className="spkTableScroll spkTableScroll--finishing max-h-[min(70vh,36rem)]">
            <table className="spkTable">
                <thead>
                    <tr>
                        <th className="spkTableColDoc--spk spkTableColDoc--dossierId">
                            ID
                        </th>
                        <th>Tipe</th>
                        <th>Bentuk</th>
                        <th className="spkTableColCenter">Warna</th>
                        <th className="spkTableColCenter">CRT</th>
                        <th className="spkTableColCenter">No. Sertifikat</th>
                        <th className="spkTableColCenter">Supplier</th>
                        <th className="spkTableColCenter">Tanggal masuk</th>
                        <th className="spkTableColCenter">Tanggal keluar</th>
                        <th className="spkTableColCenter">Status</th>
                        <th className="spkTableColCenter">No. Pasang Batu</th>
                        <th className="spkTableColCenter">No. SPK</th>
                        <th className="spkTableColCenter">Dibuat oleh</th>
                        <th className="spkTableColCenter">Diubah oleh</th>
                    </tr>
                </thead>
                <tbody>
                    {diamonds.length === 0 ? (
                        <tr>
                            <td colSpan={14}>{emptyMessage}</td>
                        </tr>
                    ) : (
                        diamonds.map((diamond) => (
                            <tr key={diamond.id}>
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
                                <td>{diamond.diamondType ?? '—'}</td>
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
                                    {formatDateDisplay(diamond.entryDate)}
                                </td>
                                <td className="spkTableColCenter">
                                    {formatDateDisplay(diamond.outDate)}
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
                                </td>
                                <td className="spkTableColCenter">
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
    );
}
