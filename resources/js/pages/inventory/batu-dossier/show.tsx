import { Head, Link, router } from '@inertiajs/react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import chainLinkIcon from '@ui5/webcomponents-icons/dist/chain-link.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useState, type ReactNode } from 'react';
import { DiamondDossierEditDialog } from '@/pages/inventory/batu-dossier/diamond-dossier-form-dialog';
import type {
    DossierFormValues,
    DossierShapeOption,
} from '@/pages/inventory/batu-dossier/dossier-form-fields';
import { index as diamondDossiersIndex, revokeAllocation as revokeDiamondAllocation } from '@/routes/inventory/diamond-dossiers';
import { show as pasangBatuShow } from '@/routes/pasang-batu';
import { show as spkShow } from '@/routes/spk';

type DiamondStatus = 'available' | 'reserved' | 'used';

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
    reservedSpkNo: string | null;
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
    canEdit: boolean;
    canRevokeAllocation: boolean;
    shapeOptions: DossierShapeOption[];
    diamondTypeOptions: DossierShapeOption[];
    form: DossierFormValues | null;
};

type MainSection = 'produksi' | 'riwayat';

const GIA_REPORT_CHECK_BASE_URL =
    'https://www.gia.edu/report-check?locale=en_US&reportno=';

function giaReportCheckUrl(certificate: string | null): string | null {
    const trimmed = certificate?.trim() ?? '';

    if (trimmed === '') {
        return null;
    }

    return `${GIA_REPORT_CHECK_BASE_URL}${encodeURIComponent(trimmed)}`;
}

