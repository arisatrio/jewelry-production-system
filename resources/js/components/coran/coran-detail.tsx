import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import editIcon from '@ui5/webcomponents-icons/dist/edit.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkOrderTypeColumn } from '@/components/spk/spk-order-type-column';
import {
    SpkApprovalTimelinePanel,
    type SpkApprovalTimelineEvent,
} from '@/components/spk/spk-approval-timeline-panel';
import {
    CoranMaterialBreakdownTables,
    type CoranBreakdownSection,
} from '@/components/coran/coran-material-breakdown';
import {
    resolveQcStatusFromCoranStatus,
    SpkQcStatusBadge,
} from '@/components/spk/spk-qc-status-badge';

type CoranMaterialLine = {
    name: string;
    weight: string;
};

type CoranDetailRow = {
    lineId: number;
    spkId: number;
    spkNo: string | null;
    spkType: string | null;
    orderTypeLabel: string | null;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    itemDescription: string | null;
    customerName: string | null;
    satuan: string;
    weight: string | null;
    kadar: string | null;
    status: string | null;
    statusLabel: string;
};

type CoranDetailItem = {
    id: number;
    docNo: string | null;
    transDate: string | null;
    status: string | null;
    statusLabel: string;
    craftsmanId: number | null;
    craftsmanName: string | null;
    submitMaterials: CoranMaterialLine[];
    resultMaterials: CoranMaterialLine[];
    totalSubmitMaterial: string | null;
    totalResultMaterial: string | null;
    totalSpkWeight: string | null;
    spkCount: number;
    okSpkPercent: string | null;
    shrink: string | null;
    coranBreakdown: CoranBreakdownSection[];
    details: CoranDetailRow[];
};

type CoranWorkflowStatus = {
    key: string;
    label: string;
    stageIndex: number;
    stages: Array<{ key: string; label: string }>;
};

type ApprovalFooterColumn = {
    title: string;
    name: string;
    date: string;
};

type ApprovalHistoryEvent = {
    status: string;
    statusLabel: string;
    approve: string;
    notes: string | null;
    createdBy: string | null;
    createdAt: string | null;
};

type CoranApprovalAbilities = {
    canSubmit: boolean;
    canEdit: boolean;
    canOpenEdit: boolean;
    canDelete: boolean;
    canManagerApprove: boolean;
    canComplete: boolean;
    status: string;
    statusLabel: string;
};

type CoranDetailProps = {
    coranItem: CoranDetailItem;
    workflowStatus: CoranWorkflowStatus;
    approvalHistory: ApprovalHistoryEvent[];
    approvalFooter: ApprovalFooterColumn[];
    approval: CoranApprovalAbilities;
    editHref: string;
    backHref: string;
    deleteUrl: string;
    submitUrl?: string;
    managerApproveUrl?: string;
    completeUrl?: string;
};

function displayValue(value: string | null | undefined): string {
    const trimmed = value?.trim() ?? '';

    return trimmed !== '' ? trimmed : '—';
}

function parseDecimal(value: string | null | undefined): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const trimmed = value.trim().replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const numeric = Number(trimmed);

    return Number.isFinite(numeric) ? numeric : null;
}

function formatShareOfTotalMaterial(
    value: string | null | undefined,
    totalMaterial: string | null | undefined,
): string {
    const amount = parseDecimal(value);
    const total = parseDecimal(totalMaterial);

    if (amount === null) {
        return '—';
    }

    const amountLabel = displayValue(value);

    if (total === null || Math.abs(total) < 0.0005) {
        return amountLabel;
    }

    const percent = (amount / total) * 100;

    return `${amountLabel} (${percent.toFixed(2)}%)`;
}

function formatTransDate(isoDate: string | null): string {
    if (!isoDate) {
        return '—';
    }

    const [year, month, day] = isoDate.split('-');

    if (!year || !month || !day) {
        return isoDate;
    }

    return `${day}/${month}/${year}`;
}