function certificateFieldValue(certificate: string | null): ReactNode {
    const trimmed = certificate?.trim() ?? '';
    const giaUrl = giaReportCheckUrl(certificate);

    if (trimmed === '') {
        return '—';
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-2">
            <span>{trimmed}</span>
            {giaUrl !== null ? (
                <a
                    href={giaUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="spkFormLink inline-flex shrink-0 items-center"
                    title="Cek sertifikat di GIA Report Check"
                    aria-label={`Buka sertifikat GIA ${trimmed} di tab baru`}
                >
                    <Icon name={chainLinkIcon} mode="Decorative" className="text-blue-500" />
                </a>
            ) : null}
        </span>
    );
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

    const [datePart, timePart] = trimmed.split(/\s+/u);
    const [year, month, day] = (datePart ?? '').split('-');

    if (!year || !month || !day) {
        return trimmed;
    }

    const monthLabel = MONTH_LABELS[Number(month) - 1];

    if (!monthLabel) {
        return trimmed;
    }

    const dateLabel = `${Number(day)}-${monthLabel}-${year}`;

    if (!timePart) {
        return dateLabel;
    }

    return `${dateLabel} ${timePart.slice(0, 5)}`;
}

function parseStockDate(value: string | null): Date | null {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return null;
    }

    const datePart = trimmed.split(/\s+/u)[0] ?? '';
    const [year, month, day] = datePart.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function calendarDayDiff(start: Date, end: Date): number {
    const startUtc = Date.UTC(
        start.getFullYear(),
        start.getMonth(),
        start.getDate(),
    );
    const endUtc = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

    return Math.max(0, Math.floor((endUtc - startUtc) / 86_400_000));
}

function formatAgeDuration(totalDays: number): string {
    if (totalDays < 30) {
        return `${totalDays} hari`;
    }

    if (totalDays < 365) {
        const months = Math.floor(totalDays / 30);
        const days = totalDays % 30;

        if (days === 0) {
            return `${months} bulan`;
        }

        return `${months} bulan ${days} hari`;
    }

    const years = Math.floor(totalDays / 365);
    const remainder = totalDays % 365;
    const months = Math.floor(remainder / 30);
    const days = remainder % 30;

    const parts = [`${years} tahun`];

    if (months > 0) {
        parts.push(`${months} bulan`);
    }

    if (days > 0) {
        parts.push(`${days} hari`);
    }

    return parts.join(' ');
}

function formatUmurBarang(
    entryDate: string | null,
    outDate: string | null,
    status: DiamondStatus,
): string {
    const entry = parseStockDate(entryDate);

    if (entry === null) {
        return '—';
    }

    if (status === 'used') {
        const out = parseStockDate(outDate);

        if (out === null) {
            return '—';
        }

        return formatAgeDuration(calendarDayDiff(entry, out));
    }

    return formatAgeDuration(calendarDayDiff(entry, new Date()));
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

function statusShortLabel(status: DiamondStatus): string {
    if (status === 'used') {
        return 'Terpakai';
    }

    if (status === 'reserved') {
        return 'Reserved';
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

function recordTitle(diamond: DossierDiamondDetail): string {
    if (diamond.code) {
        return diamond.code;
    }

    return `Batu #${diamond.id}`;
}

function OdooFormField({
    label,
    children,
    className,
}: {
    label: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className="spkFormField">
            <span className="spkFormLabel">{label}</span>
            <div
                className={['spkFormValue', className].filter(Boolean).join(' ')}
            >
                {children}
            </div>
        </div>
    );
}

export default function DiamondDossierShow({
    diamond,
    canEdit,
    canRevokeAllocation,
    shapeOptions,
    diamondTypeOptions,
    form,
}: PageProps) {
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [revokingAllocation, setRevokingAllocation] = useState(false);
    const [mainSection, setMainSection] = useState<MainSection>('produksi');
    const title = recordTitle(diamond);
    const subtitle = [
        diamond.diamondType,
        diamond.shape,
        diamond.color,
        diamond.crt !== null
            ? `${formatCrt(diamond.crt)} CRT`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    const handleRevokeAllocation = (): void => {
        if (revokingAllocation) {
            return;
        }

        const confirmed = window.confirm(
            'Batalkan alokasi batu dossier ini dari SPK? Batu akan kembali tersedia di stok.',
        );

        if (!confirmed) {
            return;
        }

        setRevokingAllocation(true);

        router.post(
            revokeDiamondAllocation.url(diamond.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setRevokingAllocation(false);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Batu Dossier · ${title}`} />
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <div className="spkTopBarCard">
                        <div className="spkTopBarMain">
                            <div className="spkTopBarLeft">
                                <div className="spkDocTitleBlock">
                                    <div className="spkDocTitleRow">
                                        <h1 className="spkDocTitle">{title}</h1>
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
                                    </div>
                                    {subtitle !== '' ? (
                                        <p className="jewelCadDocSubtitle">
                                            {subtitle}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="spkControlBarRight">
                                <div className="spkHeaderActions">
                                    {canEdit && form !== null ? (
                                        <button
                                            type="button"
                                            className="spkHeaderActionBtn"
                                            aria-label="Edit"
                                            title="Edit batu dossier"
                                            onClick={() =>
                                                setEditDialogOpen(true)
                                            }
                                        >
                                            <Icon
                                                name={editIcon}
                                                mode="Decorative"
                                            />
                                        </button>
                                    ) : null}
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                        aria-label="Tutup"
                                        title="Kembali ke stok"
                                        onClick={() =>
                                            router.visit(
                                                diamondDossiersIndex.url(),
                                            )
                                        }
                                    >
                                        <Icon
                                            name={declineIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="spkDetailBody spkDetailBody--dossierOdoo">
                        <div className="spkOdooFormSheetCard">
                            <div className="spkOdooFormSheetHeader spkFormGrid">
                                <div className="spkFormColumn">
                                    <OdooFormField label="ID">
                                        {diamond.code ?? '—'}
                                    </OdooFormField>
                                    <OdooFormField label="Tipe">
                                        {diamond.diamondType ?? '—'}
                                    </OdooFormField>
                                    <OdooFormField label="Bentuk">
                                        {diamond.shape ?? '—'}
                                    </OdooFormField>
                                    <OdooFormField label="CRT">
                                        {formatCrt(diamond.crt)}
                                    </OdooFormField>
                                    <OdooFormField label="Warna">
                                        {diamond.color ?? '—'}
                                    </OdooFormField>
                                    <OdooFormField
                                        label="No. Sertifikat"
                                        className="break-all"
                                    >
                                        {certificateFieldValue(diamond.certificate)}
                                    </OdooFormField>
                                </div>
                                <div className="spkFormColumn">
                                    <OdooFormField label="Supplier">
                                        {diamond.supplier ?? '—'}
                                    </OdooFormField>
                                    <OdooFormField label="Tanggal masuk">
                                        {formatDateDisplay(diamond.entryDate)}
                                    </OdooFormField>
                                    <OdooFormField label="Umur Barang">
                                        {formatUmurBarang(
                                            diamond.entryDate,
                                            diamond.outDate,
                                            diamond.status,
                                        )}
                                    </OdooFormField>
                                </div>
                            </div>

                            <div className="spkOdooFormSheetNotebook">
                                <div
                                    className="spkSectionTabs spkOdooFormSheetTabs"
                                    role="tablist"
                                    aria-label="Detail batu dossier"
                                >
                                    {(
                                        [
                                            ['produksi', 'Produksi'],
                                            ['riwayat', 'Riwayat'],
                                        ] as const
                                    ).map(([tabId, tabLabel]) => (
                                        <button
                                            key={tabId}
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                mainSection === tabId
                                            }
                                            className={[
                                                'spkSectionTab',
                                                mainSection === tabId
                                                    ? 'is-active'
                                                    : '',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            onClick={() =>
                                                setMainSection(tabId)
                                            }
                                        >
                                            <span className="spkSectionTabLabel">
                                                {tabLabel}
                                            </span>
                                        </button>
                                    ))}
                                </div>

                                {mainSection === 'produksi' ? (
                                    <div
                                        role="tabpanel"
                                        className="spkOdooFormSheetPanel"
                                        aria-label="Produksi"
                                    >
                                        <div className="spkFormColumn spkOdooFormSheetFields">
                                            <OdooFormField label="Status" className="spkFormField--inline">
                                                <span
                                                    className={statusBadgeClass(
                                                        diamond.status,
                                                    )}
                                                >
                                                    {statusShortLabel(
                                                        diamond.status,
                                                    )}
                                                </span>
                                            </OdooFormField>
                                            <OdooFormField label="No. SPK">
                                                <div className="spkDossierSpkAllocationRow">
                                                    {diamond.spkNo !== null &&
                                                    diamond.spkNo !== '' ? (
                                                        <Link
                                                            href={spkShow.url(
                                                                diamond.spkNo,
                                                            )}
                                                            className="spkFormLink"
                                                        >
                                                            {diamond.spkNo}
                                                        </Link>
                                                    ) : (
                                                        <span>—</span>
                                                    )}
                                                    {canRevokeAllocation ? (
                                                        <button
                                                            type="button"
                                                            className="spkDossierRevokeAllocationBtn"
                                                            aria-label="Hapus alokasi"
                                                            title="Hapus alokasi batu dari SPK"
                                                            disabled={
                                                                revokingAllocation
                                                            }
                                                            onClick={
                                                                handleRevokeAllocation
                                                            }
                                                        >
                                                            <Icon
                                                                name={
                                                                    deleteIcon
                                                                }
                                                                mode="Decorative"
                                                            />
                                                            <span className="spkChainPrintReceiptBtnLabel">
                                                                Hapus alokasi
                                                            </span>
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </OdooFormField>
                                            <OdooFormField label="Dokumen Pasang Batu">
                                                {diamond.mountingDocumentId !==
                                                    null &&
                                                diamond.mountingDocumentNo !==
                                                    null ? (
                                                    <Link
                                                        href={pasangBatuShow.url(
                                                            diamond.mountingDocumentId,
                                                        )}
                                                        className="spkFormLink"
                                                    >
                                                        {
                                                            diamond.mountingDocumentNo
                                                        }
                                                    </Link>
                                                ) : (
                                                    (diamond.mountingDocumentNo ??
                                                    '—')
                                                )}
                                            </OdooFormField>
                                            <OdooFormField label="Tanggal keluar">
                                                {formatDateDisplay(
                                                    diamond.outDate,
                                                )}
                                            </OdooFormField>
                                        </div>
                                    </div>
                                ) : null}

                                {mainSection === 'riwayat' ? (
                                    <div
                                        role="tabpanel"
                                        className="spkOdooFormSheetPanel"
                                        aria-label="Riwayat"
                                    >
                                        <div className="spkFormColumn spkOdooFormSheetFields">
                                            <OdooFormField label="Dibuat">
                                                {formatAudit(
                                                    diamond.createdDate,
                                                    diamond.createdBy,
                                                )}
                                            </OdooFormField>
                                            <OdooFormField label="Terakhir diubah">
                                                {formatAudit(
                                                    diamond.modifiedDate,
                                                    diamond.modifiedBy,
                                                )}
                                            </OdooFormField>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {canEdit && form !== null ? (
                <DiamondDossierEditDialog
                    diamondId={diamond.id}
                    shapeOptions={shapeOptions}
                    diamondTypeOptions={diamondTypeOptions}
                    form={form}
                    open={editDialogOpen}
                    onOpenChange={setEditDialogOpen}
                />
            ) : null}
        </>
    );
}

DiamondDossierShow.layout = {
    activeMenu: 'Stok Batu Dossier',
    pageTitle: 'Detail Batu Dossier',
};