export function CoranDetail({
    coranItem,
    workflowStatus,
    approvalHistory,
    approvalFooter,
    approval,
    editHref,
    backHref,
    deleteUrl,
    submitUrl,
    managerApproveUrl,
    completeUrl,
}: CoranDetailProps) {
    const [mainSection, setMainSection] = useState<'informasi' | 'riwayat'>(
        'informasi',
    );
    const [submitting, setSubmitting] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const activeStageIndex = workflowStatus.stageIndex;
    const statusStages = workflowStatus.stages;

    const approvalTimeline = useMemo<SpkApprovalTimelineEvent[]>(
        () =>
            approvalHistory.map((event) => ({
                source: 'Coran',
                status: event.status,
                statusLabel: event.statusLabel,
                approve: event.approve,
                notes: event.notes,
                createdBy: event.createdBy,
                createdAt: event.createdAt,
            })),
        [approvalHistory],
    );

    const submitToManager = () => {
        if (!submitUrl || !approval.canSubmit) {
            return;
        }

        setSubmitting(true);
        router.post(
            submitUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const approveByManager = () => {
        if (!managerApproveUrl || !approval.canManagerApprove) {
            return;
        }

        setSubmitting(true);
        router.post(
            managerApproveUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const markComplete = () => {
        if (!completeUrl || !approval.canComplete) {
            return;
        }

        setSubmitting(true);
        router.post(
            completeUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleDelete = () => {
        if (!approval.canDelete) {
            return;
        }

        setDeleting(true);
        router.delete(deleteUrl, {
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <div className="spkDetailShell">
                <div className="spkDetailStack">
                    <div className="spkTopBarCard">
                        <div className="spkTopBarMain">
                            <div className="spkTopBarLeft">
                                <div className="spkDocTitleBlock">
                                    <div className="spkDocTitleRow">
                                        <h1 className="spkDocTitle">
                                            {displayValue(coranItem.docNo)}
                                        </h1>
                                    </div>
                                </div>

                            <div
                                className="spkStatusPipeline"
                                aria-label="Status Dokumen Coran"
                            >
                                {statusStages.map((stage, index) => (
                                    <div
                                        key={stage.key}
                                        className={[
                                            'spkStatusStage',
                                            index === activeStageIndex
                                                ? 'is-active'
                                                : '',
                                            index < activeStageIndex
                                                ? 'is-done'
                                                : '',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        {stage.label}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="spkControlBarRight">
                            <div className="spkHeaderActions">
                                {((approval.canSubmit && submitUrl) ||
                                    (approval.canManagerApprove &&
                                        managerApproveUrl)) && (
                                    <div className="spkApprovalActions">
                                        {approval.canSubmit && submitUrl ? (
                                            <Button
                                                design="Emphasized"
                                                icon={paperPlaneIcon}
                                                disabled={submitting || deleting}
                                                onClick={submitToManager}
                                            >
                                                {submitting
                                                    ? 'Mengirim...'
                                                    : 'Kirim ke Manager Produksi'}
                                            </Button>
                                        ) : null}
                                        {approval.canManagerApprove &&
                                        managerApproveUrl ? (
                                            <Button
                                                design="Positive"
                                                className="spkApprovalApproveBtn"
                                                icon={paperPlaneIcon}
                                                disabled={submitting || deleting}
                                                onClick={approveByManager}
                                            >
                                                {submitting
                                                    ? 'Memproses...'
                                                    : 'Approve'}
                                            </Button>
                                        ) : null}
                                    </div>
                                )}
                                {approval.canComplete && completeUrl ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn spkHeaderActionBtn--positive spkHeaderTextActionBtn"
                                        disabled={submitting || deleting}
                                        onClick={markComplete}
                                    >
                                        <Icon
                                            name={acceptIcon}
                                            mode="Decorative"
                                        />
                                        {submitting
                                            ? 'Memproses...'
                                            : 'Selesai'}
                                    </button>
                                ) : null}
                                {approval.canOpenEdit ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn"
                                        aria-label="Edit"
                                        title="Edit Dokumen Coran"
                                        disabled={submitting || deleting}
                                        onClick={() =>
                                            router.visit(editHref)
                                        }
                                    >
                                        <Icon
                                            name={editIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                ) : null}
                                {approval.canDelete ? (
                                    <button
                                        type="button"
                                        className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                        aria-label="Hapus"
                                        title="Hapus Dokumen Coran"
                                        disabled={submitting || deleting}
                                        onClick={() => setDeleteOpen(true)}
                                    >
                                        <Icon
                                            name={deleteIcon}
                                            mode="Decorative"
                                        />
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className="spkHeaderActionBtn spkHeaderActionBtn--danger"
                                    aria-label="Tutup"
                                    title="Tutup"
                                    disabled={submitting || deleting}
                                    onClick={() => router.visit(backHref)}
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

                <div className="spkDetailBody">
                    <section className="spkMainPanel">
                        <div
                            className="spkSectionTabs"
                            role="tablist"
                            aria-label="Konten utama Coran"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={mainSection === 'informasi'}
                                className={[
                                    'spkSectionTab',
                                    mainSection === 'informasi'
                                        ? 'is-active'
                                        : '',
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                                onClick={() => setMainSection('informasi')}
                            >
                                <span className="spkSectionTabLabel">
                                    Informasi
                                </span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={mainSection === 'riwayat'}
                                className={[
                                    'spkSectionTab',
                                    mainSection === 'riwayat'
                                        ? 'is-active'
                                        : '',
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                                onClick={() => setMainSection('riwayat')}
                            >
                                <span className="spkSectionTabLabel">
                                    Riwayat
                                </span>
                            </button>
                        </div>

                        {mainSection === 'informasi' ? (
                        <div
                            role="tabpanel"
                            aria-label="Informasi"
                            className="spkInformasiProduksiBody"
                        >
                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Informasi Dokumen
                                </h3>
                                <div className="jewelCadDetailInfoLayout coranDetailInfoLayout">
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailInfoTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Tanggal</th>
                                                <td>
                                                    {formatTransDate(
                                                        coranItem.transDate,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Pengrajin</th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.craftsmanName,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total SPK
                                                </th>
                                                <td>{coranItem.spkCount}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Hasil coran OK (%)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.okSpkPercent,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table className="spkItemMetaTable spkItemMetaTable--sm jewelCadDetailNotesTable">
                                        <tbody>
                                            <tr>
                                                <th scope="row">
                                                    Total bahan emas
                                                    diserahkan (g)
                                                </th>
                                                <td>
                                                    {displayValue(
                                                        coranItem.totalSubmitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total sisa hasil coran (g)
                                                </th>
                                                <td>
                                                    {formatShareOfTotalMaterial(
                                                        coranItem.totalResultMaterial,
                                                        coranItem.totalSubmitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    Total hasil coran (g)
                                                </th>
                                                <td>
                                                    {formatShareOfTotalMaterial(
                                                        coranItem.totalSpkWeight,
                                                        coranItem.totalSubmitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Susut (g)</th>
                                                <td>
                                                    {formatShareOfTotalMaterial(
                                                        coranItem.shrink,
                                                        coranItem.totalSubmitMaterial,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    List SPK
                                </h3>
                                <div className="spkTableScroll">
                                    <table className="spkTable masterDataTable">
                                        <thead>
                                            <tr>
                                                <th className="spkTableColSpkNo">
                                                    SPK
                                                </th>
                                                <th className="spkTableColCenter spkTableColTipeProduksi">
                                                    Tipe Produksi
                                                </th>
                                                <th>SKU</th>
                                                <th>Qty</th>
                                                <th>
                                                    Berat Hasil
                                                    <br />
                                                    Coran (g)
                                                </th>
                                                <th>Kadar</th>
                                                <th className="spkTableColCenter">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {coranItem.details.length === 0 ? (
                                                <tr>
                                                    <td colSpan={7}>
                                                        Belum ada SPK pada
                                                        dokumen ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                coranItem.details.map(
                                                    (detail) => {
                                                        const qcStatus =
                                                            resolveQcStatusFromCoranStatus(
                                                                detail.status,
                                                            );

                                                        return (
                                                            <tr
                                                                key={`detail-${detail.lineId}`}
                                                            >
                                                                <td className="spkTableColSpkNo">
                                                                    <strong>
                                                                        {displayValue(
                                                                            detail.spkNo,
                                                                        )}
                                                                    </strong>
                                                                </td>
                                                                <td className="spkTableColCenter spkTableColTipeProduksi">
                                                                    <SpkOrderTypeColumn
                                                                        spkType={
                                                                            detail.spkType
                                                                        }
                                                                        orderTypeLabel={
                                                                            detail.orderTypeLabel
                                                                        }
                                                                    />
                                                                </td>
                                                                <td>
                                                                    <SpkItemSkuColumn
                                                                        typeCode={
                                                                            detail.typeCode
                                                                        }
                                                                        productItemName={
                                                                            detail.productItemName
                                                                        }
                                                                        skuCode={
                                                                            detail.skuCode
                                                                        }
                                                                        itemDescription={
                                                                            detail.itemDescription
                                                                        }
                                                                    />
                                                                </td>
                                                                <td>
                                                                    {displayValue(
                                                                        detail.satuan,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {displayValue(
                                                                        detail.weight,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {displayValue(
                                                                        detail.kadar,
                                                                    )}
                                                                </td>
                                                                <td className="spkTableColCenter">
                                                                    {qcStatus ? (
                                                                        <SpkQcStatusBadge
                                                                            status={
                                                                                qcStatus
                                                                            }
                                                                        />
                                                                    ) : (
                                                                        displayValue(
                                                                            detail.statusLabel,
                                                                        )
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="spkShowSection">
                                <h3 className="spkShowSectionTitle">
                                    Bahan Emas
                                </h3>
                                <CoranMaterialBreakdownTables
                                    breakdown={coranItem.coranBreakdown}
                                    submitMaterials={coranItem.submitMaterials}
                                    resultMaterials={coranItem.resultMaterials}
                                    totalSubmit={
                                        coranItem.totalSubmitMaterial
                                    }
                                    totalResult={
                                        coranItem.totalResultMaterial
                                    }
                                />
                            </section>
                            {approvalFooter.length > 0 ? (
                                <div className="spkShowBottom">
                                    <footer
                                        className="spkApprovalFooter spkApprovalFooter--splitEnds"
                                        aria-label="Persetujuan"
                                    >
                                        {approvalFooter.map((column) => (
                                            <div
                                                key={column.title}
                                                className="spkApprovalFooterCol"
                                            >
                                                <div className="spkApprovalFooterTitle">
                                                    {column.title}
                                                </div>
                                                <div className="spkApprovalFooterMeta">
                                                    <div className="spkApprovalFooterMetaRow">
                                                        <span className="spkApprovalFooterMetaLabel">
                                                            Nama
                                                        </span>
                                                        <span className="spkApprovalFooterMetaValue">
                                                            {displayValue(
                                                                column.name,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="spkApprovalFooterMetaRow">
                                                        <span className="spkApprovalFooterMetaLabel">
                                                            Tanggal
                                                        </span>
                                                        <span className="spkApprovalFooterMetaValue">
                                                            {displayValue(
                                                                column.date,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </footer>
                                </div>
                            ) : null}
                        </div>
                        ) : null}

                        {mainSection === 'riwayat' ? (
                            <SpkApprovalTimelinePanel
                                events={approvalTimeline}
                            />
                        ) : null}
                    </section>
                </div>
            </div>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent className="spkDeleteDialog">
                    <DialogHeader>
                        <DialogTitle>Hapus Dokumen Coran</DialogTitle>
                        <DialogDescription>
                            Dokumen {coranItem.docNo ?? coranItem.id} akan
                            dihapus dan tidak lagi muncul di daftar coran.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            design="Transparent"
                            disabled={deleting}
                            onClick={() => setDeleteOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Negative"
                            disabled={deleting}
                            onClick={handleDelete}
                        >
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
